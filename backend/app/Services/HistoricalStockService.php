<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Cuánto se puede sacar de una ubicación CON UNA FECHA DADA sin dejar el kardex
 * en negativo en ningún momento.
 *
 * POR QUÉ NO BASTA CON MIRAR EL STOCK DE HOY
 * ==========================================
 * El sistema validaba las salidas contra la existencia actual. Con una salida
 * fechada hoy eso es correcto, pero con una salida RETROFECHADA no: insertar una
 * salida con fecha D no baja solo el saldo de ese día, baja el de D y el de
 * TODOS los días siguientes.
 *
 * Caso medido en un ensayo contra copia de producción: se sacaron 1.500 kg de
 * CALFOS de Breva fechados el 22 y el 02 de agosto, de un producto cuya única
 * entrada a esa finca está fechada el 1 de septiembre. El sistema lo aceptó sin
 * una sola advertencia porque HOY el saldo es +17.000. Resultado:
 *
 *     saldo de kardex de Breva/CALFOS al 31/08/2026 = −1.500,00 kg
 *
 * El stock de hoy quedaba correcto y `inventory` no tenía negativos, así que
 * ningún chequeo de "¿hay algo en rojo?" lo detectaba. El daño estaba en la foto
 * histórica de agosto, que es justo la que se entrega a Contabilidad.
 *
 * LA REGLA CORRECTA
 * =================
 * Insertar una salida de Q con fecha D resta Q al saldo acumulado de cada punto
 * de la línea de tiempo desde D en adelante. Para que ninguno quede negativo, lo
 * que hay que exigir NO es "el saldo en D es suficiente", sino:
 *
 *     min( saldo acumulado en cada fecha >= D )  >=  Q
 *
 * No basta el saldo en D. Si en D hay 2.000 pero cinco días después una salida
 * lo deja en 100, sacar 1.500 fechado en D deja ese día en −1.400.
 *
 * DENTRO DEL MISMO DÍA
 * ====================
 * El saldo del día D cuenta lo que entró ESE MISMO DÍA. Al principio se hizo al
 * revés —se asumía que la salida ocurría antes que los movimientos ya registrados
 * en D— y eso prohibía el caso más común de la operación: comprar 40 kg de
 * OXICLORURO DE COBRE por la mañana, recibirlos, y despacharlos a la finca esa
 * misma tarde. El sistema respondía "no tiene inventario", y al mover la salida al
 * día siguiente sí la dejaba. Es exactamente el síntoma que reportó el cliente el
 * 09-sep-2026.
 *
 * `movement_date` es una fecha, no un instante: dentro del día no hay orden que
 * distinguir, y el hecho económico es que el producto SÍ estaba ese día. Lo que
 * sigue protegido es el caso peligroso de verdad: fechar una salida ANTES del día
 * en que el producto llegó.
 */
class HistoricalStockService
{
    /** Holgura para no pelear con los decimales de MySQL. */
    private const EPSILON = 0.01;

    /**
     * Cuánto puede salir de [producto, marca, ubicación] con fecha `$fecha` sin
     * que el kardex quede negativo en ningún momento desde esa fecha en adelante.
     *
     * Devuelve la cantidad en UNIDAD BASE del producto. Nunca devuelve negativo:
     * si el histórico ya está en rojo (por datos anteriores a esta validación),
     * responde 0 en vez de un número sin sentido.
     *
     * @param  string|null  $excluirMovimientoId  Movimiento a ignorar, para poder
     *                                            revalidar uno que ya existe sin
     *                                            que se cuente a sí mismo.
     */
    public function disponibleALaFecha(
        string $productId,
        ?string $brandId,
        string $locationId,
        string $fecha,
        ?string $excluirMovimientoId = null,
    ): float {
        $fecha = substr($fecha, 0, 10);

        $query = DB::table('inventory_movements')
            ->select('movement_date')
            ->selectRaw("SUM(CASE WHEN type = 'entry' THEN quantity ELSE -quantity END) as delta")
            ->where('product_id', $productId)
            ->where('location_id', $locationId);

        // La marca puede venir nula (producto sin marca): se compara como tal, no
        // con `= NULL`, que en SQL nunca es cierto y dejaría pasar cualquier cosa.
        $query = $brandId === null
            ? $query->whereNull('brand_id')
            : $query->where('brand_id', $brandId);

        if ($excluirMovimientoId !== null) {
            $query->where('id', '!=', $excluirMovimientoId);
        }

        $filas = $query->groupBy('movement_date')
            ->orderBy('movement_date')
            ->get();

        $acumulado = 0.0;
        $saldoEnLaFecha = 0.0;
        $minimoDesdeLaFecha = null;

        foreach ($filas as $fila) {
            $dia = substr((string) $fila->movement_date, 0, 10);
            $acumulado += (float) $fila->delta;

            // Saldo AL CIERRE del día D, contando lo que ya entró ESE MISMO DÍA.
            if ($dia <= $fecha) {
                $saldoEnLaFecha = $acumulado;
            }

            // Y el punto más bajo de ahí en adelante: insertar la salida baja
            // todos esos puntos por igual.
            if ($dia >= $fecha) {
                $minimoDesdeLaFecha = $minimoDesdeLaFecha === null
                    ? $acumulado
                    : min($minimoDesdeLaFecha, $acumulado);
            }
        }

        $minimo = $minimoDesdeLaFecha === null
            ? $saldoEnLaFecha
            : min($saldoEnLaFecha, $minimoDesdeLaFecha);

        return max(0.0, round($minimo, 2));
    }

    /**
     * ¿Cabe sacar `$cantidad` con esa fecha?
     */
    public function alcanza(
        string $productId,
        ?string $brandId,
        string $locationId,
        string $fecha,
        float $cantidad,
        ?string $excluirMovimientoId = null,
    ): bool {
        $disponible = $this->disponibleALaFecha(
            $productId,
            $brandId,
            $locationId,
            $fecha,
            $excluirMovimientoId,
        );

        return $disponible >= $cantidad - self::EPSILON;
    }

    /**
     * Mensaje para el usuario cuando no alcanza. Se explica en términos de lo que
     * él hizo —una fecha— y no en términos de kardex, que no le dice nada.
     */
    public function mensajeDeRechazo(
        string $nombreProducto,
        string $nombreUbicacion,
        string $fecha,
        float $solicitado,
        float $disponible,
        string $unidad,
    ): string {
        return sprintf(
            'No hay existencia suficiente de %s en %s con fecha %s. Solicitado: %s %s · '
            . 'Disponible a esa fecha: %s %s. En esa fecha el producto todavía no había '
            . 'llegado (o ya estaba comprometido): registre primero la entrada, o use una '
            . 'fecha posterior. Hoy puede haber existencia, pero registrarlo con esa fecha '
            . 'dejaría el mes en negativo.',
            $nombreProducto,
            $nombreUbicacion,
            \Carbon\CarbonImmutable::parse($fecha)->format('d/m/Y'),
            number_format($solicitado, 2, ',', '.'),
            $unidad,
            number_format($disponible, 2, ',', '.'),
            $unidad,
        );
    }
}
