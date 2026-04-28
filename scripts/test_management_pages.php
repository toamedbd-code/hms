<?php
// Usage: php scripts/test_management_pages.php
// Visits each Menu route and reports HTTP status codes.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Admin;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

$admin = Admin::where('email', 'admin@gmail.com')->first();
if (! $admin) {
    echo "Admin user not found; creating temporary admin (admin@gmail.com / asdasd)\n";
    $admin = Admin::create([
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@gmail.com',
        'password' => 'asdasd',
        'status' => 'Active',
    ]);
}

// login the admin for this script's runtime so AdminAuth middleware sees it
Auth::guard('admin')->setUser($admin);
Auth::shouldUse('admin');

$menuRoutes = Menu::whereNotNull('route')->pluck('route')->unique()->filter()->values()->toArray();

$results = [];
foreach ($menuRoutes as $routeName) {
    $routeName = trim((string) $routeName);
    if ($routeName === '') continue;

    // Try to resolve the most likely registered route name. Menu route
    // descriptors sometimes omit or include the `backend.` prefix while
    // RouteServiceProvider globally prefixes names with `backend.`.
    $resolvedName = null;
    if (Route::has($routeName)) {
        $resolvedName = $routeName;
    } else {
        $strip = $routeName;
        if (str_starts_with($strip, 'backend.')) {
            $strip = substr($strip, strlen('backend.'));
        }
        $candidate = 'backend.' . $strip;
        if (Route::has($candidate)) {
            $resolvedName = $candidate;
        }
    }

    if (! $resolvedName) {
        $results[$routeName] = ['status' => 'missing'];
        continue;
    }

    try {
        $url = route($resolvedName);
    } catch (\Throwable $e) {
        $results[$routeName] = ['status' => 'url-error', 'message' => $e->getMessage()];
        continue;
    }

    try {
        $request = Illuminate\Http\Request::create($url, 'GET');
        // ensure the user is available via request resolver too
        $request->setUserResolver(function () use ($admin) { return $admin; });
        $response = $app->handle($request);
        $code = $response->getStatusCode();
        $results[$routeName] = ['status' => 'ok', 'code' => $code, 'url' => $url];
    } catch (\Throwable $e) {
        $results[$routeName] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// Print summary
foreach ($results as $name => $info) {
    if ($info['status'] === 'ok') {
        echo "[OK] {$name} -> {$info['code']} ({$info['url']})\n";
    } elseif ($info['status'] === 'missing') {
        echo "[MISSING] {$name}\n";
    } elseif ($info['status'] === 'url-error') {
        echo "[URL ERROR] {$name} -> {$info['message']}\n";
    } else {
        echo "[ERROR] {$name} -> {$info['message']}\n";
    }
}

echo "\nDone.\n";
