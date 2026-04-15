<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Duty Roster Print</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111; }
        .header { text-align: center; margin-bottom: 16px; }
        .period { font-size: 14px; color: #444; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .date-row { background: #fafafa; font-weight: 600; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h2>Duty Roster</h2>
        <div class="period">Period: {{ \Carbon\Carbon::parse($start)->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($end)->format('d-m-Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:15%">Date</th>
                <th style="width:10%">ID</th>
                <th style="width:30%">Staff</th>
                <th style="width:15%">Shift</th>
                <th style="width:15%">Start</th>
                <th style="width:15%">End</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $date => $group)
                @foreach($group as $row)
                    <tr>
                        <td>{{ $date }}</td>
                        <td>{{ $row->staff_id ?? $row->staff?->id ?? 'N/A' }}</td>
                        <td>{{ $row->staff?->name ?? 'N/A' }}</td>
                        <td>{{ $row->shift_name }}</td>
                        <td>{{ $row->start_time }}</td>
                        <td>{{ $row->end_time }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="6">No roster entries for selected period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:20px" class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>
</body>
</html>