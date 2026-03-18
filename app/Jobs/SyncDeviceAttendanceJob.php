<?php
namespace App\Jobs;

use App\Models\AttendanceDevice;
use App\Services\ZktecoService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncDeviceAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $deviceId;

    public $tries = 3;

    public $timeout = 120;

    public function __construct(int $deviceId)
    {
        $this->deviceId = $deviceId;
    }

    public function handle(ZktecoService $service)
    {
        $device = AttendanceDevice::find($this->deviceId);
        if (!$device) {
            Log::warning('SyncDeviceAttendanceJob: device not found', ['device_id' => $this->deviceId]);
            return;
        }

        try {
            $inserted = $service->syncDevice($device);
            Log::info('SyncDeviceAttendanceJob: completed', ['device_id' => $device->id, 'inserted' => $inserted]);
        } catch (Exception $e) {
            Log::error('SyncDeviceAttendanceJob: failed', ['device_id' => $device->id, 'error' => $e->getMessage()]);
            // Do not rethrow: one bad/unreachable device should not break global sync.
            return;
        }
    }
}
