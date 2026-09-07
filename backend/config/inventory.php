<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Periodo contable cerrado
    |--------------------------------------------------------------------------
    |
    | Ningún movimiento de inventario puede escribirse con `movement_date` igual
    | o anterior a esta fecha: son meses ya conciliados con Contabilidad.
    |
    | POR QUÉ VIVE AQUÍ Y NO SOLO EN EL .env
    | --------------------------------------
    | El candado de Ajustes leía `config('adjustments.closed_period_until')`, cuyo
    | default es '2026-05-31'. El corte real de producción, '2026-07-31', existía
    | ÚNICAMENTE como variable en el docker-compose del servidor, sin versionar y
    | sin respaldo: si el contenedor se recreaba sin ella, el sistema aceptaba en
    | silencio escrituras dentro de junio y julio, es decir, dentro del
    | re-baseline. Un candado que falla abriéndose no es un candado.
    |
    | El default de ESTE archivo es el corte real. El .env solo puede moverlo
    | hacia adelante cuando Contabilidad cierre un mes nuevo.
    |
    | Qué protege, con un caso real: una recepción escrita el 29-jul-2026 con
    | `movement_date` = 2026-05-29 metió +10.500 kg de QROP KS dentro de mayo, un
    | mes que Contabilidad ya había conciliado producto por producto. El neto de
    | ese producto al 31/05 da hoy 45.050 kg; sin ese movimiento da 34.550, que es
    | exactamente la cifra de Siigo.
    |
    */

    'closed_period_until' => env('INVENTORY_CLOSED_PERIOD_UNTIL', '2026-07-31'),

];
