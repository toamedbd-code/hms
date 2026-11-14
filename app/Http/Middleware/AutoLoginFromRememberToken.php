<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginFromRememberToken
{
    /**
     * Handle an incoming request.
     *
     * If user is not authenticated but has a valid remember token
     * (within 2 hours of logout), automatically log them in.
     * This provides a quick re-login experience after logout.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only process on login pages when not authenticated
        if (!auth('admin')->check() && ($request->is('*/login*') || $request->is('*/auth/login*'))) {
            try {
                // Laravel's guard will automatically use remember token if present
                // Call viaRemember to check and load user from remember token
                $user = auth('admin')->viaRemember();

                if (auth('admin')->check()) {
                    // Successfully authenticated via remember token
                    // Redirect to dashboard
                    return redirect()->route('admin.dashboard');
                }
            } catch (\Exception $e) {
                // Invalid or expired remember token (older than 2 hours)
                // Continue to login page and let user manually log in
            }
        }

        return $next($request);
    }
}
