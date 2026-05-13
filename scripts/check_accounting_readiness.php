<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Admin;

$email = $argv[1] ?? 'toamedbd@gmail.com';

$admin = Admin::where('email', $email)->first();
$permissions = [];
$sideMenuNames = [];

if ($admin) {
    try {
        $permissions = $admin->getAllPermissions()->pluck('name')->toArray();
    } catch (Throwable $e) {
        $permissions = [];
    }

    try {
        $sideMenus = getSideMenus($admin);
        foreach ($sideMenus as $menu) {
            $sideMenuNames[] = is_array($menu) ? ($menu['name'] ?? '') : ($menu->name ?? '');
        }
    } catch (Throwable $e) {
        $sideMenuNames = [];
    }
}

$requiredPermissions = [
    'chart-of-accounts',
    'ledger',
    'account-balances',
    'currency-list',
    'exchange-rate-list',
    'journal-entry',
];

$missingPermissions = array_values(array_filter($requiredPermissions, function ($perm) use ($permissions) {
    return !in_array($perm, $permissions, true);
}));

echo "Accounting Readiness Report\n";
echo "========================\n";
echo "accounts: " . Account::count() . "\n";
echo "account_balances: " . AccountBalance::count() . "\n";
echo "currencies: " . Currency::count() . "\n";
echo "exchange_rates: " . ExchangeRate::count() . "\n";

echo "\nUser: " . $email . "\n";
echo "user_found: " . ($admin ? 'yes' : 'no') . "\n";
echo "permissions_total: " . count($permissions) . "\n";
echo "missing_accounting_permissions: " . (count($missingPermissions) ? implode(',', $missingPermissions) : 'none') . "\n";
echo "sidebar_has_account_management: " . (in_array('Account Management', $sideMenuNames, true) ? 'yes' : 'no') . "\n";
