<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    // Ensure Account Management > Journal Entries
    $accountParent = Menu::query()
        ->whereNull('parent_id')
        ->where(function ($query) {
            $query->where('name', 'Account Management')
                ->orWhere('permission_name', 'account-management');
        })
        ->first();

    if ($accountParent) {
        $accountPermission = Permission::query()
            ->where('guard_name', 'admin')
            ->where('name', 'account-management')
            ->first();

        $journalPermission = Permission::query()->firstOrCreate(
            ['name' => 'journal-entry', 'guard_name' => 'admin'],
            ['parent_id' => null, 'sorting' => 1]
        );

        if ($accountPermission && (int) ($journalPermission->parent_id ?? 0) !== (int) $accountPermission->id) {
            $journalPermission->parent_id = $accountPermission->id;
            $journalPermission->save();
        }

        $journal = Menu::query()->whereIn('route', ['journal-entry.index', 'backend.journal-entry.index'])->first();
        if (!$journal) {
            $journal = new Menu();
            $journal->sorting = (int) (Menu::query()->where('parent_id', $accountParent->id)->max('sorting') ?? 0) + 1;
            echo "[CREATED] Account Management > Journal Entries" . PHP_EOL;
        } else {
            echo "[UPDATED] Account Management > Journal Entries" . PHP_EOL;
        }

        $journal->parent_id = $accountParent->id;
        $journal->name = 'Journal Entries';
        $journal->icon = 'book';
        $journal->route = 'backend.journal-entry.index';
        $journal->permission_name = 'journal-entry';
        $journal->status = 'Active';
        $journal->deleted_at = null;
        if (empty($journal->sorting)) {
            $journal->sorting = 10;
        }
        $journal->save();
    } else {
        echo "[SKIPPED] Account Management parent not found" . PHP_EOL;
    }

    // Ensure Store Management has Stock Management (rename from Store Dashboard when present)
    $storeParent = Menu::query()
        ->whereNull('parent_id')
        ->where(function ($query) {
            $query->where('name', 'Store Management')
                ->orWhere('permission_name', 'store-management');
        })
        ->first();

    if ($storeParent) {
        Permission::query()->firstOrCreate(
            ['name' => 'stock-management', 'guard_name' => 'admin'],
            ['parent_id' => null, 'sorting' => 1]
        );

        $stockMenu = Menu::query()->where('route', 'backend.stock.index')->first();
        if (!$stockMenu) {
            $stockMenu = Menu::query()
                ->where('parent_id', $storeParent->id)
                ->where('name', 'Store Dashboard')
                ->first();
        }

        if (!$stockMenu) {
            $stockMenu = new Menu();
            $stockMenu->sorting = (int) (Menu::query()->where('parent_id', $storeParent->id)->max('sorting') ?? 0) + 1;
            echo "[CREATED] Store Management > Stock Management" . PHP_EOL;
        } else {
            echo "[UPDATED] Store Management > Stock Management" . PHP_EOL;
        }

        $stockMenu->parent_id = $storeParent->id;
        $stockMenu->name = 'Stock Management';
        $stockMenu->icon = 'box';
        $stockMenu->route = 'backend.stock.index';
        $stockMenu->permission_name = 'stock-management';
        $stockMenu->status = 'Active';
        $stockMenu->deleted_at = null;
        if (empty($stockMenu->sorting)) {
            $stockMenu->sorting = 2;
        }
        $stockMenu->save();
    } else {
        echo "[SKIPPED] Store Management parent not found" . PHP_EOL;
    }

    DB::commit();

    try {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    } catch (\Throwable $e) {
        // ignore cache reset failures
    }

    echo 'Done' . PHP_EOL;
} catch (Throwable $e) {
    DB::rollBack();
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
