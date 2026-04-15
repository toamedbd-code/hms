<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\FaceEncoding;
use App\Services\AttendanceDeviceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FaceKioskAttendanceController extends Controller
{
    public function index()
    {
        // Public kiosk page (PIN required only for POST)
        return view('kiosk.attendance_face');
    }

    public function mark(Request $request)
    {
        // Accept either `employee_code` (manual) or `descriptor` (array) for auto match
        $employeeCode = null;

        if ($request->filled('employee_code')) {
            $employeeCode = $request->input('employee_code');
        } elseif ($request->filled('descriptor')) {
            $incoming = $request->input('descriptor');
            if (!is_array($incoming)) {
                return response()->json(['message' => 'Invalid descriptor'], 422);
            }

            // Use the latest encoding per employee code to avoid noisy duplicates.
            $encodings = FaceEncoding::orderByDesc('id')->get()->unique('employee_code')->values();
            $best = null;
            foreach ($encodings as $enc) {
                $d = $this->euclideanDistance($incoming, (array) $enc->descriptor);
                if ($best === null || $d < $best['dist']) {
                    $best = ['dist' => $d, 'code' => $enc->employee_code];
                }
            }

            if ($best && $best['dist'] <= config('attendance.face_threshold')) {
                $employeeCode = $best['code'];
            } else {
                return response()->json(['message' => 'No match found', 'best' => $best], 422);
            }
        } else {
            return response()->json(['message' => 'employee_code or descriptor required'], 422);
        }

        $employeeCode = trim((string) $employeeCode);
        if ($employeeCode === '') {
            return response()->json(['message' => 'Invalid employee code'], 422);
        }

        // Auto IN/OUT toggle based on latest record for today.
        $now = now();
        $latestToday = Attendance::where('employee_code', $employeeCode)
            ->whereDate('recorded_at', $now->toDateString())
            ->orderByRaw('COALESCE(recorded_out, recorded_at) DESC')
            ->orderByDesc('id')
            ->first();

        $cooldownSeconds = max((int) config('attendance.face_scan_cooldown_seconds', 10), 0);

        if ($latestToday && $cooldownSeconds > 0) {
            $lastAction = null;
            $lastActionTs = null;

            if ($latestToday->type === 'in' && empty($latestToday->recorded_out)) {
                $lastAction = 'in';
                $lastActionTs = Carbon::parse($latestToday->recorded_at);
            } elseif ($latestToday->type === 'in' && !empty($latestToday->recorded_out)) {
                $lastAction = 'out';
                $lastActionTs = Carbon::parse($latestToday->recorded_out);
            } elseif ($latestToday->type === 'out') {
                $lastAction = 'out';
                $lastActionTs = Carbon::parse($latestToday->recorded_out ?? $latestToday->recorded_at);
            }

            if ($lastAction && $lastActionTs && $lastActionTs->diffInSeconds($now) < $cooldownSeconds) {
                return response()->json([
                    'status' => 'success',
                    'marked_as' => $lastAction,
                    'employee_code' => $employeeCode,
                    'attendance' => $latestToday,
                    'message' => "{$lastAction} already marked. Wait {$cooldownSeconds} seconds.",
                ], 200);
            }
        }

        $eventType = 'in';
        if ($latestToday && $latestToday->type === 'in' && empty($latestToday->recorded_out)) {
            $eventType = 'out';
        }

        /** @var AttendanceDeviceService $service */
        $service = app(AttendanceDeviceService::class);

        $ok = $service->processAttendanceEvent([
            'employee_code' => $employeeCode,
            'type' => $eventType,
            'timestamp' => $now->toDateTimeString(),
            'source' => 'webcam-kiosk',
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ],
        ]);

        if (!$ok) {
            return response()->json(['message' => 'Failed to mark attendance'], 500);
        }

        // Return the relevant attendance row
        if ($eventType === 'in') {
            $attendance = Attendance::where('employee_code', $employeeCode)->orderByDesc('id')->first();
        } else {
            $attendance = Attendance::where('employee_code', $employeeCode)
                ->where('type', 'in')
                ->whereDate('recorded_at', $now->toDateString())
                ->whereNotNull('recorded_out')
                ->orderByDesc('recorded_at')
                ->first();
        }

        return response()->json([
            'status' => 'success',
            'marked_as' => $eventType,
            'employee_code' => $employeeCode,
            'attendance' => $attendance,
        ], 201);
    }

    private function euclideanDistance(array $a, array $b)
    {
        $len = min(count($a), count($b));
        $sum = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $diff = (float) $a[$i] - (float) $b[$i];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }
}
