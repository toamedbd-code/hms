<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
use App\Models\FaceEncoding;
use App\Services\AttendanceDeviceService;

class FaceAttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin')->except(['registerStoreTest', 'markTest']);
        $this->middleware('permission:staff-attendance-list')->except(['registerStoreTest', 'markTest']);
    }

    public function index(Request $request)
    {
        if ($redirect = $this->cameraLocalhostRedirect($request)) {
            return $redirect;
        }

        return view('backend.staffattendance.face');
    }

    public function registerIndex(Request $request)
    {
        if ($redirect = $this->cameraLocalhostRedirect($request)) {
            return $redirect;
        }

        return view('backend.staffattendance.face_register');
    }

    public function registerList(Request $request)
    {
        $query = FaceEncoding::query()->orderByDesc('id');
        if ($request->filled('employee_code')) {
            $query->where('employee_code', 'like', '%' . trim((string) $request->input('employee_code')) . '%');
        }

        $encodings = $query->paginate(20)->withQueryString();
        return view('backend.staffattendance.face_encodings', compact('encodings'));
    }

    public function registerDelete(int $id)
    {
        $encoding = FaceEncoding::findOrFail($id);
        $encoding->delete();

        return redirect()->back()->with('successMessage', 'Face encoding deleted successfully.');
    }

    private function cameraLocalhostRedirect(Request $request)
    {
        if ($request->isSecure()) {
            return null;
        }

        if ($request->getHost() !== 'hms.test') {
            return null;
        }

        $target = 'http://localhost/hms/public' . $request->getRequestUri();
        return redirect()->away($target);
    }

    public function registerStore(Request $request)
    {
        $request->validate([
            'employee_code' => 'required|string',
            'descriptor' => 'required|array|min:64',
            'descriptor.*' => 'numeric',
        ]);

        try {
            FaceEncoding::create([
                'employee_code' => trim((string) $request->input('employee_code')),
                'descriptor' => $request->input('descriptor'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Face register failed', [
                'employee_code' => $request->input('employee_code'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Face encoding save failed. Please try again.',
            ], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    // Test-only endpoint (no admin auth) to allow automated browser tests to register encodings.
    public function registerStoreTest(Request $request)
    {
        $data = $request->only(['employee_code', 'descriptor']);
        if (empty($data['employee_code']) || empty($data['descriptor']) || !is_array($data['descriptor'])) {
            file_put_contents(storage_path('logs/face_register.log'), date('c') . " INVALID INPUT: " . json_encode($request->all()) . "\n", FILE_APPEND);
            return response()->json(['message' => 'invalid input'], 422);
        }
        $fe = FaceEncoding::create([
            'employee_code' => $data['employee_code'],
            'descriptor' => $data['descriptor'],
        ]);
        $entry = ['time' => date('c'), 'employee_code' => $data['employee_code'], 'face_id' => $fe->id];
        file_put_contents(storage_path('logs/face_register.log'), json_encode($entry) . "\n", FILE_APPEND);
        return response()->json(['status' => 'ok', 'face_id' => $fe->id]);
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
                $d = $this->euclideanDistance($incoming, $enc->descriptor);
                if ($best === null || $d < $best['dist']) {
                    $best = ['dist' => $d, 'code' => $enc->employee_code];
                }
            }

            // threshold: 0.6 is a common starting point for face descriptors
            if ($best && $best['dist'] <= config('attendance.face_threshold')) {
                $employeeCode = $best['code'];
            } else {
                return response()->json(['message' => 'No match found', 'best' => $best], 422);
            }
        } else {
            return response()->json(['message' => 'employee_code or descriptor required'], 422);
        }

                // Auto IN/OUT toggle:
        // If there is an open IN record today (recorded_out is null) => mark as OUT, else mark as IN.
        $now = now();
        $openInExists = Attendance::where('employee_code', $employeeCode)
            ->where('type', 'in')
            ->whereDate('recorded_at', $now->toDateString())
            ->whereNull('recorded_out')
            ->exists();

        $eventType = $openInExists ? 'out' : 'in';

        /** @var AttendanceDeviceService $service */
        $service = app(AttendanceDeviceService::class);

        $ok = $service->processAttendanceEvent([
            'employee_code' => $employeeCode,
            'type' => $eventType,
            'timestamp' => $now->toDateTimeString(),
            'source' => 'webcam',
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

    // Test-only endpoint (no admin auth) to allow automated tests to POST descriptors and mark attendance.
    public function markTest(Request $request)
    {
        // Log incoming descriptor for debugging
        file_put_contents(storage_path('logs/face_mark_test.log'), date('c') . " " . json_encode($request->all()) . "\n", FILE_APPEND);

        return $this->mark($request);
    }

    private function euclideanDistance(array $a, array $b)
    {
        $len = min(count($a), count($b));
        $sum = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $diff = (float)$a[$i] - (float)$b[$i];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }
}
