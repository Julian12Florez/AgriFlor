<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }

        $user = auth()->user();

        // Load the role relation if not already loaded
        if (!$user->relationLoaded('roleRelation')) {
            $user->load('roleRelation');
        }

        // Check if user has required role using the new role system (roleRelation)
        // $roles is an array like ['admin', 'warehouse']
        $userRoleName = $user->roleRelation?->name ?? $user->role;

        if (!in_array($userRoleName, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para acceder a este recurso'
            ], 403);
        }

        return $next($request);
    }
}
