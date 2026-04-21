<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, $moduleSlug = null)
    {
        $user = auth()->guard('admin')->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        try {
            // If no specific module slug provided, allow the request to continue
            if (empty($moduleSlug)) {
                return $next($request);
            }

            // Check whether the logged-in admin has the module assigned
            $has = $user->modules()->where('slug', $moduleSlug)->exists();

            if ($has) {
                return $next($request);
            }

            // Otherwise forbid access
            abort(403);
        } catch (\Throwable $e) {
            abort(403);
        }
    }
}
