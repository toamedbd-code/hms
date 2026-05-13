<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class RefreshWebSettingAfterLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        try {
            if (function_exists('get_cached_web_setting')) {
                // Force refresh cached web setting so subsequent requests see latest values
                get_cached_web_setting(true);
            } else {
                \Cache::forget('web_setting.active_or_latest');
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to refresh websetting cache on login: ' . $e->getMessage());
        }

        try {
            // Ensure any session-based fallback does not carry stale company info
            session()->forget('companyInfo');
        } catch (\Throwable $e) {
            Log::warning('Failed to forget companyInfo session on login: ' . $e->getMessage());
        }
    }
}
