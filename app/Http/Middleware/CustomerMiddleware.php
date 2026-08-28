<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerMiddleware
{

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check login
        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Check account status
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active',
            ], 403);
        }

        // Check Customer role
        if ($user->role !== 'customer') {
            return response()->json([
                'message' => 'Only customer can access this resource',
            ], 403);
        }

        return $next($request);
    }
}
