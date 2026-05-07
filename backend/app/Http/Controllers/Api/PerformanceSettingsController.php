<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerformanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = PerformanceSettings::current();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $settings->id,
                'globalSobrepasoPct' => $settings->global_sobrepaso_pct,
                'global_sobrepaso_pct' => $settings->global_sobrepaso_pct,
                'globalAltoPct' => $settings->global_alto_pct,
                'global_alto_pct' => $settings->global_alto_pct,
                'globalMedioPct' => $settings->global_medio_pct,
                'global_medio_pct' => $settings->global_medio_pct,
                'globalKFactor' => $settings->global_k_factor,
                'global_k_factor' => $settings->global_k_factor,
                'updatedAt' => $settings->updated_at?->toISOString(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'global_sobrepaso_pct' => ['required', 'numeric', 'min:100', 'max:300'],
            'global_alto_pct' => ['required', 'numeric', 'min:1'],
            'global_medio_pct' => ['required', 'numeric', 'min:1'],
            'global_k_factor' => ['required', 'numeric', 'min:1', 'max:10'],
        ]);

        // Validar coherencia: sobrepaso > alto > medio
        if ($validated['global_sobrepaso_pct'] <= $validated['global_alto_pct']) {
            return response()->json([
                'success' => false,
                'message' => 'El umbral de Sobrepaso debe ser mayor que el de Alto',
            ], 422);
        }
        if ($validated['global_alto_pct'] <= $validated['global_medio_pct']) {
            return response()->json([
                'success' => false,
                'message' => 'El umbral de Alto debe ser mayor que el de Medio',
            ], 422);
        }

        $settings = PerformanceSettings::current();
        $settings->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Configuración de rendimiento actualizada exitosamente',
            'data' => [
                'id' => $settings->id,
                'globalSobrepasoPct' => $settings->global_sobrepaso_pct,
                'global_sobrepaso_pct' => $settings->global_sobrepaso_pct,
                'globalAltoPct' => $settings->global_alto_pct,
                'global_alto_pct' => $settings->global_alto_pct,
                'globalMedioPct' => $settings->global_medio_pct,
                'global_medio_pct' => $settings->global_medio_pct,
                'globalKFactor' => $settings->global_k_factor,
                'global_k_factor' => $settings->global_k_factor,
                'updatedAt' => $settings->updated_at?->toISOString(),
            ],
        ]);
    }
}
