<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceDevice;
use App\Models\WebSetting;
use App\Services\AttendanceDeviceService;

class AttendanceDeviceController extends Controller
{
    protected AttendanceDeviceService $service;

    public function __construct(AttendanceDeviceService $service)
    {
        $this->service = $service;
        $this->middleware('auth:admin')->except(['webhook', 'admsWebhook']);
    }

    // Register device (admin)
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string',
            'identifier' => 'required|string|unique:attendance_devices,identifier',
            'type' => 'required|string',
            'secret' => 'nullable|string',
        ]);

        $device = AttendanceDevice::create($data + ['status' => 'Active']);
        return response()->json(['data' => $device], 201);
    }

    // List registered devices (admin UI)
    public function index(Request $request)
    {
        $devices = AttendanceDevice::orderBy('id')->get();
        return response()->json(['data' => $devices]);
    }

    // Soft-delete / deactivate a device
    public function destroy(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $force = $request->boolean('force');

        if ($force) {
            // permanent delete
            $device->delete();
            return response()->json(['message' => 'Device permanently deleted']);
        }

        // soft deactivate by status
        $device->update(['status' => 'Inactive']);
        return response()->json(['message' => 'Device deactivated']);
    }

    // Device webhook endpoint (no auth by default, can use secret header)
    public function webhook(Request $request)
    {
        $webhookOptions = $this->resolveWebhookOptions();
        $signatureAlgorithm = strtolower((string) ($webhookOptions['signature_algorithm'] ?? 'sha256'));
        if (!in_array($signatureAlgorithm, hash_algos(), true)) {
            $signatureAlgorithm = 'sha256';
        }

        $signatureHeaderName = (string) ($webhookOptions['signature_header'] ?? 'X-Device-Signature');
        $secretHeaderName = (string) ($webhookOptions['secret_header'] ?? 'X-Device-Secret');
        $payloadDeviceKey = (string) ($webhookOptions['payload_device_key'] ?? 'device_id');

        // Attempt to resolve device by identifier from payload
        $payload = $request->all();
        $identifier = $payload[$payloadDeviceKey] ?? $payload['device_id'] ?? $payload['identifier'] ?? null;
        $device = null;
        if ($identifier) {
            $device = AttendanceDevice::where('identifier', $identifier)->where('status', 'Active')->first();
        }

        // If device has a secret, verify HMAC signature header `X-Device-Signature`
        if ($device && $device->secret) {
            $signatureHeader = $request->header($signatureHeaderName) ?: $request->header('X-Device-Signature');
            $raw = $request->getContent();
            $signaturePrefix = $signatureAlgorithm . '=';
            if (empty($signatureHeader) || !str_starts_with($signatureHeader, $signaturePrefix)) {
                return response()->json(['message' => 'Missing or invalid signature header'], 403);
            }
            $expected = $signaturePrefix . hash_hmac($signatureAlgorithm, $raw, $device->secret);
            if (!hash_equals($expected, $signatureHeader)) {
                return response()->json(['message' => 'Invalid signature'], 403);
            }
            // decode payload after verifying raw body
            $payload = json_decode($raw, true) ?? $payload;
        } else {
            // Fallback: if no device secret, optional header `X-Device-Secret` may be used
            $secret = $request->header($secretHeaderName) ?: $request->header('X-Device-Secret');
            if ($secret) {
                $device = $device ?? AttendanceDevice::where('secret', $secret)->where('status', 'Active')->first();
            }
        }

        $this->normalizePayloadByWebhookOptions($payload, $webhookOptions);

        if (!$device) {
            return response()->json(['message' => 'Unknown device'], 403);
        }

        $ok = $this->service->processAttendanceEvent($payload);
        return response()->json(['ok' => (bool)$ok]);
    }

    // ZKTeco ADMS/iClock compatibility endpoint (uFace/K40 style devices)
    public function admsWebhook(Request $request)
    {
        $payload = $request->all();
        $identifier = $payload['SN']
            ?? $payload['sn']
            ?? $request->query('SN')
            ?? $request->query('sn')
            ?? $payload['device_id']
            ?? $payload['identifier']
            ?? null;

        if (!$identifier) {
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        $device = AttendanceDevice::query()
            ->where('identifier', $identifier)
            ->where('status', 'Active')
            ->first();

        if (!$device) {
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        $events = $this->extractAdmsEvents($request, (string) $identifier);
        foreach ($events as $event) {
            $this->service->processAttendanceEvent($event + ['source' => 'device']);
        }

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    private function resolveWebhookOptions(): array
    {
        $setting = WebSetting::query()->select('attendance_device_options')->latest('id')->first();
        $options = $setting?->attendance_device_options;

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($options)) {
            $options = [];
        }

        $webhookOptions = $options['webhook'] ?? [];
        return is_array($webhookOptions) ? $webhookOptions : [];
    }

    private function normalizePayloadByWebhookOptions(array &$payload, array $webhookOptions): void
    {
        $deviceKey = (string) ($webhookOptions['payload_device_key'] ?? 'device_id');
        $employeeKey = (string) ($webhookOptions['payload_employee_key'] ?? 'employee_code');
        $typeKey = (string) ($webhookOptions['payload_type_key'] ?? 'type');
        $timestampKey = (string) ($webhookOptions['payload_timestamp_key'] ?? 'timestamp');

        if (!array_key_exists('device_id', $payload) && array_key_exists($deviceKey, $payload)) {
            $payload['device_id'] = $payload[$deviceKey];
        }

        if (!array_key_exists('employee_code', $payload) && array_key_exists($employeeKey, $payload)) {
            $payload['employee_code'] = $payload[$employeeKey];
        }

        if (!array_key_exists('type', $payload) && array_key_exists($typeKey, $payload)) {
            $payload['type'] = $payload[$typeKey];
        }

        if (!array_key_exists('timestamp', $payload) && array_key_exists($timestampKey, $payload)) {
            $payload['timestamp'] = $payload[$timestampKey];
        }
    }

    private function extractAdmsEvents(Request $request, string $identifier): array
    {
        $events = [];
        $payload = $request->all();

        $pin = $payload['PIN'] ?? $payload['pin'] ?? $payload['user_id'] ?? $payload['employee_code'] ?? null;
        $timestamp = $payload['DateTime'] ?? $payload['datetime'] ?? $payload['timestamp'] ?? $payload['time'] ?? null;
        $status = $payload['Status'] ?? $payload['status'] ?? $payload['type'] ?? null;
        if ($pin && $timestamp) {
            $events[] = [
                'device_id' => $identifier,
                'employee_code' => (string) $pin,
                'type' => $this->mapAdmsType($status),
                'timestamp' => (string) $timestamp,
                'meta' => ['adms' => true],
            ];
        }

        $raw = trim((string) $request->getContent());
        if ($raw === '') {
            return $events;
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with(strtoupper($line), 'GET ') || str_contains($line, '=')) {
                continue;
            }

            // Typical ATTLOG row: PIN<TAB>DateTime<TAB>Status<TAB>Verify<TAB>WorkCode
            $parts = preg_split('/\t+/', $line) ?: [];
            if (count($parts) < 2) {
                continue;
            }

            $rowPin = trim((string) ($parts[0] ?? ''));
            $rowTs = trim((string) ($parts[1] ?? ''));
            $rowStatus = trim((string) ($parts[2] ?? ''));
            if ($rowPin === '' || $rowTs === '') {
                continue;
            }

            $events[] = [
                'device_id' => $identifier,
                'employee_code' => $rowPin,
                'type' => $this->mapAdmsType($rowStatus),
                'timestamp' => $rowTs,
                'meta' => [
                    'adms' => true,
                    'raw_line' => $line,
                ],
            ];
        }

        return $events;
    }

    private function mapAdmsType(mixed $status): string
    {
        if (is_string($status)) {
            $value = strtolower(trim($status));
            if (in_array($value, ['out', 'checkout', 'check-out'], true)) {
                return 'out';
            }

            if (is_numeric($value)) {
                // Common terminal status mapping where non-zero often indicates checkout.
                return ((int) $value) === 0 ? 'in' : 'out';
            }
        }

        if (is_numeric($status)) {
            return ((int) $status) === 0 ? 'in' : 'out';
        }

        return 'in';
    }
}
