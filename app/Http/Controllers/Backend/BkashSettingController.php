<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BkashSetting;

class BkashSettingController extends Controller
{
    public function index()
    {
        $setting = BkashSetting::first();

        return view('backend.settings.bkash', [
            'setting' => $setting,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'app_key' => 'nullable|string',
            'app_secret' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'merchant_number' => 'nullable|string',
            'is_sandbox' => 'nullable',
            'is_enabled' => 'nullable',
            'monthly_amount' => 'nullable|numeric|min:0',
        ]);

        $setting = BkashSetting::first();

        // normalize values to avoid inserting NULL into NOT NULL columns
        $data['monthly_amount'] = isset($data['monthly_amount']) ? $data['monthly_amount'] : 0;
        $data['is_sandbox'] = $request->has('is_sandbox') ? (bool) $request->input('is_sandbox') : ($data['is_sandbox'] ?? true);
        $data['is_enabled'] = $request->has('is_enabled') ? (bool) $request->input('is_enabled') : ($data['is_enabled'] ?? false);

        if ($setting) {
            $setting->update($data);
        } else {
            $setting = BkashSetting::create($data);
        }

        return redirect()
            ->route('backend.settings.payment.bkash')
            ->with('success', 'bKash settings updated.');
    }
}
