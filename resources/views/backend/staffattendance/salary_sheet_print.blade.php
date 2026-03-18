<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Sheet - {{ $monthLabel }}</title>
    <style>
        :root {
            --text: #1f2937;
            --muted: #6b7280;
            --line: #d1d5db;
            --head-bg: #f3f4f6;
            --accent: #0f766e;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
            background: #fff;
        }

        .sheet {
            width: 100%;
            max-width: 1120px;
            margin: 20px auto;
            padding: 0 12px 16px;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
        }

        .title {
            font-size: 24px;
            font-weight: 700;
            margin: 2px 0;
            color: #111827;
            letter-spacing: 0.3px;
        }

        .company {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .sub {
            margin-top: 2px;
            font-size: 12px;
            color: var(--muted);
        }

        .meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 12px 0 10px;
            font-size: 12px;
            color: #374151;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        th,
        td {
            border: 1px solid var(--line);
            padding: 6px 5px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }

        th {
            background: var(--head-bg);
            font-weight: 700;
            color: #111827;
        }

        td.name,
        td.department,
        td.designation {
            text-align: left;
            white-space: normal;
        }

        .money {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .tfoot td {
            font-weight: 700;
            background: #f9fafb;
        }

        .footer {
            margin-top: 14px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #4b5563;
        }

        .print-actions {
            max-width: 1120px;
            margin: 12px auto 0;
            padding: 0 12px;
            display: flex;
            gap: 8px;
        }

        .btn {
            border: 0;
            padding: 8px 12px;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-print { background: #0f766e; }
        .btn-close { background: #374151; }

        @media print {
            @page {
                margin: 10mm;
                size: A4 landscape;
            }

            .print-actions {
                display: none;
            }

            .sheet {
                margin: 0;
                max-width: none;
                padding: 0;
            }

            .title {
                font-size: 20px;
            }

            table {
                font-size: 10px;
            }

            th,
            td {
                padding: 4px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" class="btn btn-print" onclick="window.print()">Print</button>
        <button type="button" class="btn btn-close" onclick="window.close()">Close</button>
    </div>

    <div class="sheet">
        <div class="header">
            <p class="company">{{ $websetting?->company_name ?? config('app.name', 'Hospital') }}</p>
            <p class="sub">{{ $websetting?->address ?? $websetting?->report_title ?? 'Address not available' }}</p>
            <p class="title">Monthly Salary Sheet</p>
            <p class="sub">Payroll Month: {{ $monthLabel }}</p>
        </div>

        <div class="meta">
            <div>Total Staff: {{ number_format((int) ($totals['staff_count'] ?? 0)) }}</div>
            <div>Generated: {{ $generatedAt->format('Y-m-d h:i A') }}</div>
        </div>

        <div class="meta" style="margin-top: 0;">
            <div>Late Fee / Late Day: {{ number_format((float) ($lateFeePerLate ?? 0), 2) }}</div>
            <div>Overtime Multiplier: {{ number_format((float) ($overtimeMultiplier ?? 1), 2) }}x</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Staff ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Basic Salary</th>
                    <th>Workable Days</th>
                    <th>Paid Days</th>
                    <th>Late Days</th>
                    <th>Unpaid Days</th>
                    <th>Biometric Deduction</th>
                    <th>Late Fee</th>
                    <th>Overtime Bonus</th>
                    <th>Advance Paid</th>
                    <th>Total Deduction</th>
                    <th>Net Payable</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['sl'] }}</td>
                        <td>{{ $row['staff_id'] }}</td>
                        <td class="name">{{ $row['name'] }}</td>
                        <td class="department">{{ $row['department'] }}</td>
                        <td class="designation">{{ $row['designation'] }}</td>
                        <td class="money">{{ number_format((float) ($row['basic_salary'] ?? 0), 2) }}</td>
                        <td>{{ $row['workable_days'] }}</td>
                        <td>{{ $row['paid_days'] }}</td>
                        <td>{{ $row['late'] }}</td>
                        <td>{{ $row['unpaid_days'] }}</td>
                        <td class="money">{{ number_format((float) ($row['biometric_deduction'] ?? 0), 2) }}</td>
                        <td class="money">{{ number_format((float) ($row['late_fee'] ?? 0), 2) }}</td>
                        <td class="money">{{ number_format((float) ($row['overtime_bonus'] ?? 0), 2) }}</td>
                        <td class="money">{{ number_format((float) ($row['advance_paid'] ?? 0), 2) }}</td>
                        <td class="money">{{ number_format((float) ($row['deduction'] ?? 0), 2) }}</td>
                        <td class="money">{{ number_format((float) ($row['payable_salary'] ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16">No salary data found for {{ $monthLabel }}.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(count($rows) > 0)
                <tfoot class="tfoot">
                    <tr>
                        <td colspan="5">Grand Total</td>
                        <td class="money">{{ number_format((float) ($totals['basic_salary'] ?? 0), 2) }}</td>
                        <td colspan="4"></td>
                        <td class="money">{{ number_format((float) ($totals['late_fee'] ?? 0), 2) }}</td>
                        <td class="money">{{ number_format((float) ($totals['overtime_bonus'] ?? 0), 2) }}</td>
                        <td></td>
                        <td class="money">{{ number_format((float) ($totals['deduction'] ?? 0), 2) }}</td>
                        <td class="money">{{ number_format((float) ($totals['payable_salary'] ?? 0), 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="footer">
            <div>Prepared by: ____________________</div>
            <div>Authorized by: ____________________</div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 200);
        });
    </script>
</body>
</html>
