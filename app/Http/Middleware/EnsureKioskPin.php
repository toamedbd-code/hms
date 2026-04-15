<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureKioskPin
{
    public function handle(Request $request, Closure $next)
    {
        // If kiosk mode is not enabled, hide endpoints.
        if (!config('attendance.kiosk.enabled')) {
            abort(404);
        }

        // Allow GET so the kiosk page can load and then submit PIN via AJAX.
        if ($request->isMethod('get')) {
            return $next($request);
        }

        $expectedPin = (string) config('attendance.kiosk.pin', '');

        // If PIN not set, treat as disabled/invalid config.
        if (trim($expectedPin) === '') {
            return $this->deny($request, 'Kiosk PIN not configured');
        }

        $incomingPin = (string) ($request->header('X-Kiosk-Pin') ?? $request->input('pin', ''));

        if (!hash_equals($expectedPin, $incomingPin)) {
            return $this->deny($request, 'Invalid kiosk PIN');
        }

        return $next($request);
    }

    private function deny(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        abort(403, $message);
    }
}
