<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\StaffAttendance;
use App\Services\GoogleHolidayCalendarService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportHolidayAuditCommand extends Command
{
    protected $signature = 'attendance:holiday-audit
        {--month= : Target month (Y-m), default current month}
        {--country= : Country code for Google public holidays (bd/in/us/gb)}
        {--with-weekly : Include configured weekly holidays}';

    protected $description = 'Export monthly holiday audit report as CSV for payroll verification';

    public function __construct(private GoogleHolidayCalendarService $holidayService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $monthInput = (string) ($this->option('month') ?? '');
        $countryOption = (string) ($this->option('country') ?? '');
        $withWeekly = (bool) $this->option('with-weekly');

        try {
            $monthDate = $monthInput !== ''
                ? Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $this->error('Invalid --month format. Use Y-m, e.g. 2026-03');
            return self::FAILURE;
        }

        $year = (int) $monthDate->year;
        $month = (int) $monthDate->month;

        $holidayMap = $this->holidayService->getHolidayMapForYear(
            $year,
            $countryOption !== '' ? $countryOption : null
        );

        $holidayMap = array_filter(
            $holidayMap,
            static function ($_title, $date) use ($monthDate) {
                return str_starts_with($date, $monthDate->format('Y-m-'));
            },
            ARRAY_FILTER_USE_BOTH
        );

        if ($withWeekly) {
            foreach ($this->buildWeeklyHolidayMapForMonth($monthDate) as $date => $title) {
                if (!isset($holidayMap[$date])) {
                    $holidayMap[$date] = $title;
                }
            }
        }

        ksort($holidayMap);

        $staffIds = Admin::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->all();

        $totalStaff = count($staffIds);
        if ($totalStaff === 0) {
            $this->warn('No active staff found.');
            return self::SUCCESS;
        }

        $rows = [];
        $rows[] = ['Date', 'Holiday Title', 'Total Staff', 'Holiday Marked', 'Present/Late Skipped', 'Unmarked/Other'];

        foreach ($holidayMap as $date => $title) {
            $records = StaffAttendance::query()
                ->whereIn('staff_id', $staffIds)
                ->where('attendance_date', $date)
                ->get(['staff_id', 'attendance_status']);

            $holidayMarked = 0;
            $presentLateSkipped = 0;

            foreach ($records as $record) {
                $status = (string) ($record->attendance_status ?? '');
                if ($status === 'Holiday') {
                    $holidayMarked++;
                }
                if (in_array($status, ['Present', 'Late'], true)) {
                    $presentLateSkipped++;
                }
            }

            $unmarkedOther = max($totalStaff - $holidayMarked - $presentLateSkipped, 0);

            $excelDate = Carbon::parse($date)->format('n/j/Y');

            $rows[] = [
                '="' . $excelDate . '"',
                $title,
                (string) $totalStaff,
                (string) $holidayMarked,
                (string) $presentLateSkipped,
                (string) $unmarkedOther,
            ];
        }

        $csv = $this->toCsv($rows);
        $filePath = 'attendance/reports/holiday-audit-' . $monthDate->format('Y-m') . '.csv';
        Storage::disk('local')->put($filePath, $csv);

        $this->info('Holiday audit exported successfully.');
        $this->line('File: storage/app/' . $filePath);
        $this->line('Rows: ' . max(count($rows) - 1, 0));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function buildWeeklyHolidayMapForMonth(Carbon $monthDate): array
    {
        $weeklyDays = config('attendance.google_holidays.weekly_holidays', []);
        if (!is_array($weeklyDays) || empty($weeklyDays)) {
            return [];
        }

        $cursor = $monthDate->copy()->startOfMonth();
        $end = $monthDate->copy()->endOfMonth();
        $map = [];

        while ($cursor->lte($end)) {
            if (in_array($cursor->dayOfWeek, $weeklyDays, true)) {
                $map[$cursor->toDateString()] = 'Weekly Holiday';
            }
            $cursor->addDay();
        }

        return $map;
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function toCsv(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $escaped = array_map(static function ($value) {
                $v = (string) $value;
                $v = str_replace('"', '""', $v);
                return '"' . $v . '"';
            }, $row);

            $lines[] = implode(',', $escaped);
        }

        // Add UTF-8 BOM for better Excel compatibility on Windows.
        return "\xEF\xBB\xBF" . implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
