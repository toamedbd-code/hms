<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminAuth
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

            // Ensure packages/middlewares that rely on the default guard (like Spatie Permission)
            // will use the admin guard for backend routes.
            Auth::shouldUse('admin');

            // Share admin side menus with Inertia while admin guard is active.
            Inertia::share('auth.sideMenus', function () {
                try {
                    return getSideMenus(auth()->guard('admin')->user());
                } catch (\Throwable $e) {
                    return [];
                }
            });
            return $next($request);
        } else {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('backend.auth.login'));
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Session expired or unauthorized. Please login again.',
                ], 401);
            }

            session()->flash('errMsg', 'Please Login First.');
            return redirect()->route('backend.auth.login');
        }
    }
}
