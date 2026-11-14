<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeSessionActivity
{
    /**
     * Handle an incoming request.
     *
     * Initialize last_activity on session start to prevent
     * false timeout on first request after login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // If user is authenticated but last_activity not set, initialize it
            if (auth('admin')->check()) {
                if (!session()->has('last_activity')) {
                    session(['last_activity' => now()->getTimestamp()]);
                }
            }
        } catch (\Exception $e) {
            // Silently continue if session initialization fails
        }

        return $next($request);
    }
}
