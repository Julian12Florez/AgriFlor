<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $module  The module name (e.g., 'admin', 'reception', 'outputs')
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        // Load role relationship
        $user->load('roleRelation');

        // Check if user has access to the module
        if (!$user->hasModuleAccess($module)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a este módulo',
                'required_module' => $module,
            ], 403);
        }

        return $next($request);
    }
}
