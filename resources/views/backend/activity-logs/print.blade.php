<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs Print</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }

        .meta {
            font-size: 12px;
            color: #4b5563;
            margin-top: 4px;
        }

        .filters {
            margin: 10px 0 14px;
            font-size: 12px;
            color: #374151;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-weight: 600;
        }

        .status-success {
            color: #065f46;
            font-weight: 600;
        }

        .status-failed {
            color: #991b1b;
            font-weight: 600;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                margin: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1 class="title">Activity Logs</h1>
            <div class="meta">Printed at: {{ $printedAt->format('Y-m-d h:i A') }}</div>
            <div class="meta">Total records: {{ $logs->count() }}</div>
        </div>
        <div class="no-print">
            <button onclick="window.print()">Print</button>
        </div>
    </div>

    @php
        $activeFilters = collect($filters ?? [])->only(['module', 'action', 'status', 'date_from', 'date_to', 'search'])->filter(fn($value) => $value !== null && $value !== '');
    @endphp

    @if($activeFilters->isNotEmpty())
        <div class="filters">
            <strong>Applied Filters:</strong>
            @foreach($activeFilters as $key => $value)
                <span>{{ strtoupper(str_replace('_', ' ', $key)) }}: {{ $value }}</span>@if(!$loop->last) | @endif
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 120px;">Date Time</th>
                <th style="width: 120px;">User</th>
                <th style="width: 130px;">Module</th>
                <th style="width: 80px;">Action</th>
                <th>Description</th>
                <th style="width: 110px;">Login Duration</th>
                <th style="width: 90px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at_local ?? optional($log->created_at)->timezone(config('app.timezone'))->format('Y-m-d h:i A') }}</td>
                    <td>{{ $log->user_name ?? 'System' }}</td>
                    <td>{{ $log->module }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->description }}</td>
                    <td>{{ data_get($log->meta, 'session_duration_human', '-') }}</td>
                    <td class="{{ $log->status === 'failed' ? 'status-failed' : 'status-success' }}">{{ strtoupper($log->status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">No logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
