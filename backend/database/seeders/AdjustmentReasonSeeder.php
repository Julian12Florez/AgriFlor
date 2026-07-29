<?php

namespace Database\Seeders;

use App\Models\AdjustmentReason;
use Illuminate\Database\Seeder;

class AdjustmentReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            ['code' => 'error_captura', 'name' => 'Error de captura', 'direction' => 'any'],
            ['code' => 'conteo_fisico', 'name' => 'Conteo físico', 'direction' => 'any'],
            ['code' => 'merma_dano', 'name' => 'Merma o daño', 'direction' => 'exit'],
            ['code' => 'vencimiento', 'name' => 'Producto vencido', 'direction' => 'exit'],
            ['code' => 'robo_perdida', 'name' => 'Robo o pérdida', 'direction' => 'exit'],
            ['code' => 'devolucion', 'name' => 'Devolución', 'direction' => 'any'],
            ['code' => 'compra_doble', 'name' => 'Compra/recepción doble', 'direction' => 'exit'],
            ['code' => 'salida_erronea', 'name' => 'Salida errónea (revertir)', 'direction' => 'entry'],
            ['code' => 'traslado_interno', 'name' => 'Traslado interno', 'direction' => 'transfer'],
            ['code' => 'ajuste_inicial', 'name' => 'Ajuste inicial', 'direction' => 'entry'],
        ];

        foreach ($reasons as $reason) {
            AdjustmentReason::updateOrCreate(['code' => $reason['code']], $reason);
        }
    }
}
