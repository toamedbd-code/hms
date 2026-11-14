<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleRememberMeSession
{
    /**
     * Handle an incoming request.
     *
     * This middleware enforces session inactivity timeout.
     * Sessions will automatically expire after 2 hours of inactivity
     * for security purposes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Only check if user is authenticated
            if (auth('admin')->check()) {
                $now = now()->getTimestamp();

                // Try to get last activity, with safe default
                try {
                    $lastActivity = session()->get('last_activity');
                } catch (\Exception $e) {
                    $lastActivity = null;
                }

                // Check for inactivity timeout (2 hours = 7200 seconds)
                $inactivityTimeout = 2 * 60 * 60; // 2 hours

                if ($lastActivity && ($now - $lastActivity) > $inactivityTimeout) {
                    // Session has been inactive for more than 2 hours - logout
                    auth('admin')->logout();
                    session()->flush();
                    $request->session()->regenerateToken();

                    return redirect()->route('backend.auth.login2');
                }

                // Update last activity timestamp - only if session is valid
                try {
                    session(['last_activity' => $now]);
                } catch (\Exception $e) {
                    // If session write fails, continue anyway (don't break the app)
                    \Log::warning('Session update failed in HandleRememberMeSession: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            // Any error in session handling, just continue
            \Log::error('Error in HandleRememberMeSession middleware: ' . $e->getMessage());
        }

        $response = $next($request);
        return $response;
    }
}
