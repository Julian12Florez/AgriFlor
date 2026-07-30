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
    'closed_period_until' => env('ADJUSTMENTS_CLOSED_PERIOD_UNTIL', '2026-05-31'),
];
