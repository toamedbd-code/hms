<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePatientPanelEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $settings = get_cached_web_setting();

        $rawPatientPanel = $settings ? $settings->getRawOriginal('patient_panel') : null;
        $isExplicitlyDisabled = $rawPatientPanel !== null && (int) $rawPatientPanel === 0;

        if ($isExplicitlyDisabled) {
            abort(403, 'Patient portal is disabled by administrator.');
        }

        return $next($request);
    }
}
