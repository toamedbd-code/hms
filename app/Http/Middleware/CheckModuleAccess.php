<?php

namespace App\Http\Middleware;

use App\Support\DefaultDeveloperManager;
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

        $moduleSlug = strtolower(trim((string) $moduleSlug));
        if ($moduleSlug === '') {
            return $next($request);
        }

        if (DefaultDeveloperManager::isDeveloper($user)) {
            return $next($request);
        }

        try {
            if (function_exists('user_has_module') && user_has_module($user, $moduleSlug)) {
                return $next($request);
            }

            $assigned = function_exists('user_assigned_module_slugs')
                ? user_assigned_module_slugs($user)
                : collect();

            if ($assigned->isEmpty()) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            return $next($request);
        }

        abort(403, 'This module is not assigned.');
    }
}
