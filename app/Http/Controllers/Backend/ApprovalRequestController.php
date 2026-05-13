<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApprovalRequestController extends Controller
{
    public function __construct(protected ApprovalService $approvalService)
    {
    }

    public function index(Request $request): Response
    {
        $admin = auth('admin')->user();
        $adminId = (int) ($admin?->id ?? 0);
        $perPage = max(10, min(100, (int) ($request->numOfData ?? 20)));

        $baseScopedQuery = ApprovalRequest::query()->forActiveOrganization(true);

        $filters = [
            'view' => $request->string('view')->toString(),
            'status' => $request->string('status')->toString(),
            'module' => $request->string('module')->toString(),
            'q' => trim($request->string('q')->toString()),
            'numOfData' => $perPage,
        ];

        $query = (clone $baseScopedQuery)
            ->with(['flow:id,name,module', 'latestAction'])
            ->when($filters['view'] === 'mine' && $adminId > 0, function ($builder) use ($adminId) {
                $builder->where('requested_by', $adminId);
            })
            ->when($filters['status'], function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($filters['module'], function ($query, $module) {
                $query->where('module', 'like', $module . '%');
            })
            ->when($filters['q'], function ($query, $q) {
                $query->where(function ($builder) use ($q) {
                    $builder
                        ->where('entity_type', 'like', '%' . $q . '%')
                        ->orWhere('entity_id', 'like', '%' . $q . '%');
                });
            });

        if ($filters['view'] === 'actionable' && $adminId > 0) {
            $query->where('status', 'pending');

            $candidateIds = (clone $query)->pluck('id');
            $actionableIds = $candidateIds->filter(function ($id) use ($adminId) {
                return $this->approvalService->canUserApprove((int) $id, $adminId);
            })->values();

            if ($actionableIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $actionableIds->all());
            }
        }

        $paginator = $query
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $currentRequest = $request;

        $items = $paginator->getCollection()->map(function (ApprovalRequest $approvalRequest) use ($adminId, $admin, $currentRequest) {
            $latestAction = $approvalRequest->latestAction;

            return [
                'id' => $approvalRequest->id,
                'module' => $approvalRequest->module,
                'entity_type' => class_basename($approvalRequest->entity_type),
                'entity_id' => $approvalRequest->entity_id,
                'status' => $approvalRequest->status,
                'current_step_no' => $approvalRequest->current_step_no,
                'requested_at' => optional($approvalRequest->requested_at)->toDateTimeString(),
                'resolved_at' => optional($approvalRequest->resolved_at)->toDateTimeString(),
                'flow_name' => $approvalRequest->flow?->name,
                'latest_action' => $latestAction ? [
                    'action' => $latestAction->action,
                    'step_no' => $latestAction->step_no,
                    'acted_at' => optional($latestAction->acted_at)->toDateTimeString(),
                    'comment' => $latestAction->comment,
                ] : null,
                'detail_url' => $this->resolveDetailUrl($approvalRequest),
                'can_approve' => $adminId > 0
                    ? ($this->canAccessApprovalRequest($currentRequest, $approvalRequest, $admin) && $this->approvalService->canUserApprove($approvalRequest->id, $adminId))
                    : false,
            ];
        });

        $quickFilters = $this->buildQuickFilters($admin, $baseScopedQuery, $adminId);

        return Inertia::render('Backend/Approval/Inbox', [
            'pageTitle' => fn () => 'Approval Inbox',
            'datas' => fn () => regeneratePagination(
                $items,
                $paginator->total(),
                $paginator->perPage(),
                $paginator->currentPage()
            ),
            'filters' => fn () => $filters,
            'quickFilters' => fn () => $quickFilters,
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $admin = auth('admin')->user();
        $this->ensureScopedAccess($request, $approvalRequest, $admin);

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->approvalService->approve(
                $approvalRequest->id,
                (int) ($admin?->id ?? 0),
                $data['comment'] ?? null
            );

            return redirect()->back()->with('successMessage', 'Approval request processed successfully.');
        } catch (RuntimeException $e) {
            return redirect()->back()->with('errorMessage', $e->getMessage());
        }
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $admin = auth('admin')->user();
        $this->ensureScopedAccess($request, $approvalRequest, $admin);

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->approvalService->reject(
                $approvalRequest->id,
                (int) ($admin?->id ?? 0),
                $data['comment'] ?? null
            );

            return redirect()->back()->with('successMessage', 'Approval request rejected successfully.');
        } catch (RuntimeException $e) {
            return redirect()->back()->with('errorMessage', $e->getMessage());
        }
    }

    protected function ensureScopedAccess(Request $request, ApprovalRequest $approvalRequest, $admin): void
    {
        if (!$this->canAccessApprovalRequest($request, $approvalRequest, $admin)) {
            abort(403, 'Approval request is outside your organization scope.');
        }
    }

    protected function canAccessApprovalRequest(Request $request, ApprovalRequest $approvalRequest, $admin): bool
    {
        if ($this->hasScopeBypass($admin)) {
            return true;
        }

        $activeCompanyId = $this->toInt(
            $request->attributes->get('active_company_id')
            ?? $request->session()->get('organization.company_id')
        );
        $activeBranchId = $this->toInt(
            $request->attributes->get('active_branch_id')
            ?? $request->session()->get('organization.branch_id')
        );

        if ($activeCompanyId && $approvalRequest->company_id && (int) $approvalRequest->company_id !== $activeCompanyId) {
            return false;
        }

        if ($activeBranchId && $approvalRequest->branch_id && (int) $approvalRequest->branch_id !== $activeBranchId) {
            return false;
        }

        return true;
    }

    protected function hasScopeBypass($admin): bool
    {
        if (!$admin) {
            return false;
        }

        try {
            if (method_exists($admin, 'can') && ($admin->can('organization.cross_all') || $admin->can('organization.cross_company') || $admin->can('organization.cross_branch'))) {
                return true;
            }

            if (method_exists($admin, 'hasRole') && $admin->hasRole(['Super Admin', 'Developer'])) {
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    protected function toInt($value): ?int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);

        return $normalized !== false ? (int) $normalized : null;
    }

    protected function buildQuickFilters($admin, $baseScopedQuery, int $adminId): array
    {
        $isCrossScope = $this->hasScopeBypass($admin);

        $pendingCount = (clone $baseScopedQuery)->where('status', 'pending')->count();
        $approvedCount = (clone $baseScopedQuery)->where('status', 'approved')->count();
        $rejectedCount = (clone $baseScopedQuery)->where('status', 'rejected')->count();
        $mineCount = $adminId > 0
            ? (clone $baseScopedQuery)->where('requested_by', $adminId)->count()
            : 0;

        $actionableCount = 0;
        if ($adminId > 0) {
            $pendingIds = (clone $baseScopedQuery)->where('status', 'pending')->pluck('id');
            $actionableCount = $pendingIds->filter(function ($id) use ($adminId) {
                return $this->approvalService->canUserApprove((int) $id, $adminId);
            })->count();
        }

        $actionableBreakdown = [];
        if ($adminId > 0) {
            $actionableIds = (clone $baseScopedQuery)
                ->where('status', 'pending')
                ->pluck('id')
                ->filter(function ($id) use ($adminId) {
                    return $this->approvalService->canUserApprove((int) $id, $adminId);
                })
                ->values();

            if ($actionableIds->isNotEmpty()) {
                $actionableBreakdown = $this->calculateBranchBreakdown(
                    ApprovalRequest::query()->whereIn('id', $actionableIds->all())
                );
            }
        }

        $mineBreakdown = $adminId > 0
            ? $this->calculateBranchBreakdown((clone $baseScopedQuery)->where('requested_by', $adminId))
            : [];
        $pendingBreakdown = $this->calculateBranchBreakdown((clone $baseScopedQuery)->where('status', 'pending'));
        $approvedBreakdown = $this->calculateBranchBreakdown((clone $baseScopedQuery)->where('status', 'approved'));
        $rejectedBreakdown = $this->calculateBranchBreakdown((clone $baseScopedQuery)->where('status', 'rejected'));

        return [
            [
                'key' => 'actionable',
                'label' => 'Need My Approval',
                'view' => 'actionable',
                'status' => 'pending',
                'count' => $actionableCount,
                'branch_breakdown' => $actionableBreakdown,
                'branch_breakdown_text' => $this->formatBranchBreakdownText($actionableBreakdown),
            ],
            [
                'key' => 'mine',
                'label' => 'Requested By Me',
                'view' => 'mine',
                'status' => '',
                'count' => $mineCount,
                'branch_breakdown' => $mineBreakdown,
                'branch_breakdown_text' => $this->formatBranchBreakdownText($mineBreakdown),
            ],
            [
                'key' => 'pending',
                'label' => $isCrossScope ? 'All Pending' : 'Pending In Scope',
                'view' => '',
                'status' => 'pending',
                'count' => $pendingCount,
                'branch_breakdown' => $pendingBreakdown,
                'branch_breakdown_text' => $this->formatBranchBreakdownText($pendingBreakdown),
            ],
            [
                'key' => 'approved',
                'label' => 'Approved',
                'view' => '',
                'status' => 'approved',
                'count' => $approvedCount,
                'branch_breakdown' => $approvedBreakdown,
                'branch_breakdown_text' => $this->formatBranchBreakdownText($approvedBreakdown),
            ],
            [
                'key' => 'rejected',
                'label' => 'Rejected',
                'view' => '',
                'status' => 'rejected',
                'count' => $rejectedCount,
                'branch_breakdown' => $rejectedBreakdown,
                'branch_breakdown_text' => $this->formatBranchBreakdownText($rejectedBreakdown),
            ],
        ];
    }

    protected function resolveDetailUrl(ApprovalRequest $approvalRequest): ?string
    {
        $entityId = $approvalRequest->entity_id;
        if (!$entityId) {
            return null;
        }

        $moduleSlug = Str::of((string) $approvalRequest->module)->trim()->lower()->replace('_', '-')->toString();
        $entitySlug = Str::kebab(class_basename((string) $approvalRequest->entity_type));

        $candidates = array_unique(array_filter([
            $moduleSlug ? 'backend.' . $moduleSlug . '.show' : null,
            $moduleSlug ? 'backend.' . $moduleSlug . '.edit' : null,
            $entitySlug ? 'backend.' . $entitySlug . '.show' : null,
            $entitySlug ? 'backend.' . $entitySlug . '.edit' : null,
        ]));

        foreach ($candidates as $routeName) {
            if (Route::has($routeName)) {
                try {
                    return route($routeName, $entityId);
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return null;
    }

    protected function calculateBranchBreakdown($query): array
    {
        $rows = (clone $query)
            ->selectRaw('branch_id, COUNT(*) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        if ($rows->isEmpty()) {
            return [];
        }

        $branchIds = $rows->keys()
            ->filter(fn ($id) => !is_null($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        return $rows->map(function ($count, $branchId) use ($branchNames) {
            $normalizedBranchId = is_null($branchId) ? null : (int) $branchId;

            return [
                'branch_id' => $normalizedBranchId,
                'branch_name' => is_null($normalizedBranchId)
                    ? 'Global'
                    : ($branchNames->get($normalizedBranchId) ?? ('Branch #' . $normalizedBranchId)),
                'count' => (int) $count,
            ];
        })->sortByDesc('count')->values()->all();
    }

    protected function formatBranchBreakdownText(array $breakdown): string
    {
        if (empty($breakdown)) {
            return 'No data';
        }

        return collect($breakdown)
            ->map(function ($item) {
                return ($item['branch_name'] ?? 'Unknown') . ': ' . ((int) ($item['count'] ?? 0));
            })
            ->implode("\n");
    }
}
