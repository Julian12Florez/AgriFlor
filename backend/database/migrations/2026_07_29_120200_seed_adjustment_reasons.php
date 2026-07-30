<?php

use Database\Seeders\AdjustmentReasonSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Siembra el catálogo de motivos de ajuste como parte de la MIGRACIÓN, no solo
 * del seeder.
 *
 * El despliegue (.github/workflows/deploy.yml) ejecuta únicamente
 * `php artisan migrate --force`; nunca `db:seed`. Con `adjustment_reasons`
 * vacía, `reason_id` es `required|exists:adjustment_reasons,id`, así que NO se
 * podría crear ninguna solicitud de ajuste: el módulo llegaría a producción
 * inservible y el arreglo exigiría entrar al servidor a mano.
 *
 * Reutiliza AdjustmentReasonSeeder (que sigue funcionando por su cuenta, y lo
 * usan las pruebas) en vez de duplicar la lista: su `updateOrCreate` por `code`
 * hace que correr esto sea idempotente y no pise cambios posteriores de `name`
 * ni motivos añadidos por el cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new AdjustmentReasonSeeder())->run();
    }

    /**
     * Sin rollback a propósito: al revertir esta migración los motivos pueden
     * estar ya referenciados por `adjustments.reason_id` (FK), y borrarlos
     * fallaría o dejaría solicitudes huérfanas. La migración que crea la tabla es
     * la que se encarga de eliminarla.
     */
    public function down(): void
    {
    }
};
