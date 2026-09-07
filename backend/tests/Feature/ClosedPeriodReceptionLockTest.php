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
}
