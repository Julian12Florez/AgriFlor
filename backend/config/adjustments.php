<?php

/**
 * Configuración del módulo de Ajustes de Inventario.
 *
 * `closed_period_until`: fecha de cierre contable. Ningún ajuste (al crear NI
 * al aprobar, ver StoreAdjustmentRequest::validateMovementDateNotClosed y
 * AdjustmentController::assertMovementDateNotClosed) puede tener
 * `movement_date` igual o anterior a esta fecha: ese periodo ya está
 * conciliado con Contabilidad (Siigo) y un movimiento retroactivo lo
 * descuadraría en silencio — el informe mensual de ese mes ya se cerró y
 * entregó, así que un ajuste con fecha dentro de él cambia "Aumentos"/
 * "Disminuciones" sin que la columna "Variación" (la que se concilia contra
 * Siigo) lo delate.
 *
 * CÓMO CAMBIARLA cuando Contabilidad cierre un mes nuevo:
 *   1. Sin re-deploy: definir ADJUSTMENTS_CLOSED_PERIOD_UNTIL en el .env del
 *      servidor con la nueva fecha (formato YYYY-MM-DD) y correr
 *      `php artisan config:cache` (o reiniciar el servicio).
 *   2. Con deploy: actualizar el valor por defecto de abajo.
 *
 * Valor inicial: 2026-05-31, el último mes conciliado con Siigo (ver
 * database/migrations/2026_06_16_120000_align_may_to_accounting_siigo.php).
 */
return [
    /*
     | UN SOLO CORTE PARA TODO EL INVENTARIO
     |
     | Este valor apuntaba a su propia variable con default '2026-05-31', mientras
     | el corte real de la operación es el 2026-07-31 (el re-baseline). Junio y
     | julio quedaban escribibles desde Ajustes: se pudo crear Y APROBAR un ajuste
     | fechado el 20-jul, el informe de julio se movió, y la columna "Variación"
     | —donde se concilia— siguió en 0. La fuga era invisible justo donde se mira.
     |
     | Ahora hereda de config/inventory.php. La variable propia sigue existiendo
     | por si alguna vez hay que abrir Ajustes sin abrir el resto, pero su default
     | ya no puede quedarse atrás.
     */
    'closed_period_until' => env(
        'ADJUSTMENTS_CLOSED_PERIOD_UNTIL',
        env('INVENTORY_CLOSED_PERIOD_UNTIL', '2026-07-31')
    ),
];
