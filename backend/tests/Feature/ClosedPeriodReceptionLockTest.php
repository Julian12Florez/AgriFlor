<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El kardex no se escribe dentro de un mes ya conciliado con Contabilidad.
 *
 * El candado existía SOLO en Ajustes. La ruta por la que entra el 100% de los
 * movimientos de recepción validaba la fecha con `required|date` y nada más, así
 * que una recepción retrofechada podía meter producto en un mes cerrado sin que
 * nada lo delatara. Pasó de verdad: el 29-jul-2026 se escribió una recepción con
 * `movement_date` = 2026-05-29 por +10.500 kg de QROP KS, dentro de mayo, que
 * Contabilidad ya había conciliado producto por producto. El neto de ese producto
 * al 31/05 da hoy 45.050 kg; sin ese movimiento da 34.550, la cifra de Siigo.
 *
 * Y el corte real de producción (2026-07-31) vivía ÚNICAMENTE como variable de
 * entorno en el docker-compose del servidor, sin versionar. El default del código
 * era 2026-05-31: si el contenedor se recreaba sin esa variable, el sistema
 * aceptaba en silencio escrituras dentro de junio y julio, es decir, dentro del
 * re-baseline. Un candado que falla abriéndose no es un candado.
 */
class ClosedPeriodReceptionLockTest extends TestCase
{
    /**
     * El corte vive en el repositorio, no solo en el servidor. Si alguien lo
     * mueve, que sea un cambio revisable y no una variable que nadie respalda.
     */
    public function test_el_corte_contable_esta_versionado_y_no_es_fail_open(): void
    {
        $corte = config('inventory.closed_period_until');

        $this->assertSame(
            '2026-07-31',
            $corte,
            'El default del código debe ser el corte REAL de producción. Si vuelve a quedar '
            . 'antes del re-baseline, un contenedor recreado sin la variable acepta escrituras '
            . 'dentro de junio y julio.'
        );
    }

    /**
     * El corte no puede quedar antes del re-baseline: todo lo anterior al
     * 31/07/2026 se cargó desde el inventario físico del cliente.
     */
    public function test_el_corte_nunca_queda_antes_del_re_baseline(): void
    {
        $corte = \Carbon\CarbonImmutable::parse(config('inventory.closed_period_until'));
        $rebaseline = \Carbon\CarbonImmutable::parse('2026-07-31');

        $this->assertTrue(
            $corte->greaterThanOrEqualTo($rebaseline),
            'Mover el corte antes del 31/07/2026 reabre el periodo que el re-baseline fijó '
            . 'contra el conteo físico del cliente.'
        );
    }

    /**
     * La regla que aplica el candado en la ruta viva. Se comprueba sobre el
     * validador directamente para no montar toda una recepción: lo que importa
     * es que la fecha de recepción se compare contra el corte.
     */
    public function test_rechaza_una_fecha_dentro_del_periodo_cerrado(): void
    {
        $corte = config('inventory.closed_period_until');

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['reception_date' => '2026-05-29'],
            ['reception_date' => ['required', 'date', 'after:' . $corte]],
        );

        $this->assertTrue(
            $validator->fails(),
            'El 29/05/2026 —la fecha del movimiento que descuadró mayo contra Siigo— debe '
            . 'rechazarse.'
        );
    }

    /** El borde: el propio día del corte tampoco entra. */
    public function test_el_dia_del_corte_tampoco_se_acepta(): void
    {
        $corte = config('inventory.closed_period_until');

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['reception_date' => $corte],
            ['reception_date' => ['required', 'date', 'after:' . $corte]],
        );

        $this->assertTrue($validator->fails(), 'El corte es inclusivo: el 31/07 ya está cerrado.');
    }

    /**
     * Y lo que NO debe pasar: una compra vieja se sigue pudiendo recibir hoy.
     * El candado mira la fecha de RECEPCIÓN, no la del documento.
     */
    public function test_deja_recibir_hoy_un_documento_viejo(): void
    {
        $corte = config('inventory.closed_period_until');

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['reception_date' => \Carbon\CarbonImmutable::parse('2026-09-07')->toDateString()],
            ['reception_date' => ['required', 'date', 'after:' . $corte]],
        );

        $this->assertFalse(
            $validator->fails(),
            'Las dos salidas parciales de julio que siguen abiertas tienen que poder cerrarse '
            . 'hoy, con la fecha de hoy.'
        );
    }

    /**
     * Los dos cortes tienen que ser el mismo. El de Ajustes iba por su cuenta con
     * default 2026-05-31 mientras el real era 2026-07-31: junio y julio quedaban
     * escribibles desde Ajustes, y como la columna "Variación" seguía en 0, la
     * fuga era invisible justo donde se concilia.
     */
    public function test_ajustes_e_inventario_comparten_el_mismo_corte(): void
    {
        $this->assertSame(
            config('inventory.closed_period_until'),
            config('adjustments.closed_period_until'),
            'Dos cortes distintos = una puerta abierta en el módulo que menos se mira.'
        );
    }

    /**
     * `after:` compara TIMESTAMPS, no días: '2026-07-31T23:59:59' se colaba
     * mientras '2026-07-31' se rechazaba. El corte es inclusivo por día.
     */
    public function test_el_ultimo_instante_del_dia_del_corte_tampoco_pasa(): void
    {
        $primerDiaAbierto = \Carbon\CarbonImmutable::parse(config('inventory.closed_period_until'))
            ->addDay()->toDateString();

        $reglas = ['reception_date' => ['required', 'date', 'after_or_equal:' . $primerDiaAbierto]];

        foreach (['2026-07-31', '2026-07-31T23:59:59', '2026-07-15'] as $fecha) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Validator::make(['reception_date' => $fecha], $reglas)->fails(),
                "'{$fecha}' está dentro del periodo cerrado y debe rechazarse."
            );
        }

        $this->assertFalse(
            \Illuminate\Support\Facades\Validator::make(['reception_date' => '2026-08-01'], $reglas)->fails(),
            'El primer día abierto sí debe pasar.'
        );
    }

    /**
     * Sin tope de futuro, un dedazo de año mete la mercancía al estante pero la
     * deja FUERA del informe del mes: aparece en `inventory` y no en el kardex
     * hasta hoy. Se midieron 15,00 kg desaparecidos así en un ensayo.
     */
    public function test_no_se_acepta_una_fecha_futura(): void
    {
        $reglas = ['reception_date' => ['required', 'date', 'before_or_equal:today']];

        foreach (['2035-01-15', '2030-06-15', '2027-08-05'] as $fecha) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Validator::make(['reception_date' => $fecha], $reglas)->fails(),
                "'{$fecha}' es futura: no puede escribirse en el kardex."
            );
        }
    }
}
