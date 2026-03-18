<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\StaffAttendance;
use App\Models\Admin;
use App\Models\AdminDetail;
use App\Models\DutyRoster;
use App\Models\AttendanceShift;
use Carbon\Carbon;

class SyncAttendanceToStaff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:sync-to-staff {--date= : Sync for specific date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync biometric attendance records to staff attendance';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $dateString = $date->toDateString();

        $this->info("Syncing attendance for date: {$dateString}");

        $attendances = Attendance::whereDate('recorded_at', $dateString)
            ->get()
            ->groupBy('employee_code');

        $synced = 0;
        $skipped = 0;

        foreach ($attendances as $employeeCode => $records) {
            $staff = $this->resolveStaff((string) $employeeCode);
            if (!$staff) {
                $this->warn("Staff not found for employee_code: {$employeeCode}");
                $skipped++;
                continue;
            }

            $firstIn = $records
                ->filter(fn($row) => strtolower((string) $row->type) === 'in')
                ->sortBy('recorded_at')
                ->first();

            $lastOut = $records
                ->filter(fn($row) => !empty($row->recorded_out))
                ->sortByDesc('recorded_out')
                ->first();

            if (!$firstIn && !$lastOut) {
                $skipped++;
                continue;
            }

            $inTs = $firstIn ? Carbon::parse($firstIn->recorded_at) : null;
            $outTs = $lastOut ? Carbon::parse($lastOut->recorded_out) : null;
            $status = 'Present';
            if ($inTs && $this->isLate((int) $staff->id, (string) $employeeCode, $inTs)) {
                $status = 'Late';
            }

            $existing = StaffAttendance::where('staff_id', $staff->id)
                ->where('attendance_date', $dateString)
                ->first();

            $payload = [
                'attendance_status' => $status,
                'in_time' => $inTs?->format('H:i:s'),
                'out_time' => $outTs?->format('H:i:s'),
                'note' => 'Synced from biometric device',
                'status' => 'Active',
            ];

            if ($existing) {
                $existing->update($payload);
                $this->info("Updated attendance for {$staff->name}");
            } else {
                StaffAttendance::create([
                    'staff_id' => $staff->id,
                    'name' => $staff->name,
                    'attendance_date' => $dateString,
                ] + $payload);
                $this->info("Created attendance for {$staff->name}");
            }

            $synced++;
        }

        $this->info("Sync completed. Synced: {$synced}, Skipped: {$skipped}");
        return Command::SUCCESS;
    }

    private function resolveStaff(string $employeeCode): ?Admin
    {
        if (ctype_digit($employeeCode)) {
            $byId = Admin::find((int) $employeeCode);
            if ($byId) {
                return $byId;
            }
        }

        $detail = AdminDetail::where('staff_id', $employeeCode)->first();
        if ($detail && !empty($detail->admin_id)) {
            return Admin::find((int) $detail->admin_id);
        }

        return Admin::where('username', $employeeCode)->first();
    }

    private function isLate(int $adminId, string $employeeCode, Carbon $inTs): bool
    {
        $scheduledStart = Carbon::createFromFormat('H:i', config('attendance.scheduled_start'))
            ->setDate($inTs->year, $inTs->month, $inTs->day);

        $roster = DutyRoster::where('date', $inTs->toDateString())
            ->where('staff_id', $adminId)
            ->first();

        if ($roster && !empty($roster->start_time)) {
            $parsed = $this->parseShiftTime((string) $roster->start_time, $inTs);
            if ($parsed) {
                $scheduledStart = $parsed;
            }
        } else {
            $shift = AttendanceShift::where('employee_code', $employeeCode)
                ->where(function ($q) use ($inTs) {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', $inTs->toDateString());
                })
                ->where(function ($q) use ($inTs) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $inTs->toDateString());
                })
                ->orderByDesc('id')
                ->first();

            if ($shift && !empty($shift->start_time)) {
                $parsed = $this->parseShiftTime((string) $shift->start_time, $inTs);
                if ($parsed) {
                    $scheduledStart = $parsed;
                }
            }
        }

        return $inTs->gt($scheduledStart);
    }

    private function parseShiftTime(string $value, Carbon $date): ?Carbon
    {
        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                return $parsed->setDate($date->year, $date->month, $date->day);
            } catch (\Throwable $e) {
                // Continue.
            }
        }

        return null;
    }
}
