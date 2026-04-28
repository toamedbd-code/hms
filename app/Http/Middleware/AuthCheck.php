<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->guard('admin')->check() && strcasecmp(trim((string) (auth()->guard('admin')->user()->status ?? '')), 'Active') === 0) {
            // Try several likely dashboard route names (RouteServiceProvider prefixes
            // backend routes with 'backend.'). Redirect to the first one that exists
            // to avoid RouteNotFound exceptions when route names vary.
            $routeCandidates = [
                'backend.dashboard',
                'admin.dashboard',
                'dashboard',
            ];

            foreach ($routeCandidates as $candidate) {
                if (\Illuminate\Support\Facades\Route::has($candidate)) {
                    return redirect()->route($candidate);
                }
            }

            // Fallback to root if no named dashboard is available
            return redirect('/');
        }

        return $next($request);
    }
}
