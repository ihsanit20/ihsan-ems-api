<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Support both: role:Admin,Owner  OR  role:Admin|Owner
        if (count($roles) === 1 && str_contains($roles[0], '|')) {
            $roles = explode('|', $roles[0]);
        }

        // Super roles (optional): always allowed
        $superRoles = config('auth.super_roles', ['Developer']);
        if (in_array($user->role, $superRoles, true)) {
            return $next($request);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Forbidden: role ' . $user->role . ' is not allowed.',
            ], 403);
        }

        return $next($request);
    }
}
