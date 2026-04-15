<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\StaffAttendance;
use App\Services\GoogleHolidayCalendarService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncGoogleHolidaysCommand extends Command
{
    protected $signature = 'attendance:sync-holidays
        {--year= : Target year, e.g. 2026}
        {--date= : Sync only one date (Y-m-d)}
        {--country= : Country code for Google public holidays (bd/in/us/gb)}
        {--with-weekly : Also include configured weekly holidays}';

    protected $description = 'Sync Google public holidays (and optional weekly holidays) into staff attendance as Holiday';

    public function __construct(private GoogleHolidayCalendarService $holidayService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('attendance.google_holidays.enabled', false)) {
            $this->warn('Google holiday sync is disabled by config.');
            return self::SUCCESS;
        }

        $dateOption = (string) ($this->option('date') ?? '');
        $yearOption = (string) ($this->option('year') ?? '');
        $countryOption = (string) ($this->option('country') ?? '');
        $withWeekly = (bool) $this->option('with-weekly');

        $targetDate = null;
        if ($dateOption !== '') {
            try {
                $targetDate = Carbon::parse($dateOption)->toDateString();
            } catch (\Throwable $e) {
                $this->error('Invalid --date format. Use Y-m-d.');
                return self::FAILURE;
            }
        }

        $year = $yearOption !== '' ? (int) $yearOption : (int) now()->year;
        if ($targetDate) {
            $year = (int) Carbon::parse($targetDate)->year;
        }

        $holidayMap = $this->holidayService->getHolidayMapForYear(
            $year,
            $countryOption !== '' ? $countryOption : null
        );

        if ($targetDate) {
            $holidayMap = array_filter(
                $holidayMap,
                static fn($_title, $date) => $date === $targetDate,
                ARRAY_FILTER_USE_BOTH
            );
        }

        if ($withWeekly) {
            foreach ($this->buildWeeklyHolidayMap($year, $targetDate) as $date => $title) {
                if (!isset($holidayMap[$date])) {
                    $holidayMap[$date] = $title;
                }
            }
        }

        if (empty($holidayMap)) {
            $this->info('No holiday dates found to sync.');
            return self::SUCCESS;
        }

        $staffs = Admin::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->get(['id', 'first_name', 'last_name']);

        if ($staffs->isEmpty()) {
            $this->warn('No active staff found.');
            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($holidayMap as $date => $title) {
            foreach ($staffs as $staff) {
                $name = trim((string) ($staff->first_name ?? '') . ' ' . (string) ($staff->last_name ?? ''));

                $record = StaffAttendance::query()
                    ->where('staff_id', (string) $staff->id)
                    ->where('attendance_date', $date)
                    ->first();

                if ($record) {
                    if (in_array((string) $record->attendance_status, ['Present', 'Late'], true)) {
                        $skipped++;
                        continue;
                    }

                    $record->attendance_status = 'Holiday';
                    $record->note = $this->buildHolidayNote($title);
                    $record->status = 'Active';
                    $record->save();
                    $updated++;
                    continue;
                }

                StaffAttendance::create([
                    'staff_id' => (string) $staff->id,
                    'name' => $name,
                    'attendance_date' => $date,
                    'attendance_status' => 'Holiday',
                    'in_time' => null,
                    'out_time' => null,
                    'note' => $this->buildHolidayNote($title),
                    'status' => 'Active',
                ]);
                $created++;
            }
        }

        $this->info('Holiday sync completed.');
        $this->line('Created: ' . $created);
        $this->line('Updated: ' . $updated);
        $this->line('Skipped (present/late): ' . $skipped);

        $this->storeSummary([
            'synced_at' => now()->toDateTimeString(),
            'year' => $year,
            'date' => $targetDate,
            'country' => $countryOption !== '' ? strtolower($countryOption) : (string) config('attendance.google_holidays.country', 'bd'),
            'with_weekly' => $withWeekly,
            'holiday_dates' => array_keys($holidayMap),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function buildWeeklyHolidayMap(int $year, ?string $targetDate = null): array
    {
        $weeklyDays = config('attendance.google_holidays.weekly_holidays', []);
        if (!is_array($weeklyDays) || empty($weeklyDays)) {
            return [];
        }

        if ($targetDate) {
            $date = Carbon::parse($targetDate);
            if (in_array($date->dayOfWeek, $weeklyDays, true)) {
                return [$date->toDateString() => 'Weekly Holiday'];
            }

            return [];
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->startOfDay();
        $map = [];

        while ($start->lte($end)) {
            if (in_array($start->dayOfWeek, $weeklyDays, true)) {
                $map[$start->toDateString()] = 'Weekly Holiday';
            }
            $start->addDay();
        }

        return $map;
    }

    private function buildHolidayNote(string $title): string
    {
        return 'Holiday sync: ' . trim($title);
    }

    private function storeSummary(array $payload): void
    {
        try {
            $folder = 'attendance/holiday-sync';
            Storage::disk('local')->put(
                $folder . '/latest.json',
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            Storage::disk('local')->put(
                $folder . '/history/' . now()->format('Ymd_His') . '.json',
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } catch (\Throwable $e) {
            // Summary persistence should not fail the sync command.
        }
    }
}
