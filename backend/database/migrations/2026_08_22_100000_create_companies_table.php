<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas emisoras de documentos (órdenes de compra y remisiones).
 *
 * El grupo opera con tres razones sociales distintas y cada documento debe
 * salir a nombre de la que corresponda, con su propio membrete y plantilla.
 *
 * El LOGO se guarda EN LA BASE DE DATOS (mime + base64), no en `storage/`:
 * el backend corre en un contenedor que se reconstruye en cada despliegue y
 * `storage/app` no persiste, así que un logo subido por la UI desaparecería
 * en el siguiente deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('nit', 20)->unique();
            $table->string('address', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('legal_rep', 150)->nullable();
            $table->string('tax_regime', 200)->nullable();
            $table->string('ciiu', 50)->nullable();

            // Nombre de la plantilla Blade a usar:
            // resources/views/pdf/remision/{template}.blade.php
            $table->string('template', 30)->default('clasico');

            // Logo embebido (ver nota del encabezado)
            $table->string('logo_mime', 50)->nullable();
            $table->longText('logo_base64')->nullable();

            $table->boolean('is_default')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('status');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
