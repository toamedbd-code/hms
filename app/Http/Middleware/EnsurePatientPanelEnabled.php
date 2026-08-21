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
        $hasQrAccess = $request->session()->get('patient_portal_qr_access', false);

        if ($isExplicitlyDisabled && !$hasQrAccess) {
            abort(403, 'Patient portal is disabled by administrator.');
        }

        return $next($request);
    }
}
