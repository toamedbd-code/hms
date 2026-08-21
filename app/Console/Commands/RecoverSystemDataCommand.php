<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecoverSystemDataCommand extends Command
{
    protected $signature = 'system:recover';
    protected $description = 'Recover missing sidebar menus, permissions, and related app data.';

    public function handle(): int
    {
        $this->info('Starting system recovery...');

        $this->info('1/6 Re-seeding menu definitions...');
        foreach ([
            'MenuSeeder',
            'SettingsMenuSyncSeeder',
            'QuickAccessMenuPermissionSyncSeeder',
            'StoreManagementMenuSeeder',
            'SalarySheetMenuSeeder',
            'MenuDeduplicateSeeder',
        ] as $class) {
            try {
                Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
                $this->line("  - {$class}: ok");
            } catch (\Throwable $e) {
                $this->warn("  - {$class}: skipped ({$e->getMessage()})");
            }
        }

        $this->info('2/6 Re-seeding permissions...');
        foreach ([
            'PermissionSeeder',
            'PermissionFixSeeder',
            'MenuPermissionCoverageSeeder',
            'ReportManagementPermissionSeeder',
            'DutyRosterPermissionSeeder',
            'SalarySheetPermissionSeeder',
            'SidebarPermissionStabilitySeeder',
        ] as $class) {
            try {
                Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
                $this->line("  - {$class}: ok");
            } catch (\Throwable $e) {
                $this->warn("  - {$class}: skipped ({$e->getMessage()})");
            }
        }

        $this->info('3/6 Refreshing caches...');
        Artisan::call('cache:sidebar-refresh');
        Artisan::call('optimize:clear');

        $this->info('4/6 Checking critical tables...');
        $tables = ['admins', 'roles', 'permissions', 'menus'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $this->line("  - {$table}: exists ({$this->countRows($table)})");
            } else {
                $this->warn("  - {$table}: missing");
            }
        }

        $this->info('5/6 Restoring developer bootstrap...');
        try {
            $admin = \App\Support\DefaultDeveloperManager::ensure();
            if ($admin) {
                $this->line('  - Developer account restored: ' . $admin->email);
            } else {
                $this->warn('  - Developer account could not be restored');
            }
        } catch (\Throwable $e) {
            $this->warn('  - Developer bootstrap failed: ' . $e->getMessage());
        }

        $this->info('6/6 Recovery complete.');
        return self::SUCCESS;
    }

    private function countRows(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
