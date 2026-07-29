<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OptimizeLoginResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only for login page GET requests
        if (($request->path() === 'login' || $request->path() === '/login') && $request->isMethod('GET')) {
            // Cache login page for 5 minutes in browser - public but vary by encoding
            $response->header('Cache-Control', 'public, max-age=300, must-revalidate');
            $response->header('Vary', 'Accept-Encoding');
        }

        return $response;
    }
}
