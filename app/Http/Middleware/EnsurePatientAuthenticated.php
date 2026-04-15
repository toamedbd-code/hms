<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePatientAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->guard('patient')->check()) {
            return redirect()->route('backend.patient.portal.login');
        }

        return $next($request);
    }
}
