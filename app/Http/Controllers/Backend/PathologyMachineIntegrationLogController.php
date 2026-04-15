<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PathologyMachineIntegrationLog;
use App\Services\PathologyMachineIntegrationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

class PathologyMachineIntegrationLogController extends Controller
{
    public function __construct(private readonly PathologyMachineIntegrationService $integrationService)
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:activity-log-view', ['only' => ['index', 'retrySimulate']]);
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'event',
            'level',
            'source_format',
            'communication_mode',
            'search',
            'date_from',
            'date_to',
            'per_page',
        ]);

        $query = $this->applyFilters(PathologyMachineIntegrationLog::query(), $request);

        $logs = $query
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 25))
            ->withQueryString();

        $events = PathologyMachineIntegrationLog::query()->select('event')->distinct()->orderBy('event')->pluck('event')->values();
        $levels = PathologyMachineIntegrationLog::query()->select('level')->distinct()->orderBy('level')->pluck('level')->values();
        $formats = PathologyMachineIntegrationLog::query()->select('source_format')->distinct()->whereNotNull('source_format')->where('source_format', '!=', '')->orderBy('source_format')->pluck('source_format')->values();
        $communicationModes = PathologyMachineIntegrationLog::query()->select('communication_mode')->distinct()->whereNotNull('communication_mode')->where('communication_mode', '!=', '')->orderBy('communication_mode')->pluck('communication_mode')->values();

        return Inertia::render('Backend/PathologyMachineIntegrationLog/Index', [
            'pageTitle' => 'Pathology Machine Integration Logs',
            'logs' => $logs,
            'events' => $events,
            'levels' => $levels,
            'formats' => $formats,
            'communicationModes' => $communicationModes,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->applyFilters(PathologyMachineIntegrationLog::query(), $request)
            ->orderByDesc('id');

        $filename = 'pathology_machine_integration_logs_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Created At',
                'Request ID',
                'Event',
                'Level',
                'Source Format',
                'Communication Mode',
                'IP Address',
                'Message',
                'Context',
                'Raw Payload',
            ]);

            $query->chunk(500, function ($items) use ($handle) {
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $item->id,
                        (string) $item->created_at,
                        (string) ($item->request_id ?? ''),
                        (string) ($item->event ?? ''),
                        (string) ($item->level ?? ''),
                        (string) ($item->source_format ?? ''),
                        (string) ($item->communication_mode ?? ''),
                        (string) ($item->ip_address ?? ''),
                        (string) ($item->message ?? ''),
                        json_encode($item->context ?? [], JSON_UNESCAPED_UNICODE),
                        (string) ($item->raw_payload ?? ''),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function retrySimulate(PathologyMachineIntegrationLog $log)
    {
        $result = $this->integrationService->retryFromRawPayload((string) ($log->raw_payload ?? ''), [
            'source_log_id' => $log->id,
            'source_event' => $log->event,
        ]);

        return response()->json([
            'ok' => $result['ok'] ?? false,
            'message' => $result['message'] ?? '',
            'meta' => $result['meta'] ?? null,
            'request_id' => $result['request_id'] ?? null,
        ], (int) ($result['code'] ?? 200));
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('event')) {
            $query->where('event', (string) $request->string('event'));
        }

        if ($request->filled('level')) {
            $query->where('level', (string) $request->string('level'));
        }

        if ($request->filled('source_format')) {
            $query->where('source_format', (string) $request->string('source_format'));
        }

        if ($request->filled('communication_mode')) {
            $query->where('communication_mode', (string) $request->string('communication_mode'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->string('date_to'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->string('search')) . '%';
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('request_id', 'like', $search)
                    ->orWhere('message', 'like', $search)
                    ->orWhere('ip_address', 'like', $search);
            });
        }

        return $query;
    }
}
