<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hace opcionales advance_pct_today y accumulated_snapshot_pct.
     * Ahora el registro diario solo requiere persons_today.
     * El % de avance se puede registrar opcionalmente, pero no es obligatorio.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE task_daily_logs MODIFY advance_pct_today DECIMAL(5,2) NULL');
        DB::statement('ALTER TABLE task_daily_logs MODIFY accumulated_snapshot_pct DECIMAL(5,2) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE task_daily_logs MODIFY advance_pct_today DECIMAL(5,2) NOT NULL');
        DB::statement('ALTER TABLE task_daily_logs MODIFY accumulated_snapshot_pct DECIMAL(5,2) NOT NULL');
    }
};
