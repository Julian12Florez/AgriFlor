<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asocia cada compra y cada salida a la empresa emisora que firma el documento.
 *
 * ORDEN DELIBERADO (no lo reordenes):
 *   1) Se agrega la columna NULLABLE y con índice, SIN clave foránea.
 *   2) Se rellena el histórico con AGRILOGISTIC (la empresa con la que se
 *      operó hasta hoy) mediante UPDATE simples contra un LITERAL.
 *   3) Recién entonces se crea la FK, cuando ya no quedan valores huérfanos.
 *
 * Sobre el paso 2: se usa `UPDATE tabla SET company_id = ? WHERE company_id IS NULL`
 * con el uuid resuelto en PHP. NUNCA un `UPDATE ... JOIN companies ...`:
 * un JOIN entre dos tablas dispara el error 1267 (Illegal mix of collations)
 * cuando los cotejos difieren entre tablas, y eso ya tumbó un despliegue de
 * este proyecto (commit 470dc78). Un literal es "coercible" para MySQL y toma
 * el cotejo de la columna, así que el problema no puede reproducirse.
 *
 * La columna queda NULLABLE en base de datos aunque los FormRequest la exijan:
 * así los 155 compras / 351 salidas históricos (y cualquier fila anterior a
 * este cambio) siguen siendo válidos y un PDF viejo no revienta —
 * `CompanyInfo::resolve(null)` cae a `config('app.company_*')`.
 */
return new class extends Migration
{
    private const TABLES = ['purchases', 'product_outputs'];

    public function up(): void
    {
        // ---- Paso 1: columna nullable + índice, sin FK ----
        foreach (self::TABLES as $tableName) {
            if (Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->uuid('company_id')->nullable()->index();
            });
        }

        // ---- Paso 2: backfill del histórico contra un LITERAL ----
        $defaultCompanyId = DB::table('companies')
            ->where('nit', '901441688-7')
            ->value('id');

        // Red de seguridad por si el nit cambiara: cae a la empresa por defecto.
        if (!$defaultCompanyId) {
            $defaultCompanyId = DB::table('companies')
                ->where('is_default', true)
                ->value('id');
        }

        if ($defaultCompanyId) {
            foreach (self::TABLES as $tableName) {
                DB::statement(
                    "UPDATE {$tableName} SET company_id = ? WHERE company_id IS NULL",
                    [$defaultCompanyId]
                );
            }
        }

        // ---- Paso 3: ahora sí la clave foránea ----
        foreach (self::TABLES as $tableName) {
            if ($this->hasForeignKey($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if ($this->hasForeignKey($tableName, 'company_id')) {
                    $table->dropForeign(['company_id']);
                }
                $table->dropColumn('company_id');
            });
        }
    }

    /**
     * ¿La columna ya tiene una FK? Hace la migración re-ejecutable sin explotar
     * con "Duplicate foreign key constraint name".
     */
    private function hasForeignKey(string $tableName, string $columnName): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $columnName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
