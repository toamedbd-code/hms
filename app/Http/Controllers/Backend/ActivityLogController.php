<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    private function formatLogDateTime($dateTime, string $format = 'Y-m-d h:i A'): string
    {
        if (!$dateTime) {
            return '-';
        }

        return $dateTime->copy()->timezone(config('app.timezone'))->format($format);
    }

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:activity-log-view', ['only' => ['index', 'show', 'userSummary', 'moduleSummary', 'export', 'print']]);
    }

    /**
     * Display activity logs
     */
    public function index(Request $request)
    {
        $filters = $request->only(['module', 'action', 'user_id', 'date_from', 'date_to', 'status']);

        $query = ActivityLog::query();

        // Filter by module
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in description
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $activityLogs = $query->with('admin')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 25))
            ->withQueryString();

        $activityLogs->getCollection()->transform(function ($log) {
            $log->created_at_local = $this->formatLogDateTime($log->created_at);
            return $log;
        });

        // Get unique modules for filter
        $modules = ActivityLog::distinct('module')->pluck('module')->sort();
        
        // Get unique actions for filter
        $actions = ActivityLog::distinct('action')->pluck('action')->sort();

        return Inertia::render('Backend/ActivityLog/Index', [
            'pageTitle' => 'Activity Logs',
            'activityLogs' => $activityLogs,
            'modules' => $modules,
            'actions' => $actions,
            'filters' => $filters
        ]);
    }

    /**
     * Show activity log detail
     */
    public function show(ActivityLog $activityLog)
    {
        $activityLog->created_at_local = $this->formatLogDateTime($activityLog->created_at);

        return Inertia::render('Backend/ActivityLog/Show', [
            'pageTitle' => 'Activity Log Detail',
            'activityLog' => $activityLog->load('admin')
        ]);
    }

    /**
     * Get user activity summary
     */
    public function userSummary(Request $request)
    {
        $userId = $request->get('user_id');
        
        $summary = ActivityLog::where('user_id', $userId)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->get();

        $recentActivities = ActivityLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => $summary,
            'recentActivities' => $recentActivities
        ]);
    }

    /**
     * Get module activity summary
     */
    public function moduleSummary(Request $request)
    {
        $module = $request->get('module');
        
        $summary = ActivityLog::where('module', $module)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->get();

        $recentActivities = ActivityLog::where('module', $module)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => $summary,
            'recentActivities' => $recentActivities
        ]);
    }

    /**
     * Export activity logs
     */
    public function export(Request $request)
    {
        $query = ActivityLog::query();

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->with('admin')->orderBy('created_at', 'desc')->get();

        $csvHeaders = [
            'Date',
            'Time',
            'User',
            'Module',
            'Action',
            'Description',
            'Login Duration',
            'IP Address',
            'Status'
        ];

        $filename = 'activity_logs_' . date('YmdHis') . '.csv';

        $handle = fopen('php://memory', 'w');
        fputcsv($handle, $csvHeaders);

        foreach ($logs as $log) {
            $localDateTime = $log->created_at?->copy()->timezone(config('app.timezone'));

            fputcsv($handle, [
                $localDateTime?->format('Y-m-d') ?? '-',
                $localDateTime?->format('H:i:s') ?? '-',
                $log->user_name,
                $log->module,
                $log->action,
                $log->description,
                $log->meta['session_duration_human'] ?? '-',
                $log->ip_address,
                $log->status
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}"
        ]);
    }

    /**
     * Printable activity logs view
     */
    public function print(Request $request)
    {
        $query = ActivityLog::query();

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $limit = (int) $request->get('limit', 500);
        $limit = max(1, min($limit, 2000));

        $logs = $query->with('admin')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $logs->transform(function ($log) {
            $log->created_at_local = $this->formatLogDateTime($log->created_at);
            return $log;
        });

        return view('backend.activity-logs.print', [
            'logs' => $logs,
            'filters' => $request->all(),
            'printedAt' => now(),
        ]);
    }

    /**
     * Delete old logs (retention policy)
     */
    public function deleteOldLogs(Request $request)
    {
        $daysOld = $request->get('days', 90);
        
        $date = now()->subDays($daysOld);
        
        $deletedCount = ActivityLog::where('created_at', '<', $date)->delete();

        return response()->json([
            'message' => "Deleted {$deletedCount} old activity logs",
            'count' => $deletedCount
        ]);
    }
}