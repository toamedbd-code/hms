<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ZktecoService;
use App\Models\AttendanceDevice;
use App\Jobs\SyncDeviceAttendanceJob;
use Exception;
use Illuminate\Support\Facades\Bus;

class AttendanceSyncController extends Controller
{
    protected ZktecoService $service;

    public function __construct(ZktecoService $service)
    {
        $this->middleware('auth:admin');
        $this->service = $service;
    }

    /**
     * Sync all active devices (triggered from admin UI)
     */
    public function sync(Request $request)
    {
        // Dispatch a queued job per active device so sync runs in background
        try {
            $devices = AttendanceDevice::where('status', 'Active')->get();
            foreach ($devices as $device) {
                SyncDeviceAttendanceJob::dispatch($device->id);
            }

            return redirect()->back()->with('successMessage', 'Attendance sync queued for active devices.');
        } catch (Exception $e) {
            return redirect()->back()->with('errorMessage', 'Failed to queue sync jobs: ' . $e->getMessage());
        }
    }

    /**
     * Sync a single device by id (optional)
     */
    public function syncDevice(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);
        try {
            SyncDeviceAttendanceJob::dispatch($device->id);
            return redirect()->back()->with('successMessage', "Device {$device->name} sync queued.");
        } catch (Exception $e) {
            return redirect()->back()->with('errorMessage', 'Failed to queue device sync: ' . $e->getMessage());
        }
    }
}
