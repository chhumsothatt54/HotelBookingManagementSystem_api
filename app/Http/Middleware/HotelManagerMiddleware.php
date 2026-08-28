<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HotelManagerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 430);
        }
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active',
            ], 403);
        }
        if ($user->role !== 'hotel_manager') {
            return response()->json([
                'message' => 'Only hotel manager can access this resource',
            ], 403);
        }

        return $next($request);
    }
}
