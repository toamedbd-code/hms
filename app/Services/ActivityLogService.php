<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;

class ActivityLogService
{
    /**
     * Log an activity
     */
    public static function log(
        string $module,
        string $action,
        string $description = '',
        array $meta = [],
        string $status = 'success',
        ?int $userId = null
    ): ActivityLog {
        $user = auth('admin')->user() ?? auth()->user();
        $enrichedMeta = self::enrichSessionMeta($meta);
        
        $activityLog = ActivityLog::create([
            'user_id' => $userId ?? $user?->id,
            'user_name' => $user?->name ?? $user?->email ?? 'System',
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
            'meta' => !empty($enrichedMeta) ? $enrichedMeta : null,
            'status' => $status
        ]);

        return $activityLog;
    }

    /**
     * Log a creation action
     */
    public static function logCreate(
        string $module,
        int|string $recordId,
        string $recordName,
        array $data = []
    ): ActivityLog {
        return self::log(
            $module,
            'CREATE',
            "Created {$module}: {$recordName} (ID: {$recordId})",
            array_merge($data, ['record_id' => $recordId, 'record_name' => $recordName])
        );
    }

    /**
     * Log an update action
     */
    public static function logUpdate(
        string $module,
        int|string $recordId,
        string $recordName,
        array $changes = [],
        array $oldData = []
    ): ActivityLog {
        return self::log(
            $module,
            'UPDATE',
            "Updated {$module}: {$recordName} (ID: {$recordId})",
            [
                'record_id' => $recordId,
                'record_name' => $recordName,
                'changes' => $changes,
                'old_data' => $oldData
            ]
        );
    }

    /**
     * Log a delete action
     */
    public static function logDelete(
        string $module,
        int|string $recordId,
        string $recordName,
        array $deletedData = []
    ): ActivityLog {
        return self::log(
            $module,
            'DELETE',
            "Deleted {$module}: {$recordName} (ID: {$recordId})",
            array_merge($deletedData, ['record_id' => $recordId, 'record_name' => $recordName])
        );
    }

    /**
     * Log a view/read action
     */
    public static function logView(
        string $module,
        int $recordId,
        string $recordName
    ): ActivityLog {
        return self::log(
            $module,
            'VIEW',
            "Viewed {$module}: {$recordName} (ID: {$recordId})",
            ['record_id' => $recordId, 'record_name' => $recordName]
        );
    }

    /**
     * Log a download action
     */
    public static function logDownload(
        string $module,
        string $fileName,
        string $fileType = 'PDF'
    ): ActivityLog {
        return self::log(
            $module,
            'DOWNLOAD',
            "Downloaded {$fileType}: {$fileName}",
            ['file_name' => $fileName, 'file_type' => $fileType]
        );
    }

    /**
     * Log login activity
     */
    public static function logLogin(string $email, ?string $loginAt = null): ActivityLog
    {
        $startedAt = $loginAt ?: now()->toDateTimeString();

        return self::log(
            'AUTH',
            'LOGIN',
            "Admin logged in: {$email}",
            [
                'email' => $email,
                'session_started_at' => $startedAt,
                'session_duration_seconds' => 0,
                'session_duration_human' => '0m 0s',
            ]
        );
    }

    /**
     * Log logout activity
     */
    public static function logLogout(string $userName, ?string $loginAt = null): ActivityLog
    {
        $seconds = self::calculateSessionDurationSeconds($loginAt);

        return self::log(
            'AUTH',
            'LOGOUT',
            "Admin logged out: {$userName}",
            [
                'user_name' => $userName,
                'session_started_at' => $loginAt,
                'session_duration_seconds' => $seconds,
                'session_duration_human' => self::humanizeDuration($seconds),
            ]
        );
    }

    private static function enrichSessionMeta(array $meta): array
    {
        $sessionKey = 'admin_login_started_at';
        $startedAt = null;

        try {
            if (function_exists('session') && session()->has($sessionKey)) {
                $startedAt = (string) session($sessionKey);
            }
        } catch (\Throwable $exception) {
            $startedAt = null;
        }

        if (!$startedAt) {
            return $meta;
        }

        $seconds = self::calculateSessionDurationSeconds($startedAt);

        return array_merge([
            'session_started_at' => $startedAt,
            'session_duration_seconds' => $seconds,
            'session_duration_human' => self::humanizeDuration($seconds),
        ], $meta);
    }

    private static function calculateSessionDurationSeconds(?string $startedAt): int
    {
        if (!$startedAt) {
            return 0;
        }

        try {
            $start = Carbon::parse($startedAt);
            return max(0, $start->diffInSeconds(now()));
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private static function humanizeDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm ' . $remainingSeconds . 's';
        }

        return $minutes . 'm ' . $remainingSeconds . 's';
    }

    /**
     * Log failed action
     */
    public static function logFailed(
        string $module,
        string $action,
        string $errorMessage,
        array $meta = []
    ): ActivityLog {
        return self::log(
            $module,
            $action,
            "FAILED: {$errorMessage}",
            $meta,
            'failed'
        );
    }

    /**
     * Get activity logs with filters
     */
    public static function getActivityLogs(
        ?string $module = null,
        ?string $action = null,
        ?int $userId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 20
    ) {
        $query = ActivityLog::query();

        if ($module) {
            $query->where('module', $module);
        }

        if ($action) {
            $query->where('action', $action);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get user activity summary
     */
    public static function getUserActivitySummary(int $userId)
    {
        return ActivityLog::where('user_id', $userId)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->get();
    }

    /**
     * Get module activity summary
     */
    public static function getModuleActivitySummary(string $module)
    {
        return ActivityLog::where('module', $module)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->get();
    }

    /**
     * Get recent activities
     */
    public static function getRecentActivities(int $limit = 10)
    {
        return ActivityLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}