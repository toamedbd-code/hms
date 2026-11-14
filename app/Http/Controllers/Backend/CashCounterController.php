<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminDetail;
use App\Models\CashCounterSession;
use App\Services\CashCounterService;
use App\Services\CashCounterLedgerService;
use App\Support\DefaultDeveloperManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashCounterController extends Controller
{
    public function __construct(
        protected CashCounterService $cashCounterService,
        protected CashCounterLedgerService $cashCounterLedgerService
    ) {
        $this->middleware('AdminAuth');
        $this->middleware('auth:admin');
        $this->middleware('permission:cash-counter')->except(['quickStart', 'quickClose', 'handoverPrint']);
        $this->middleware('permission:cash-counter|billing|billing-create')->only(['quickStart', 'quickClose', 'handoverPrint']);
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

        $admin = $this->resolveAdminActor();
        if (!$admin) {
            return redirect()->back()->with('errorMessage', 'Unable to resolve logged-in admin user.');
        }

        $existingOpenSession = $this->cashCounterService->findOpenSessionForAdmin($admin['id'], $admin['name']);
        if ($existingOpenSession) {
            return redirect()->route('backend.cash-counter.index', ['session_id' => $existingOpenSession->id])
                ->with('successMessage', 'Your counter session is already open.');
        }

        $data['user_name'] = $admin['name'];
        $data['counter_name'] = $this->resolveCounterNameForAdmin($admin['id'], (string) ($data['counter_name'] ?? 'Billing Counter'));
        $data['opened_at'] = now();
        $data['created_by'] = $admin['id'];

        $session = $this->cashCounterService->startSession($data);

        return redirect()->route('backend.cash-counter.index', ['session_id' => $session->id])
                ->with('successMessage', 'Counter session started.');
    }

    public function quickStart(Request $request): RedirectResponse
    {
        $admin = $this->resolveAdminActor();
        if (!$admin) {
            return redirect()->back()->with('errorMessage', 'Unable to resolve logged-in admin user.');
        }

        $existingOpenSession = $this->cashCounterService->findOpenSessionForAdmin($admin['id'], $admin['name']);
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
            'counter_name' => $this->resolveCounterNameForAdmin($admin['id'], (string) ($validated['counter_name'] ?? 'Billing Counter')),
            'user_name' => $admin['name'],
            'shift_name' => $validated['shift_name'] ?? 'General',
            'opening_amount' => (float) ($validated['opening_amount'] ?? 0),
            'opening_note' => $validated['opening_note'] ?? 'Started from Billing page',
            'opened_at' => now(),
            'created_by' => $admin['id'],
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

        $admin = $this->resolveAdminActor();
        if (!$admin) {
            return redirect()->back()->with('errorMessage', 'Unable to resolve logged-in admin user.');
        }

        $openSession = $this->resolveOwnedOpenSession(
            $admin,
            (int) ($validated['session_id'] ?? 0)
        );

        if (!$openSession) {
            return redirect()->back()->with('errorMessage', 'No open counter session found. Please Counter Start first.');
        }

        try {
            $closedSession = $this->cashCounterService->closeSession(
                (int) $openSession->id,
                (float) $validated['closing_amount'],
                $validated['note'] ?? 'Closed from Billing page'
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('errorMessage', $e->getMessage());
        }

        try {
            $this->cashCounterLedgerService->postCloseAdjustment($closedSession);
        } catch (\Throwable $e) {
            report($e);
        }

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

        $admin = $this->resolveAdminActor();
        if (!$admin) {
            return redirect()->back()->with('errorMessage', 'Unable to resolve logged-in admin user.');
        }

        $openSession = $this->resolveOwnedOpenSession($admin, (int) $data['session_id']);
        if (!$openSession) {
            return redirect()->route('backend.cash-counter.index')
                ->with('errorMessage', 'No open counter session found for your login.');
        }

        try {
            $closedSession = $this->cashCounterService->closeSession(
                (int) $openSession->id,
                (float) $data['closing_amount'],
                $data['note'] ?? null
            );
        } catch (\RuntimeException $e) {
            return redirect()->route('backend.cash-counter.index')
                ->with('errorMessage', $e->getMessage());
        }

        try {
            $this->cashCounterLedgerService->postCloseAdjustment($closedSession);
        } catch (\Throwable $e) {
            report($e);
        }

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

    protected function resolveAdminActor(): ?array
    {
        $admin = auth('admin')->user();
        $adminId = (int) ($admin?->id ?? 0);
        if ($adminId <= 0) {
            return null;
        }

        $adminName = trim((string) ($admin?->name ?? ''));
        if ($adminName === '') {
            $fallbackAdmin = Admin::query()->select('id', 'first_name', 'last_name')->find($adminId);
            $adminName = trim((string) (($fallbackAdmin?->first_name ?? '') . ' ' . ($fallbackAdmin?->last_name ?? '')));
        }

        return [
            'id' => $adminId,
            'name' => $adminName !== '' ? $adminName : 'Unknown',
        ];
    }

    protected function resolveOwnedOpenSession(array $admin, int $requestedSessionId = 0): ?CashCounterSession
    {
        if ($requestedSessionId > 0) {
            $session = CashCounterSession::query()
                ->where('id', $requestedSessionId)
                ->whereRaw('LOWER(status) = ?', ['open'])
                ->first();

            if ($session && ((int) ($session->created_by ?? 0) === $admin['id'] || empty($session->created_by))) {
                if (empty($session->created_by)) {
                    $session->created_by = $admin['id'];
                    $session->save();
                }

                return $session;
            }
        }

        return $this->cashCounterService->findOpenSessionForAdmin($admin['id'], $admin['name']);
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
