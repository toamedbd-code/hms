<?php
namespace App\Services;

use App\Models\AttendanceDevice;
use App\Models\Attendance;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rats\Zkteco\Lib\ZKTeco;

/**
 * ZktecoService
 *
 * Handles connecting to ZKTeco devices and syncing attendance logs.
 * Relies on the rats/zkteco package. Install with:
 * composer require rats/zkteco
 */
class ZktecoService
{
    protected int $port = 4370;

    /**
     * Fetch attendance logs from a single device.
     * Returns array of normalized logs: [ ['employee_code'=>..., 'timestamp'=>..., 'type'=>'in'|'out'], ... ]
     */
    public function fetchFromDevice(AttendanceDevice $device): array
    {
        $host = $device->identifier;

        try {
            if (!class_exists(ZKTeco::class)) {
                throw new Exception('Rats\\Zkteco ZKTeco class not found. Run: composer require rats/zkteco');
            }

            $zk = new ZKTeco($host, $this->port);
            // connect to device
            $zk->connect();

            // fetch attendance using package API
            $attendance = [];
            if (method_exists($zk, 'getAttendance')) {
                $attendance = $zk->getAttendance();
            } elseif (method_exists($zk, 'get_attendance')) {
                $attendance = $zk->get_attendance();
            }

            // ensure socket is closed
            if (method_exists($zk, 'disconnect')) {
                $zk->disconnect();
            }

            // normalize rows
            $rows = [];
            foreach ($attendance as $row) {
                // Normalize different possible return shapes from rats/zkteco or other libs
                if (!is_array($row)) continue;

                $employee = $row['userid'] ?? $row['user_id'] ?? $row['uid'] ?? $row['pin'] ?? ($row[1] ?? null);
                $ts = $row['timestamp'] ?? $row['time'] ?? $row['datetime'] ?? ($row[2] ?? null);
                $type = $row['punch'] ?? $row['punch_type'] ?? $row['type'] ?? $row[3] ?? null;

                if (empty($employee) || empty($ts)) continue;

                // Parse timestamp robustly
                try {
                    $timestamp = Carbon::parse($ts)->toDateTimeString();
                } catch (\Throwable $e) {
                    // try if it's unix
                    if (is_numeric($ts)) {
                        $timestamp = Carbon::createFromTimestamp((int)$ts)->toDateTimeString();
                    } else {
                        // skip invalid timestamp
                        continue;
                    }
                }

                $rows[] = [
                    'employee_code' => (string)$employee,
                    'timestamp' => $timestamp,
                    'type' => $this->normalizeType($type, $row),
                ];
            }

            return $rows;
        } catch (Exception $e) {
            Log::error('Zkteco fetch error: ' . $e->getMessage(), ['device' => $device->id ?? null]);
            throw $e;
        }
    }

    /**
     * Store fetched logs into `attendances` table. Avoid duplicates by using insertOrIgnore
     * Returns number of rows inserted.
     */
    public function storeAttendance(AttendanceDevice $device, array $logs): int
    {
        $rows = [];
        foreach ($logs as $log) {
            $employee = $log['employee_code'] ?? null;
            $ts = $log['timestamp'] ?? null;
            $type = $log['type'] ?? 'in';

            if (!$employee || !$ts) continue;

            try {
                $recordedAt = Carbon::parse($ts)->toDateTimeString();
            } catch (\Throwable $e) {
                // skip invalid timestamps
                continue;
            }

            $rows[] = [
                'device_id' => $device->id,
                'employee_code' => (string)$employee,
                'type' => $type,
                'recorded_at' => $recordedAt,
                'source' => 'device',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($rows)) return 0;

        // Use insertOrIgnore to avoid duplicate key errors; migration adds a unique index
        $inserted = DB::table('attendances')->insertOrIgnore($rows);

        // insertOrIgnore returns number of rows affected in newer Laravel versions; for safety, return count of rows attempted
        return is_int($inserted) ? $inserted : count($rows);
    }

    protected function normalizeType($type, $rawRow = [])
    {
        if (is_null($type)) {
            // sometimes punch type is encoded in another index (e.g. 3 or 4)
            $t = $rawRow['punch_type'] ?? ($rawRow[3] ?? null);
            $type = $t;
        }

        // normalize numeric types to 'in' or 'out'
        if (is_numeric($type)) {
            return ((int)$type === 0 || (int)$type === 1) ? 'in' : 'out';
        }

        $s = strtolower((string)$type);
        if (str_contains($s, 'in')) return 'in';
        if (str_contains($s, 'out')) return 'out';
        return 'in';
    }

    /**
     * Sync a single device: fetch and store. Returns number of inserted rows.
     */
    public function syncDevice(AttendanceDevice $device): int
    {
        $logs = $this->fetchFromDevice($device);
        return $this->storeAttendance($device, $logs);
    }

    /**
     * Sync all active devices and return summary.
     */
    public function syncAllDevices(): array
    {
        $summary = [];
        $devices = AttendanceDevice::where('status', 'Active')->get();
        foreach ($devices as $device) {
            try {
                $count = $this->syncDevice($device);
                $summary[] = ['device_id' => $device->id, 'inserted' => $count];
            } catch (\Throwable $e) {
                $summary[] = ['device_id' => $device->id, 'error' => $e->getMessage()];
            }
        }
        return $summary;
    }
}
