<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminDetail;
use App\Models\CashCounterSession;
use App\Services\CashCounterService;
use App\Support\DefaultDeveloperManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CashCounterController extends Controller
{
    public function __construct(protected CashCounterService $cashCounterService)
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:cash-counter');
    }

    public function index(Request $request): Response
    {
        $currentAdminId = (int) auth('admin')->id();
        $openSessions = CashCounterSession::query()
            ->whereRaw('LOWER(status) = ?', ['open'])
            ->where('created_by', $currentAdminId)
            ->latest('opened_at')
            ->get();
        $selectedSessionId = (int) $request->get('session_id', 0);
        $selectedSession = $selectedSessionId
            ? $openSessions->firstWhere('id', $selectedSessionId)
            : $openSessions->first();

        $sessionSummary = null;
        if ($selectedSession) {
            $sessionSummary = $this->cashCounterService->getSummary($selectedSession->id);
        }

        return Inertia::render('Backend/CashCounter/Index', [
            'sessions' => $openSessions,
            'selectedSession' => $selectedSession,
            'sessionSummary' => $sessionSummary,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'counter_name' => 'required|string|max:255',
            'user_name' => 'nullable|string|max:255',
            'shift_name' => 'required|string|max:255',
            'opening_amount' => 'nullable|numeric|min:0',
            'opening_note' => 'nullable|string',
        ]);

        $adminId = (int) auth()->guard('admin')->id();
        $data['user_name'] = (string) (auth('admin')->user()?->name ?? 'Unknown');
        $data['counter_name'] = $this->resolveCounterNameForAdmin($adminId, (string) ($data['counter_name'] ?? 'Billing Counter'));

        $data['opened_at'] = now();
        $data['created_by'] = $adminId;

        $session = $this->cashCounterService->startSession($data);

        return redirect()->route('backend.cash-counter.index', ['session_id' => $session->id])
                ->with('successMessage', 'Counter session started.');
    }

    public function quickStart(Request $request): RedirectResponse
    {
        $requestUser = $request->user();
        $adminId = (int) ($requestUser?->id ?? 0);
        $fallbackAdmin = $adminId > 0 ? Admin::query()->select('id', 'first_name', 'last_name')->find($adminId) : null;
        $fallbackName = trim((string) (($fallbackAdmin?->first_name ?? '') . ' ' . ($fallbackAdmin?->last_name ?? '')));
        $adminName = (string) ($requestUser?->name ?? ($fallbackName !== '' ? $fallbackName : 'Unknown'));

        if ($adminId <= 0) {
            return redirect()->back()->with('errorMessage', 'Unable to resolve logged-in admin user.');
        }

        $existingOpenSession = CashCounterSession::query()
            ->whereRaw('LOWER(status) = ?', ['open'])
            ->where(function ($query) use ($adminId, $adminName) {
                $query->where('created_by', $adminId);

                if ($adminName !== '') {
                    $query->orWhere(function ($subQuery) use ($adminName) {
                        $subQuery->whereNull('created_by')
                            ->where('user_name', $adminName);
                    });
                }
            })
            ->latest('opened_at')
            ->first();

        if ($existingOpenSession) {
            return redirect()->back()->with('successMessage', 'Your counter session is already open.');
        }

        $validated = $request->validate([
            'opening_amount' => 'nullable|numeric|min:0',
            'opening_note' => 'nullable|string',
            'counter_name' => 'nullable|string|max:255',
            'shift_name' => 'nullable|string|max:255',
        ]);

        $session = $this->cashCounterService->startSession([
            'counter_name' => $this->resolveCounterNameForAdmin($adminId, (string) ($validated['counter_name'] ?? 'Billing Counter')),
            'user_name' => $adminName,
            'shift_name' => $validated['shift_name'] ?? 'General',
            'opening_amount' => (float) ($validated['opening_amount'] ?? 0),
            'opening_note' => $validated['opening_note'] ?? 'Started from Billing page',
            'opened_at' => now(),
            'created_by' => $adminId,
        ]);

        return redirect()
            ->back()
            ->with('successMessage', 'Counter started for ' . $session->user_name . '.');
    }

    public function quickClose(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => 'nullable|integer',
            'closing_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $requestUser = $request->user();
        $adminId = (int) ($requestUser?->id ?? 0);
        $fallbackAdmin = $adminId > 0 ? Admin::query()->select('id', 'first_name', 'last_name')->find($adminId) : null;
        $fallbackName = trim((string) (($fallbackAdmin?->first_name ?? '') . ' ' . ($fallbackAdmin?->last_name ?? '')));
        $adminName = (string) ($requestUser?->name ?? $fallbackName);

        if ($adminId <= 0) {
            return redirect()->back()->with('errorMessage', 'Unable to resolve logged-in admin user.');
        }

        $openSession = null;
        $requestedSessionId = (int) ($validated['session_id'] ?? 0);

        if ($requestedSessionId > 0) {
            $openSession = CashCounterSession::query()
                ->where('id', $requestedSessionId)
                ->whereRaw('LOWER(status) = ?', ['open'])
                ->first();
        }

        if (!$openSession) {
            $openSession = CashCounterSession::query()
                ->whereRaw('LOWER(status) = ?', ['open'])
                ->where(function ($query) use ($adminId, $adminName) {
                    $query->where('created_by', $adminId);

                    if ($adminName !== '') {
                        $query->orWhere(function ($subQuery) use ($adminName) {
                            $subQuery->whereNull('created_by')
                                ->where('user_name', $adminName);
                        });
                    }
                })
                ->latest('opened_at')
                ->first();
        }

        if (!$openSession) {
            try {
                Log::info('cash-counter.quick-close.auto-start', [
                    'admin_id' => $adminId,
                    'admin_name' => $adminName,
                    'requested_session_id' => $requestedSessionId,
                ]);
            } catch (\Throwable $e) {
                // ignore logging failures
            }

            $openSession = $this->cashCounterService->startSession([
                'counter_name' => $this->resolveCounterNameForAdmin($adminId, 'Billing Counter'),
                'user_name' => $adminName !== '' ? $adminName : 'Unknown',
                'shift_name' => 'General',
                'opening_amount' => 0,
                'opening_note' => 'Auto-started from close request',
                'opened_at' => now()->startOfDay(),
                'created_by' => $adminId,
            ]);
        }

        $closedSession = $this->cashCounterService->closeSession(
            (int) $openSession->id,
            (float) $validated['closing_amount'],
            $validated['note'] ?? 'Closed from Billing page'
        );

        $printUrl = route('backend.cash-counter.handover-print', ['sessionId' => $closedSession->id]);

        return redirect()
            ->back()
            ->with('successMessage', 'Counter closed successfully.')
            ->with('cashCounterPrintUrl', $printUrl);
    }

    public function input(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session_id' => 'required|exists:cash_counter_sessions,id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        $this->cashCounterService->recordInput((int) $data['session_id'], (float) $data['amount'], $data['note'] ?? null);

        return redirect()->route('backend.cash-counter.index', ['session_id' => $data['session_id']])
                ->with('successMessage', 'Cash input recorded.');
    }

    public function handover(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_session_id' => 'required|exists:cash_counter_sessions,id',
            'to_session_id' => 'required|different:from_session_id|exists:cash_counter_sessions,id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        $this->cashCounterService->recordHandover(
            (int) $data['from_session_id'], 
            (float) $data['amount'], 
            (int) $data['to_session_id'], 
            $data['note'] ?? null
        );

        return redirect()->route('backend.cash-counter.index', ['session_id' => $data['from_session_id']])
                ->with('successMessage', 'Cash handover recorded.');
    }

            public function close(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session_id' => 'required|exists:cash_counter_sessions,id',
            'closing_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $closedSession = $this->cashCounterService->closeSession((int) $data['session_id'], (float) $data['closing_amount'], $data['note'] ?? null);

        $printUrl = route('backend.cash-counter.handover-print', ['sessionId' => $closedSession->id]);

        return redirect()->route('backend.cash-counter.index')
            ->with('successMessage', 'Counter closed.')
            ->with('cashCounterPrintUrl', $printUrl);
    }

    public function handoverPrint(int $sessionId)
    {
        $actor = auth('admin')->user();
        $actorAdminId = (int) ($actor?->id ?? 0);
        $includeAllUsers = DefaultDeveloperManager::isDeveloper($actor);

        $summary = $this->cashCounterService->getHandoverPrintSummary($sessionId, $actorAdminId, $includeAllUsers);

        return view('backend.cash_counter.handover_print', $summary);
    }

    protected function resolveCounterNameForAdmin(int $adminId, string $fallback = 'Billing Counter'): string
    {
        if ($adminId <= 0) {
            return trim($fallback) !== '' ? trim($fallback) : 'Billing Counter';
        }

        try {
            $detail = AdminDetail::query()
                ->with('department:id,name')
                ->where('admin_id', $adminId)
                ->first();

            $departmentName = trim((string) ($detail?->department?->name ?? ''));
            if ($departmentName !== '') {
                return $departmentName;
            }
        } catch (\Throwable $e) {
            // fallback to provided counter name
        }

        return trim($fallback) !== '' ? trim($fallback) : 'Billing Counter';
    }
}
