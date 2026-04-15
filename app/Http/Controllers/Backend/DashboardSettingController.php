<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:dashboard-setting', ['only' => ['edit', 'update']]);
    }

    public function edit()
    {
        $admin = auth()->guard('admin')->user();
        $detail = AdminDetail::firstOrCreate(['admin_id' => $admin->id]);

        return Inertia::render('Backend/DashboardSetting/Form', [
            'pageTitle' => 'Dashboard Filter Settings',
            'dashboardSetting' => [
                'filter_type' => $detail->dashboard_filter_type ?? 'daily',
                'filter_from' => $detail->dashboard_filter_from,
                'filter_to' => $detail->dashboard_filter_to,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $admin = auth()->guard('admin')->user();

        $validated = $request->validate([
            'filter_type' => 'required|in:daily,monthly,yearly,custom',
            'filter_from' => 'nullable|date|required_if:filter_type,custom',
            'filter_to' => 'nullable|date|required_if:filter_type,custom|after_or_equal:filter_from',
        ]);

        $detail = AdminDetail::firstOrCreate(['admin_id' => $admin->id]);

        $filterType = $validated['filter_type'];
        $detail->dashboard_filter_type = $filterType;

        if ($filterType === 'custom') {
            $detail->dashboard_filter_from = $validated['filter_from'];
            $detail->dashboard_filter_to = $validated['filter_to'];
        } else {
            $detail->dashboard_filter_from = null;
            $detail->dashboard_filter_to = null;
        }

        $detail->save();

        return redirect()
            ->back()
            ->with('successMessage', 'Dashboard filter settings updated successfully.');
    }
}
