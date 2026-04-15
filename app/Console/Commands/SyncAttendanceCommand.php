<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZktecoService;

class SyncAttendanceCommand extends Command
{
    protected $signature = 'attendance:sync {--device= : optional device id to sync}';
    protected $description = 'Sync attendance from configured ZKTeco devices';

    protected ZktecoService $service;

    public function __construct(ZktecoService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $deviceId = $this->option('device');
        if ($deviceId) {
            $this->info("Queueing sync for device {$deviceId}...");
            $device = \App\Models\AttendanceDevice::find($deviceId);
            if (!$device) {
                $this->error('Device not found');
                return 1;
            }

            \App\Jobs\SyncDeviceAttendanceJob::dispatch($device->id);
            $this->info('Job dispatched.');
        } else {
            $this->info('Queueing sync jobs for all active devices...');
            $devices = \App\Models\AttendanceDevice::where('status', 'Active')->get();
            foreach ($devices as $device) {
                \App\Jobs\SyncDeviceAttendanceJob::dispatch($device->id);
                $this->info("Queued device {$device->id}");
            }
        }

        return 0;
    }
}
