<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla para registrar aplicaciones de productos agrícolas (fertilizantes, pesticidas, etc.)
     * a lotes de finca. Las aplicaciones representan el consumo de productos en campo.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Número de aplicación (generado automáticamente)
            $table->string('application_number', 50)->unique();

            // Ubicación origen (bodega o finca desde donde sale el producto)
            $table->uuid('origin_location_id');

            // Lote de finca destino (donde se aplica el producto)
            $table->uuid('farm_lot_id');

            // Fecha de aplicación
            $table->date('application_date');

            // Usuario responsable de la aplicación
            $table->uuid('applied_by');

            // Estado de la aplicación
            // pending: creada pero no aprobada (no descuenta stock)
            // approved: aprobada y stock descontado
            // cancelled: cancelada (no descuenta stock)
            $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');

            // Tipo de aplicación (fertilización, fumigación, etc.)
            $table->string('application_type', 100)->nullable();

            // Observaciones generales
            $table->text('observations')->nullable();

            // Usuario que aprobó la aplicación
            $table->uuid('approved_by')->nullable();

            // Fecha de aprobación
            $table->timestamp('approved_at')->nullable();

            // Usuario que canceló la aplicación
            $table->uuid('cancelled_by')->nullable();

            // Fecha de cancelación
            $table->timestamp('cancelled_at')->nullable();

            // Motivo de cancelación
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('origin_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('farm_lot_id')->references('id')->on('farm_lots')->onDelete('restrict');
            $table->foreign('applied_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('origin_location_id');
            $table->index('farm_lot_id');
            $table->index('application_date');
            $table->index('status');
            $table->index('applied_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
