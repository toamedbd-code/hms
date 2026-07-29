<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Ensure `app.key` is available to all runtime processes. Some environments
        // may start long-running workers or PHP-FPM instances before `.env` is
        // available which causes MissingAppKeyException intermittently. Try to
        // read APP_KEY from the environment, the `.env` file, or a cached file;
        // if none exists generate and persist a key for consistent behaviour.
        try {
            if (empty(config('app.key'))) {
                $key = env('APP_KEY');

                // Try reading from .env file directly if env() didn't return a value
                if (empty($key)) {
                    $envPath = base_path('.env');
                    if (file_exists($envPath)) {
                        $contents = file_get_contents($envPath);
                        if (preg_match('/^APP_KEY=(.+)$/m', $contents, $m)) {
                            $key = trim($m[1]);
                            $key = trim($key, "\"'");
                        }
                    }
                }

                // Use cached key file if present
                if (empty($key)) {
                    $cacheKeyFile = storage_path('app/.app_key');
                    if (file_exists($cacheKeyFile)) {
                        $key = trim(file_get_contents($cacheKeyFile));
                    }
                }

                // Generate and persist a key if still missing
                if (empty($key)) {
                    try {
                        $key = 'base64:' . base64_encode(random_bytes(32));
                    } catch (\Throwable $ex) {
                        $key = null;
                    }

                    if (!empty($key)) {
                        try {
                            $cacheKeyFile = storage_path('app/.app_key');
                            if (!is_dir(dirname($cacheKeyFile))) {
                                @mkdir(dirname($cacheKeyFile), 0755, true);
                            }
                            @file_put_contents($cacheKeyFile, $key, LOCK_EX);

                            // If .env is writable and doesn't contain APP_KEY, append it
                            $envPath = base_path('.env');
                            if (file_exists($envPath) && is_writable($envPath)) {
                                $envContents = file_get_contents($envPath);
                                if (strpos($envContents, 'APP_KEY=') === false) {
                                    @file_put_contents($envPath, PHP_EOL . 'APP_KEY=' . $key . PHP_EOL, FILE_APPEND | LOCK_EX);
                                }
                            }
                        } catch (\Throwable $ignored) {
                        }
                    }
                }

                if (!empty($key)) {
                    config(['app.key' => $key]);
                    $_ENV['APP_KEY'] = $key;
                    putenv("APP_KEY={$key}");
                    Log::info('AppServiceProvider: ensured APP_KEY is set at runtime');
                } else {
                    Log::warning('AppServiceProvider: APP_KEY missing and could not be generated');
                }
            }
        } catch (\Throwable $e) {
            // Avoid breaking bootstrap if anything goes wrong here; missing key
            // will still surface as an exception but we try best-effort fix above.
        }

        // Ensure database connection config is available early in the
        // bootstrap process. Some environments may not expose env vars to
        // the running PHP process in time; try reading .env and enforce
        // DB config into runtime `config()` so middleware/auth can use it.
        try {
            $dbHost = env('DB_HOST');
            $dbPort = env('DB_PORT');
            $dbName = env('DB_DATABASE');
            $dbUser = env('DB_USERNAME');
            $dbPass = env('DB_PASSWORD');
            $cacheDriver = env('CACHE_DRIVER', config('cache.default', 'file'));

            $envPath = base_path('.env');
            if (file_exists($envPath)) {
                $contents = @file_get_contents($envPath);
                if (is_string($contents) && $contents !== '') {
                    if (preg_match('/^DB_HOST=(.+)$/m', $contents, $m)) { $dbHost = trim(trim($m[1]), "\"'"); }
                    if (preg_match('/^DB_PORT=(.+)$/m', $contents, $m)) { $dbPort = trim(trim($m[1]), "\"'"); }
                    if (preg_match('/^DB_DATABASE=(.+)$/m', $contents, $m)) { $dbName = trim(trim($m[1]), "\"'"); }
                    if (preg_match('/^DB_USERNAME=(.+)$/m', $contents, $m)) { $dbUser = trim(trim($m[1]), "\"'"); }
                    if (preg_match('/^DB_PASSWORD=(.*)$/m', $contents, $m)) { $dbPass = trim(trim($m[1]), "\"'"); }
                    if (preg_match('/^CACHE_DRIVER=(.+)$/m', $contents, $m)) { $cacheDriver = trim(trim($m[1]), "\"'"); }
                }
            }

            if (!empty($dbName) && !empty($dbUser)) {
                // Capture previous effective DB config so we only log changes
                $prevHost = config('database.connections.mysql.host') ?? '';
                $prevPort = config('database.connections.mysql.port') ?? '';
                $prevDatabase = config('database.connections.mysql.database') ?? '';
                $prevUsername = config('database.connections.mysql.username') ?? '';

                config(['database.connections.mysql.host' => $dbHost ?? $prevHost]);
                config(['database.connections.mysql.port' => $dbPort ?? $prevPort]);
                config(['database.connections.mysql.database' => $dbName]);
                config(['database.connections.mysql.username' => $dbUser]);
                config(['database.connections.mysql.password' => $dbPass]);

                $_ENV['DB_HOST'] = $dbHost ?? '';
                $_ENV['DB_PORT'] = $dbPort ?? '';
                $_ENV['DB_DATABASE'] = $dbName ?? '';
                $_ENV['DB_USERNAME'] = $dbUser ?? '';
                $_ENV['DB_PASSWORD'] = $dbPass ?? '';

                @putenv("DB_HOST={$dbHost}");
                @putenv("DB_PORT={$dbPort}");
                @putenv("DB_DATABASE={$dbName}");
                @putenv("DB_USERNAME={$dbUser}");
                @putenv("DB_PASSWORD={$dbPass}");

                // Log non-sensitive DB info for diagnostics (do not log password)
                if ($prevHost !== ($dbHost ?? '') || $prevDatabase !== ($dbName ?? '') || $prevUsername !== ($dbUser ?? '')) {
                    Log::info('AppServiceProvider: enforced DB config at runtime', [
                        'db_host' => $dbHost,
                        'db_database' => $dbName,
                        'db_username' => $dbUser,
                    ]);
                } else {
                    // No effective change — log only when app debug is enabled to avoid noise
                    if (config('app.debug', false)) {
                        Log::debug('AppServiceProvider: DB config enforcement no-op (no changes)');
                    }
                }

                try {
                    if (trim(strtolower((string) $cacheDriver)) === 'database') {
                        $cacheTable = (string) config('cache.stores.database.table', 'cache');
                        $hasCacheTable = app('db')->connection()->getSchemaBuilder()->hasTable($cacheTable);

                        if (!$hasCacheTable) {
                            config(['cache.default' => 'file']);
                            $_ENV['CACHE_DRIVER'] = 'file';
                            @putenv('CACHE_DRIVER=file');

                            Log::warning('AppServiceProvider: falling back to file cache because cache table is missing', [
                                'cache_table' => $cacheTable,
                                'db_database' => $dbName,
                            ]);
                        }
                    }
                } catch (\Throwable $cacheException) {
                    // Keep cache fallback best-effort and avoid breaking bootstrap.
                }
            } else {
                Log::warning('AppServiceProvider: DB env values missing; skipping enforcement');
            }
        } catch (\Throwable $ignored) {
            // avoid breaking bootstrap
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        date_default_timezone_set((string) config('app.timezone', 'Asia/Dhaka'));

        // Ensure polymorphic morph map so `category` strings (stored in bill_items)
        // correctly resolve to the application's model classes. This avoids
        // runtime errors like "Class 'Radiology' not found" when Eloquent
        // attempts to instantiate a related model by type name.
        try {
            Relation::morphMap([
                'Radiology' => \App\Models\RadiologyTest::class,
                'Ultrasonogram' => \App\Models\RadiologyTest::class,
                'Ultrasonography' => \App\Models\RadiologyTest::class,
                'Pathology' => \App\Models\Test::class,
            ], false);
        } catch (\Throwable $_) {
            // ignore failures during early bootstrap
        }

        $logsPath = storage_path('logs');
        $logFile = $logsPath . DIRECTORY_SEPARATOR . 'laravel.log';
        $permissionPaths = [
            storage_path(),
            base_path('bootstrap/cache'),
        ];

        try {
            if (!is_dir($logsPath)) {
                mkdir($logsPath, 0775, true);
            }

            if (!file_exists($logFile)) {
                touch($logFile);
            }

            foreach ($permissionPaths as $permissionPath) {
                if (is_dir($permissionPath)) {
                    @chmod($permissionPath, 0775);
                }
            }

            if (file_exists($logFile)) {
                @chmod($logFile, 0664);
            }
        } catch (\Throwable $exception) {
            // Avoid breaking the request cycle if permissions are locked down.
        }

        // Sidebar menu props are shared by HandleInertiaRequests middleware.
        // Avoid duplicate auth.sideMenus sharing here to prevent stale/empty
        // sidebar payloads on hard page refresh.
        
        // Register observers for automatic cache invalidation
        $this->registerModelObservers();
        
        $this->registerCrudActivityLogging();

    }

    private function registerModelObservers(): void
    {
        try {
            \App\Models\Menu::observe(\App\Observers\MenuObserver::class);
        } catch (\Throwable $e) {
            // ignore if observer not yet available
        }
    }

    private function registerCrudActivityLogging(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        Model::created(function (Model $model) {
            $this->logCrudActivity('CREATE', $model);
        });

        Model::updated(function (Model $model) {
            $this->logCrudActivity('UPDATE', $model);
        });

        Model::deleted(function (Model $model) {
            $this->logCrudActivity('DELETE', $model);
        });
    }

    private function logCrudActivity(string $action, Model $model): void
    {
        if (!$this->shouldLogCrudActivity($model)) {
            return;
        }

        try {
            $module = class_basename($model);
            $recordId = $model->getKey() ?? 'N/A';
            $recordName = $this->resolveRecordName($model);

            if ($action === 'CREATE') {
                ActivityLogService::logCreate($module, $recordId, $recordName, [
                    'attributes' => $model->getAttributes(),
                ]);
                return;
            }

            if ($action === 'UPDATE') {
                $changes = $model->getChanges();
                unset($changes['updated_at']);

                if (empty($changes)) {
                    return;
                }

                // Many modules use soft-delete style updates (set deleted_at + status)
                // instead of calling Eloquent delete(). Treat those as DELETE logs.
                if (array_key_exists('deleted_at', $changes) && !empty($changes['deleted_at'])) {
                    ActivityLogService::logDelete($module, $recordId, $recordName, [
                        'attributes' => $model->getAttributes(),
                    ]);
                    return;
                }

                $oldData = [];
                foreach (array_keys($changes) as $field) {
                    $oldData[$field] = $model->getOriginal($field);
                }

                ActivityLogService::logUpdate($module, $recordId, $recordName, $changes, $oldData);
                return;
            }

            if ($action === 'DELETE') {
                ActivityLogService::logDelete($module, $recordId, $recordName, [
                    'attributes' => $model->getOriginal(),
                ]);
            }
        } catch (\Throwable $exception) {
            // Do not break user action if logging fails.
        }
    }

    private function shouldLogCrudActivity(Model $model): bool
    {
        if (!auth('admin')->check()) {
            return false;
        }

        if ($model instanceof Pivot) {
            return false;
        }

        $excludedModels = [
            ActivityLog::class,
            \App\Models\SystemLog::class,
            \App\Models\SystemErrorLog::class,
        ];

        return !in_array($model::class, $excludedModels, true);
    }

    private function resolveRecordName(Model $model): string
    {
        $nameCandidates = ['name', 'title', 'bill_no', 'invoice_no', 'code', 'email', 'phone', 'id'];

        foreach ($nameCandidates as $field) {
            $value = $model->getAttribute($field);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return class_basename($model) . '#' . (string) $model->getKey();
    }
}
