<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceDeviceService;
use App\Services\ZktecoService;
use Illuminate\Support\Facades\Cache;

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
        $deviceSettings = app(AttendanceDeviceService::class);
        if (!$deviceSettings->isDeviceIntegrationEnabled()) {
            $this->info('Attendance device integration is disabled.');
            return 0;
        }

        if (!$deviceSettings->shouldPullSync()) {
            $this->info('Pull sync is off. Device data flow is set to webhook.');
            return 0;
        }

        $interval = $deviceSettings->integrationSettings()['sync_interval_minutes'];
        $deviceId = $this->option('device');
        if (!$deviceId && Cache::has('attendance.device.pull_sync')) {
            $this->info('Skipped: waiting for the configured sync interval.');
            return 0;
        }
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
            Cache::put('attendance.device.pull_sync', 1, now()->addMinutes($interval));
        }

        return 0;
    }
}
