<?php
namespace App\Services;

use App\Models\Admin;
use App\Models\AdminDetail;
use App\Models\Attendance;
use App\Models\AttendanceShift;
use App\Models\AttendanceDevice;
use App\Models\DutyRoster;
use App\Models\StaffAttendance;
use Carbon\Carbon;

class AttendanceDeviceService
{
    public function validateDeviceWebhook(array $payload, ?string $secret = null): ?AttendanceDevice
    {
        $identifier = $payload['device_id'] ?? $payload['identifier'] ?? null;
        if (!$identifier) return null;

        $device = AttendanceDevice::where('identifier', $identifier)->where('status', 'Active')->first();
        if (!$device) return null;

        if ($device->secret && $secret !== null && hash_equals($device->secret, $secret)) {
            return $device;
        }

        // If no secret set, accept by identifier only
        return $device;
    }

        public function processAttendanceEvent(array $payload): bool
    {
        // Expected payload: ['device_id'=>'...', 'employee_code'=>'E123', 'type'=>'in'|'out', 'timestamp'=>'2026-03-11T13:00:00']
        // Optional: 'source' => 'device'|'webcam'|..., 'meta' => array
        $employeeCode = $payload['employee_code'] ?? null;
        $type = strtolower((string) ($payload['type'] ?? 'in'));
        $ts = $payload['timestamp'] ?? now()->toDateTimeString();
        $deviceIdentifier = $payload['device_id'] ?? $payload['identifier'] ?? null;
        $source = $payload['source'] ?? 'device';
        $meta = $payload['meta'] ?? null;
        if (!is_array($meta)) {
            $meta = null;
        }

        if (!$employeeCode) return false;

        if (!in_array($type, ['in', 'out'], true)) {
            $type = 'in';
        }

        if (class_exists(Attendance::class)) {
            $device = null;
            if ($deviceIdentifier) {
                $device = AttendanceDevice::where('identifier', $deviceIdentifier)->first();
            }

            if ($type === 'in') {
                $model = Attendance::create([
                    'employee_code' => $employeeCode,
                    'type' => 'in',
                    'recorded_at' => $ts,
                    'device_id' => $device ? $device->id : null,
                    'source' => $source,
                    'meta' => $meta,
                ]);

                $this->syncLegacyStaffAttendance($employeeCode, 'in', Carbon::parse($ts));
                return (bool) $model;
            }

            // Process 'out' event: pair with latest in-record for same day
            $outTs = Carbon::parse($ts);
            $inRecord = Attendance::where('employee_code', $employeeCode)
                ->where('type', 'in')
                ->whereDate('recorded_at', $outTs->toDateString())
                ->whereNull('recorded_out')
                ->orderByDesc('recorded_at')
                ->first();

            if (!$inRecord) {
                // No prior in: create standalone out record
                $model = Attendance::create([
                    'employee_code' => $employeeCode,
                    'type' => 'out',
                    'recorded_at' => $ts,
                    'recorded_out' => $ts,
                    'device_id' => $device ? $device->id : null,
                    'source' => $source,
                    'meta' => $meta,
                ]);

                $this->syncLegacyStaffAttendance($employeeCode, 'out', $outTs);
                return (bool) $model;
            }

            $inTs = Carbon::parse($inRecord->recorded_at);
            $durationMinutes = max(0, $outTs->diffInMinutes($inTs));

            $staff = $this->resolveAdminFromEmployeeCode((string) $employeeCode);
            $resolvedStaffId = $staff?->id;

            // Check roster first, then shift override
            $roster = null;
            if (!empty($resolvedStaffId)) {
                $roster = DutyRoster::where('date', $inTs->toDateString())
                    ->where('staff_id', $resolvedStaffId)
                    ->first();
            }

            $shift = null;
            if (!$roster && !empty($employeeCode)) {
                $shift = AttendanceShift::where('employee_code', $employeeCode)
                    ->where(function ($q) use ($inTs) {
                        $q->whereNull('effective_from')->orWhere('effective_from', '<=', $inTs->toDateString());
                    })
                    ->where(function ($q) use ($inTs) {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>=', $inTs->toDateString());
                    })->orderByDesc('id')->first();
            }

            if ($roster && $roster->start_time) {
                $scheduledStart = Carbon::createFromFormat('H:i', $roster->start_time);
                $scheduledEnd = Carbon::createFromFormat('H:i', $roster->end_time);
            } elseif ($shift && $shift->start_time) {
                $scheduledStart = Carbon::createFromFormat('H:i', $shift->start_time);
                $scheduledEnd = Carbon::createFromFormat('H:i', $shift->end_time);
            } else {
                $scheduledStart = Carbon::createFromFormat('H:i', config('attendance.scheduled_start'));
                $scheduledEnd = Carbon::createFromFormat('H:i', config('attendance.scheduled_end'));
            }

            $scheduledStart = $scheduledStart->setDate($inTs->year, $inTs->month, $inTs->day);
            $scheduledEnd = $scheduledEnd->setDate($inTs->year, $inTs->month, $inTs->day);

            $lateMinutes = 0;
            if ($inTs->greaterThan($scheduledStart)) {
                $lateMinutes = $inTs->diffInMinutes($scheduledStart);
            }

            $overtimeMinutes = 0;
            if ($outTs->greaterThan($scheduledEnd)) {
                $overtimeMinutes = $outTs->diffInMinutes($scheduledEnd);
            }

            $lateThreshold = (int) config('attendance.late_threshold_minutes', 60);
            $overtimeThreshold = (int) config('attendance.overtime_threshold_minutes', 60);

            $deduction = 0.0;
            if ($lateMinutes >= $lateThreshold) {
                $deductionHours = (int) floor($lateMinutes / 60);
                $deduction = $deductionHours * (float) config('attendance.late_deduction_per_hour', 0);
            }

            $overtimeAmount = 0.0;
            if ($overtimeMinutes >= $overtimeThreshold) {
                $overtimeHours = (int) floor($overtimeMinutes / 60);
                $overtimeAmount = $overtimeHours * (float) config('attendance.overtime_rate_per_hour', 0);
            }

            $inRecord->update([
                'recorded_out' => $outTs->toDateTimeString(),
                'duration_minutes' => $durationMinutes,
                'late_minutes' => $lateMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'deduction_amount' => $deduction,
                'overtime_amount' => $overtimeAmount,
                'device_id' => $device ? $device->id : $inRecord->device_id,
            ]);

            $this->syncLegacyStaffAttendance($employeeCode, 'out', $outTs);

            return true;
        }

        logger()->info('Attendance event', $payload);
        return true;
    }

    private function syncLegacyStaffAttendance(string $employeeCode, string $eventType, Carbon $eventTs): void
    {
        $staff = $this->resolveAdminFromEmployeeCode($employeeCode);
        if (!$staff) {
            return;
        }

        $attendanceDate = $eventTs->toDateString();
        $record = StaffAttendance::firstOrNew([
            'staff_id' => (string) $staff->id,
            'attendance_date' => $attendanceDate,
        ]);

        if (!$record->exists) {
            $record->status = 'Active';
            $record->name = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
            $record->attendance_status = 'Present';
        }

        if (empty($record->name)) {
            $record->name = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
        }

        if ($eventType === 'in') {
            $existingIn = $this->parseStoredTime($record->in_time, $attendanceDate);
            if (!$existingIn || $eventTs->lt($existingIn)) {
                $record->in_time = $eventTs->format('H:i:s');
            }

            $record->attendance_status = $this->deriveAttendanceStatus((int) $staff->id, $employeeCode, $eventTs);
        }

        if ($eventType === 'out') {
            $existingOut = $this->parseStoredTime($record->out_time, $attendanceDate);
            if (!$existingOut || $eventTs->gt($existingOut)) {
                $record->out_time = $eventTs->format('H:i:s');
            }

            if (empty($record->attendance_status)) {
                $record->attendance_status = 'Present';
            }
        }

        $record->save();
    }

    private function resolveAdminFromEmployeeCode(string $employeeCode): ?Admin
    {
        if (ctype_digit($employeeCode)) {
            $admin = Admin::find((int) $employeeCode);
            if ($admin) {
                return $admin;
            }
        }

        // Fallback for codes like test_emp_1 or EMP-3 where trailing digits represent admin id.
        if (preg_match('/(\d+)$/', $employeeCode, $matches)) {
            $admin = Admin::find((int) $matches[1]);
            if ($admin) {
                return $admin;
            }
        }

        $detail = AdminDetail::query()->where('staff_id', $employeeCode)->first();
        if ($detail && !empty($detail->admin_id)) {
            return Admin::find((int) $detail->admin_id);
        }

        return null;
    }

    private function deriveAttendanceStatus(int $adminId, string $employeeCode, Carbon $inTs): string
    {
        $scheduledStart = Carbon::createFromFormat('H:i', config('attendance.scheduled_start'));

        $roster = DutyRoster::where('date', $inTs->toDateString())
            ->where('staff_id', $adminId)
            ->first();

        if ($roster && !empty($roster->start_time)) {
            $rosterStart = $this->parseShiftTime((string) $roster->start_time, $inTs);
            if ($rosterStart) {
                $scheduledStart = $rosterStart;
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
                $shiftStart = $this->parseShiftTime((string) $shift->start_time, $inTs);
                if ($shiftStart) {
                    $scheduledStart = $shiftStart;
                }
            }
        }

        $scheduledStart = $scheduledStart->setDate($inTs->year, $inTs->month, $inTs->day);
        return $inTs->gt($scheduledStart) ? 'Late' : 'Present';
    }

    private function parseShiftTime(string $value, Carbon $date): ?Carbon
    {
        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                return $parsed->setDate($date->year, $date->month, $date->day);
            } catch (\Throwable $e) {
                // Try next format.
            }
        }

        return null;
    }

    private function parseStoredTime(mixed $value, string $date): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        $asString = is_string($value) ? $value : (string) $value;
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'H:i:s', 'H:i'] as $format) {
            try {
                if (str_starts_with($format, 'Y-m-d')) {
                    return Carbon::createFromFormat($format, $asString);
                }

                return Carbon::createFromFormat($format, $asString)->setDateFrom(Carbon::parse($date));
            } catch (\Throwable $e) {
                // Continue.
            }
        }

        return null;
    }
}
