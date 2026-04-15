<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
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
        //$viewShare = [];
        //if (auth()->guard('admin')->check() && auth()->guard('admin')->user()->status == 'Active') {
        //    $sideMenus = (session()->has('sideMenus')) ? session()->get('sideMenus') : getSideMenus();
        //    $viewShare['sideMenus'] = $sideMenus;
        //}
        //$sideMenus = (session()->has('sideMenus')) ? session()->get('sideMenus') : getSideMenus();
        //$viewShare['sideMenus'] = $sideMenus;
        //$companyInfo = (session()->has('companyInfo')) ? session()->get('companyInfo') : Company::first();
        //$viewShare['companyInfo'] = $companyInfo;

        //Inertia::share($viewShare);
        $this->registerCrudActivityLogging();

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
