<?php

namespace App\Observers;

use App\Models\Menu;
use Illuminate\Support\Facades\Cache;

class MenuObserver
{
    /**
     * Handle the Menu "created" event.
     */
    public function created(Menu $menu): void
    {
        $this->invalidateSidebarCache();
    }

    /**
     * Handle the Menu "updated" event.
     */
    public function updated(Menu $menu): void
    {
        $this->invalidateSidebarCache();
    }

    /**
     * Handle the Menu "deleted" event.
     */
    public function deleted(Menu $menu): void
    {
        $this->invalidateSidebarCache();
    }

    /**
     * Handle the Menu "restored" event.
     */
    public function restored(Menu $menu): void
    {
        $this->invalidateSidebarCache();
    }

    /**
     * Invalidate sidebar cache when menu changes.
     */
    private function invalidateSidebarCache(): void
    {
        try {
            Cache::forget('sidebar.menu_schema_version');
        } catch (\Throwable $e) {
            // Silently fail
        }
    }
}
