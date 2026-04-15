<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkSmsRequest;
use App\Jobs\SendBulkSmsJob;
use App\Models\Patient;
use App\Models\SmsLog;
use App\Services\BulkSmsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class BulkSmsController extends Controller
{
    public function __construct(private readonly BulkSmsService $bulkSmsService)
    {
        $this->middleware('AdminAuth');
    }

    public function index(Request $request)
    {
        $filters = [
            'status' => (string) $request->input('status', ''),
            'batch_id' => (string) $request->input('batch_id', ''),
            'phone' => (string) $request->input('phone', ''),
            'from_date' => (string) $request->input('from_date', ''),
            'to_date' => (string) $request->input('to_date', ''),
        ];

        $baseQuery = SmsLog::query()
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['batch_id'] !== '', fn ($q) => $q->where('batch_id', 'like', '%' . $filters['batch_id'] . '%'))
            ->when($filters['phone'] !== '', fn ($q) => $q->where('phone', 'like', '%' . $filters['phone'] . '%'))
            ->when($filters['from_date'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['from_date']))
            ->when($filters['to_date'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['to_date']));

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'queued' => (clone $baseQuery)->where('status', 'queued')->count(),
            'retrying' => (clone $baseQuery)->where('status', 'retrying')->count(),
            'sent' => (clone $baseQuery)->where('status', 'sent')->count(),
            'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
        ];

        $logs = $baseQuery
            ->latest('id')
            ->paginate(20);

        return Inertia::render('Backend/BulkSms/Index', [
            'pageTitle' => 'Bulk SMS',
            'activePatientCount' => Patient::query()
                ->whereNull('deleted_at')
                ->where('status', 'Active')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->count(),
            'logs' => $logs,
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function send(BulkSmsRequest $request)
    {
        $validated = $request->validated();

        $query = Patient::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if (($validated['recipient_scope'] ?? 'all_active') === 'selected') {
            $ids = collect(explode(',', (string) ($validated['patient_ids'] ?? '')))
                ->map(fn ($id) => (int) trim($id))
                ->filter(fn ($id) => $id > 0)
                ->values();

            if ($ids->isEmpty()) {
                return redirect()->back()->with('errorMessage', 'Selected mode-এ patient ID দিন।');
            }

            $query->whereIn('id', $ids->all());
        }

        $phones = $this->bulkSmsService->sanitizePhoneNumbers($query->pluck('phone')->all());

        if (empty($phones)) {
            return redirect()->back()->with('errorMessage', 'Valid recipient phone number পাওয়া যায়নি।');
        }

        $batchId = (string) Str::uuid();
        $adminId = auth()->guard('admin')->id();
        $message = (string) $validated['message'];

        foreach ($phones as $phone) {
            SendBulkSmsJob::dispatch($batchId, (string) $phone, $message, $adminId ? (int) $adminId : null);
        }

        $total = count($phones);

        return redirect()->back()->with('successMessage', "{$total} SMS queue-তে পাঠানো হয়েছে। Batch: {$batchId}");
    }
}
