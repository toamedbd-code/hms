<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = App\Models\Admin::where('email','toamedbd@gmail.com')->first();
if (!$user) { echo "user not found\n"; exit(1); }
$strictFiltering = false;
$dashboardRoute = 'backend.dashboard';
$routeExists = \Illuminate\Support\Facades\Route::has($dashboardRoute);
echo "Route exists: ".($routeExists ? 'yes' : 'no')."\n";
$canSeeDashboard = false;
try { if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('dashboard')) { $canSeeDashboard = true; }} catch (\Throwable $e) { echo "permission check failed: {$e->getMessage()}\n"; }
echo "hasPermissionTo dashboard: ".($canSeeDashboard ? 'yes' : 'no')."\n";
try { if (!$canSeeDashboard && method_exists($user,'hasRole') && $user->hasRole('developer')) { $canSeeDashboard = true; }} catch (\Throwable $e) { echo "role check failed: {$e->getMessage()}\n"; }
echo "developer role fallback: ".($canSeeDashboard ? 'yes' : 'no')."\n";
$result = collect([['route'=>'backend.sitepurchase.index'], ['route'=>'backend.accounts.vendor-payment.index'], ['route'=>'backend.cash-counter.index'], ['route'=>null], ['route'=>'backend.billing.Page']]);
$contains = $result->contains(function ($menu) use ($dashboardRoute) { $route = trim((string) ($menu['route'] ?? '')); return strcasecmp($route, $dashboardRoute) === 0; });
echo "contains dashboard? ".($contains ? 'yes' : 'no')."\n";
