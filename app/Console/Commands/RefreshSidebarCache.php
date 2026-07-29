<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshSidebarCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:sidebar-refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear sidebar menu cache keys to force refresh on next request';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Refreshing sidebar menu cache...');

        try {
            // Explicitly clear the menu schema version cache so the next
            // sidebar request regenerates a fresh cache entry.
            Cache::forget('sidebar.menu_schema_version');
            $this->line('✓ Cleared menu schema version cache');
        } catch (\Throwable $e) {
            $this->warn('⚠ Could not clear schema cache: ' . $e->getMessage());
        }

        $this->info('✅ Sidebar cache refresh complete!');
    }
}
