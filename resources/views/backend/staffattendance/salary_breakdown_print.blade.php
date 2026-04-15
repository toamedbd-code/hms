<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $pageTitle ?? 'Attendance Breakdown' }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
            margin: 20px;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .meta {
            margin-bottom: 14px;
            color: #374151;
        }
        .meta div {
            margin-bottom: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
        }
        th {
            background: #f3f4f6;
            font-weight: 700;
        }
        .text-right {
            text-align: right;
        }
        tfoot td {
            background: #f9fafb;
            font-weight: 700;
        }
    </style>
</head>
<body>
    @php
        $breakdowns = collect($row['attendance_breakdown'] ?? []);
        $minutesToHms = function ($value) {
            $numeric = is_numeric($value) ? (float) $value : 0;
            $totalSeconds = max((int) round($numeric * 60), 0);
            $hours = intdiv($totalSeconds, 3600);
            $minutes = intdiv($totalSeconds % 3600, 60);
            $seconds = $totalSeconds % 60;

            return str_pad((string) $hours, 2, '0', STR_PAD_LEFT)
                . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT)
                . ':' . str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);
        };
        $money = fn($value) => number_format((float) ($value ?? 0), 2);
    @endphp

    <div class="title">Attendance Breakdown</div>
    <div class="meta">
        <div><strong>Company:</strong> {{ $websetting?->company_name ?? config('app.name', 'Hospital') }}</div>
        <div><strong>Month:</strong> {{ $monthLabel ?? $monthInput ?? 'N/A' }}</div>
        <div><strong>Staff:</strong> {{ $row['name'] ?? 'N/A' }} ({{ $row['staff_id'] ?? 'N/A' }})</div>
        <div><strong>Generated At:</strong> {{ optional($generatedAt)->format('Y-m-d h:i A') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>IN</th>
                <th>OUT</th>
                <th class="text-right">Duration (Min)</th>
                <th class="text-right">Late (HH:MM:SS)</th>
                <th class="text-right">OT (HH:MM:SS)</th>
                <th class="text-right">Deduction</th>
                <th class="text-right">Overtime</th>
            </tr>
        </thead>
        <tbody>
            @forelse($breakdowns as $item)
                <tr>
                    <td>{{ $item['date'] ?? '-' }}</td>
                    <td>{{ $item['in_time'] ?? '-' }}</td>
                    <td>{{ $item['out_time'] ?? '-' }}</td>
                    <td class="text-right">{{ (int) ($item['duration_minutes'] ?? 0) }}</td>
                    <td class="text-right">{{ $minutesToHms($item['late_minutes'] ?? 0) }}</td>
                    <td class="text-right">{{ $minutesToHms($item['overtime_minutes'] ?? 0) }}</td>
                    <td class="text-right">{{ $money($item['deduction_amount'] ?? 0) }}</td>
                    <td class="text-right">{{ $money($item['overtime_amount'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:#6b7280;">No attendance breakdown available for this month.</td>
                </tr>
            @endforelse
        </tbody>
        @if($breakdowns->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="3">Total</td>
                    <td class="text-right">{{ (int) ($totals['duration_minutes'] ?? 0) }}</td>
                    <td class="text-right">{{ $minutesToHms($totals['late_minutes'] ?? 0) }}</td>
                    <td class="text-right">{{ $minutesToHms($totals['overtime_minutes'] ?? 0) }}</td>
                    <td class="text-right">{{ $money($totals['deduction_amount'] ?? 0) }}</td>
                    <td class="text-right">{{ $money($totals['overtime_amount'] ?? 0) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if(!empty($autoPrint))
        <script>
            window.onload = function () {
                window.print();
            };
        </script>
    @endif
</body>
</html>
