<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Bill List</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 20px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .meta {
            margin-bottom: 14px;
            color: #4b5563;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-size: 11px;
            text-transform: uppercase;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Pharmacy Bill List</h1>
    <div class="meta">
        Generated: {{ $generated_at }} | Total: {{ count($rows) }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Bill No</th>
                <th>Date</th>
                <th>Patient</th>
                <th>Sub Total</th>
                <th>Discount</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Due</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row['bill_no'] ?? '' }}</td>
                    <td>{{ $row['date'] ?? '' }}</td>
                    <td>{{ $row['patient'] ?? '' }}</td>
                    <td class="text-right">{{ $row['total'] ?? '0.00' }}</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">{{ $row['total'] ?? '0.00' }}</td>
                    <td class="text-right">{{ $row['paid'] ?? '0.00' }}</td>
                    <td class="text-right">{{ $row['due'] ?? '0.00' }}</td>
                    <td>{{ $row['status'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center;">No data found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
