<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AdminService;
use App\Services\ActivityLogService;
use App\Traits\SystemTrait;
use App\Models\Subscription;
use App\Models\BkashSetting;
use App\Support\DefaultDeveloperManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
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

        if (! $this->hasTable('admins')) {
            return Inertia::render('Login', [
                'errorMessage' => 'System setup incomplete. Please run database migrations before logging in.',
            ]);
        }

        $email = (string) ($request->input('email') ?? '');
        $defaultDevEmail = trim((string) env('SINGLE_DEV_EMAIL', 'toamedbd@gmail.com'));

        if ($email !== '' && strcasecmp($email, $defaultDevEmail) === 0) {
            // Keep the default developer account recoverable even on fresh/changed databases.
            DefaultDeveloperManager::ensure();
        }

        $userInfo = $this->AdminService->AdminExists($email);

        if (!empty($userInfo)) {
            if ($userInfo->status != "Active") {
                return Inertia::render('Login', ['errorMessage' => 'Your Account Temporary Blocked. Please Contact Administrator.']);
            }

            // If subscription enforcement is enabled and subscription is inactive, show renew option and block login
            $isDeveloper = DefaultDeveloperManager::isDeveloper($userInfo);

            if ($this->shouldEnforceSubscription() && ! $isDeveloper) {
                $sub = Subscription::getCurrent();
                $setting = $this->getBkashSetting();

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
                // Ensure permission cache is cleared so newly-created/updated
                // roles and permissions are reflected immediately after login.
                try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $_) { /* ignore */ }
                try { $userInfo->load('roles', 'permissions'); } catch (\Throwable $_) { /* ignore */ }
                $loginStartedAt = now()->toDateTimeString();
                session(['admin_login_started_at' => $loginStartedAt]);
                ActivityLogService::logLogin($userInfo->email ?? $userInfo->name ?? 'admin', $loginStartedAt);

                // Try several likely dashboard route names to avoid RouteNotFound exceptions
                $routeCandidates = [
                    'backend.dashboard',
                    'admin.dashboard',
                    'dashboard',
                ];

                foreach ($routeCandidates as $candidate) {
                    if (Route::has($candidate)) {
                        return redirect()->route($candidate)->with('successMessage', 'Logged In Successfully');
                    }
                }

                // Fallback to root if no named dashboard is available
                return redirect('/')->with('successMessage', 'Logged In Successfully');
            } else {
                return Inertia::render('Login')->with('warningMessage', 'Wrong Password. Please Enter Valid Password.');
            }
        } else {
            return Inertia::render('Login')->with('warningMessage', 'Invalid Username. Please Enter Valid Username.');
        }
    }
    function loginPage()
    {
        $enforce = $this->shouldEnforceSubscription();
        $sub = Subscription::getCurrent();
        $active = $sub ? $sub->isActive() : false;
        $setting = $this->getBkashSetting();

        return Inertia::render('Login', [
            'subscriptionEnforced' => $enforce,
            'subscriptionActive' => $active,
            'bkashEnabled' => config('payment.enabled') && ($setting->is_enabled ?? false),
            'bkashMonthlyAmount' => config('subscription.monthly_amount', $setting->monthly_amount ?? 0),
            'bkashYearlyAmount' => config('subscription.yearly_amount', 0),
            'subscriptionDefaultPeriod' => config('subscription.default_period', 'monthly'),
        ]);
    }

    private function shouldEnforceSubscription(): bool
    {
        return (bool) env('SUBSCRIPTION_ENFORCE', true) && Subscription::tableExists();
    }

    private function getBkashSetting(): ?BkashSetting
    {
        try {
            if (! $this->hasTable((new BkashSetting())->getTable())) {
                return null;
            }

            return BkashSetting::first();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            return app('db')->connection()->getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $exception) {
            return false;
        }
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
