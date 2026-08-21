<?php

use Database\Seeders\CompanySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Siembra las empresas emisoras como parte de la MIGRACIÓN, no solo del seeder.
 *
 * El despliegue (.github/workflows/deploy.yml) ejecuta únicamente
 * `php artisan migrate --force`; nunca `db:seed`. Con `companies` vacía,
 * `company_id` es `required|exists:companies,id` en los FormRequest, así que NO
 * se podría crear ninguna compra ni ninguna salida: dos módulos centrales
 * llegarían a producción inservibles. Además la migración siguiente
 * (2026_08_22_100200) necesita el id de AGRILOGISTIC para el backfill.
 *
 * Reutiliza CompanySeeder (que sigue sirviendo por su cuenta y lo usan las
 * pruebas) en vez de duplicar los datos: su `updateOrCreate` por `nit` hace
 * que correr esto sea idempotente y no pise ediciones posteriores hechas
 * desde la UI de administración.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new CompanySeeder())->run();
    }

    /**
     * Sin rollback a propósito: al revertir, las empresas pueden estar ya
     * referenciadas por `purchases.company_id` / `product_outputs.company_id`
     * (FK restrict) y borrarlas fallaría. La migración que crea la tabla es la
     * que se encarga de eliminarla.
     */
    public function down(): void
    {
    }
};
