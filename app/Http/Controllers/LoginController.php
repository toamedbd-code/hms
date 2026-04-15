<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AdminService;
use App\Services\ActivityLogService;
use App\Traits\SystemTrait;
use App\Models\Subscription;
use App\Models\BkashSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class LoginController extends Controller
{
    use SystemTrait;

    protected $AdminService;
    public function __construct(AdminService $AdminService)
    {
        $this->AdminService = $AdminService;
    }
    public function login(LoginRequest $request)
    {
        $request->validated();

        $userInfo =  $this->AdminService->AdminExists(request()->email);

        if (!empty($userInfo)) {
            if ($userInfo->status != "Active") {
                return Inertia::render('Login', ['errorMessage' => 'Your Account Temporary Blocked. Please Contact Administrator.']);
            }

            // If subscription enforcement is enabled and subscription is inactive, show renew option and block login
            if ((bool) env('SUBSCRIPTION_ENFORCE', true)) {
                $sub = Subscription::getCurrent();
                $setting = BkashSetting::first();

                if (! $sub || ! $sub->isActive()) {
                    return Inertia::render('Login', [
                        'errorMessage' => 'Subscription inactive. Please renew subscription to log in.',
                        'showSubscriptionRenewal' => true,
                        'bkashEnabled' => config('payment.enabled') && ($setting->is_enabled ?? false),
                        'bkashMonthlyAmount' => config('subscription.monthly_amount', $setting->monthly_amount ?? 0),
                        'bkashYearlyAmount' => config('subscription.yearly_amount', 0),
                        'subscriptionDefaultPeriod' => config('subscription.default_period', 'monthly'),
                    ]);
                }
            }

            if (Hash::check(request()->password, $userInfo->password)) {
                Auth::guard('admin')->login($userInfo);
                $loginStartedAt = now()->toDateTimeString();
                session(['admin_login_started_at' => $loginStartedAt]);
                ActivityLogService::logLogin($userInfo->email ?? $userInfo->name ?? 'admin', $loginStartedAt);

                // session()->flash('message', 'Logged In Successfully');
                return redirect()->route('backend.dashboard')->with('successMessage', 'Logged In Successfully');
                // return Inertia::render('Backend/Dashboard')->with('warningMessage', 'Logged In Successfully');
            } else {
                return Inertia::render('Login')->with('warningMessage', 'Wrong Password. Please Enter Valid Password.');
            }
        } else {
            return Inertia::render('Login')->with('warningMessage', 'Invalid Username. Please Enter Valid Username.');
        }
    }
    function loginPage()
    {
        $enforce = (bool) env('SUBSCRIPTION_ENFORCE', true);
        $sub = Subscription::getCurrent();
        $active = $sub ? $sub->isActive() : false;
        $setting = BkashSetting::first();

        return Inertia::render('Login', [
            'subscriptionEnforced' => $enforce,
            'subscriptionActive' => $active,
            'bkashEnabled' => config('payment.enabled') && ($setting->is_enabled ?? false),
            'bkashMonthlyAmount' => config('subscription.monthly_amount', $setting->monthly_amount ?? 0),
            'bkashYearlyAmount' => config('subscription.yearly_amount', 0),
            'subscriptionDefaultPeriod' => config('subscription.default_period', 'monthly'),
        ]);
    }

    function logout()
    {
        $currentUser = auth('admin')->user();
        $loginStartedAt = session('admin_login_started_at');

        if ($currentUser) {
            ActivityLogService::logLogout($currentUser->name ?? $currentUser->email ?? 'Admin', $loginStartedAt);
        }

        auth('admin')->logout();
        session()->forget('admin_login_started_at');

        session()->flush('message', "Successfully Logged Out.");

        return redirect()->route('backend.auth.login');
    }
}
