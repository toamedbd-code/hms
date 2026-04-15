<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DutyRoster;
use App\Services\AdminService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DutyRosterController extends Controller
{
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:dutyroaster-list');
        $this->adminService = $adminService;
    }

    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $staffSearch = $request->input('staff_search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            $periodStart = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $periodStart = now()->startOfMonth();
            $month = $periodStart->format('Y-m');
        }

        $start = $periodStart->toDateString();
        $end = $periodStart->endOfMonth()->toDateString();

        // if explicit date range filters are provided, override month range
        if (!empty($dateFrom) && !empty($dateTo)) {
            try {
                $fromDt = Carbon::parse($dateFrom)->toDateString();
                $toDt = Carbon::parse($dateTo)->toDateString();
                $start = $fromDt;
                $end = $toDt;
            } catch (\Throwable $e) {
                // ignore parse errors and keep month range
            }
        }

        $query = DutyRoster::with('staff')
            ->whereBetween('date', [$start, $end])
            ->orderBy('date');

        if (!empty($staffSearch)) {
            $search = trim($staffSearch);
            $query->whereHas('staff', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere(DB::raw("concat(first_name, ' ', last_name)"), 'like', "%{$search}%");
                })
                ->orWhere('id', $search)
                ->orWhereHas('details', function ($d) use ($search) {
                    $d->where('staff_id', 'like', "%{$search}%");
                });
            });
        }

            // log SQL and bindings to help debug why searches return no results
            try {
                Log::debug('DutyRoster search SQL', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);
            } catch (\Throwable $e) {
                // ignore logging errors
            }

        $matchedCount = $query->count();

        $rows = $query->get()
            ->groupBy(fn($item) => Carbon::parse($item->date)->format('d-m-Y'))
            ->map(fn($group) => $group->map(fn($r) => [
                'id' => $r->id,
                'staff_id' => $r->staff_id,
                'staff_name' => $r->staff?->name ?? 'N/A',
                'start_time' => $r->start_time,
                'end_time' => $r->end_time,
                'shift_name' => $r->shift_name,
                'note' => $r->note,
                'created_at' => optional($r->created_at)?->format('d-m-Y H:i:s'),
            ]));

        $staffList = $this->adminService->activeList();

        return Inertia::render('Backend/StaffAttendance/DutyRoster', [
            'pageTitle' => 'Duty Roster',
            'filters' => [
                'month' => $month,
                'staff_search' => $staffSearch,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'rows' => $rows,
            'matchedCount' => $matchedCount,
            'staffList' => $staffList,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_id' => 'required|exists:admins,id',
            'date' => 'nullable|date',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'shift_name' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data) {
            // If an explicit id is provided, update that single record
            if (!empty($data['id'])) {
                $row = DutyRoster::find($data['id']);
                if ($row) {
                    $row->start_time = $data['start_time'] ?? $row->start_time;
                    $row->end_time = $data['end_time'] ?? $row->end_time;
                    $row->shift_name = $data['shift_name'] ?? $row->shift_name;
                    $row->note = $data['note'] ?? $row->note;
                    // allow changing date or staff_id
                    if (!empty($data['date'])) {
                        $row->date = $data['date'];
                    }
                    if (!empty($data['staff_id'])) {
                        $row->staff_id = $data['staff_id'];
                    }
                    $row->status = 'Active';
                    $row->save();
                }

                return;
            }
            // If a range is provided, apply roster for each date in range
            if (!empty($data['date_from']) && !empty($data['date_to'])) {
                $from = Carbon::parse($data['date_from'])->startOfDay();
                $to = Carbon::parse($data['date_to'])->startOfDay();
                if ($from->gt($to)) {
                    // swap if from > to
                    [$from, $to] = [$to, $from];
                }

                $period = CarbonPeriod::create($from, $to);
                foreach ($period as $dt) {
                    $dateStr = $dt->toDateString();
                    DutyRoster::updateOrCreate([
                        'staff_id' => $data['staff_id'],
                        'date' => $dateStr,
                    ], array_merge([
                        'staff_id' => $data['staff_id'],
                        'date' => $dateStr,
                        'start_time' => $data['start_time'] ?? null,
                        'end_time' => $data['end_time'] ?? null,
                        'shift_name' => $data['shift_name'] ?? null,
                        'note' => $data['note'] ?? null,
                    ], ['status' => 'Active']));
                }

                return;
            }

            // Single date fallback
            $singleDate = $data['date'] ?? null;
            if ($singleDate) {
                DutyRoster::updateOrCreate([
                    'staff_id' => $data['staff_id'],
                    'date' => $singleDate,
                ], array_merge($data, ['status' => 'Active']));
            }
        });

        return back()->with('successMessage', 'Duty roster saved.');
    }

    public function destroy($id)
    {
        $row = DutyRoster::findOrFail($id);
        $row->delete();
        return back()->with('successMessage', 'Roster removed.');
    }

    /**
     * Printable roster view (opens as a separate page for printing)
     */
    public function print(Request $request)
    {
        $staffId = $request->input('staff_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $month = $request->input('month');

        if (!empty($dateFrom) && !empty($dateTo)) {
            $start = Carbon::parse($dateFrom)->toDateString();
            $end = Carbon::parse($dateTo)->toDateString();
        } elseif (!empty($month)) {
            try {
                $periodStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable $e) {
                $periodStart = now()->startOfMonth();
            }
            $start = $periodStart->toDateString();
            $end = $periodStart->endOfMonth()->toDateString();
        } else {
            // default to current month
            $periodStart = now()->startOfMonth();
            $start = $periodStart->toDateString();
            $end = $periodStart->endOfMonth()->toDateString();
        }

        $query = DutyRoster::with('staff')->whereBetween('date', [$start, $end])->orderBy('date');
        if (!empty($staffId)) {
            $query->where('staff_id', $staffId);
        }

        // group by date formatted as DD-MM-YYYY so the print view displays date-only
        $rows = $query->get()->groupBy(fn($item) => Carbon::parse($item->date)->format('d-m-Y'));

        return view('backend.staffattendance.duty_roster_print', compact('rows', 'start', 'end'));
    }
}
