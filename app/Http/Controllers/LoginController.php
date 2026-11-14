<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AdminService;
use App\Services\ActivityLogService;
use App\Traits\SystemTrait;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\BkashSetting;
use App\Support\DefaultDeveloperManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

        $userInfo = $this->AdminService->AdminExists($email);

        if (empty($userInfo) && $email !== '' && strcasecmp($email, $defaultDevEmail) === 0) {
            DefaultDeveloperManager::ensure();
            $userInfo = $this->AdminService->AdminExists($email);
        }

        if (!empty($userInfo)) {
            if ($userInfo->status != "Active") {
                return Inertia::render('Login', ['errorMessage' => 'Your Account Temporary Blocked. Please Contact Administrator.']);
            }

            $isDeveloperUser = DefaultDeveloperManager::isDeveloper($userInfo);

            // Developer users should bypass subscription enforcement and remain able to log in.
            if ($this->shouldEnforceSubscription() && ! $isDeveloperUser) {
                $sub = Subscription::getCurrent();
                $setting = $this->getBkashSetting();

                if (! $sub || ! $sub->isActive()) {
                    return Inertia::render('Login', [
                        'errorMessage' => 'Subscription inactive. Please renew subscription to log in.',
                        'showSubscriptionRenewal' => true,
                        'bkashEnabled' => $this->shouldShowSubscriptionPayment($setting),
                        'bkashMonthlyAmount' => $this->monthlyRenewalAmount($sub, $setting),
                        'bkashMonthlyPayableAmount' => $this->gatewayAmount($this->monthlyRenewalAmount($sub, $setting)),
                        'bkashRenewalMonths' => $sub ? $sub->renewalMonthsDue() : 1,
                        'bkashYearlyAmount' => $this->resolvedYearlyAmount($setting),
                        'bkashYearlyPayableAmount' => $this->gatewayAmount($this->resolvedYearlyAmount($setting)),
                        'subscriptionDefaultPeriod' => config('subscription.default_period', 'monthly'),
                        'isSandbox' => (bool) ($setting?->is_sandbox ?? true),
                        'subscriptionEnforced' => true,
                        'subscriptionActive' => false,
                    ]);
                }
            }

            if (Hash::check(request()->password, $userInfo->password)) {
                // Double-check subscription enforcement after credential verification
                // to prevent races or alternate flows that bypass the earlier check.
                if ($this->shouldEnforceSubscription() && ! DefaultDeveloperManager::isDeveloper($userInfo)) {
                    $sub = Subscription::getCurrent();
                    $setting = $this->getBkashSetting();
                    if (! $sub || ! $sub->isActive()) {
                        return Inertia::render('Login', [
                            'errorMessage' => 'Subscription inactive. Please renew subscription to log in.',
                            'showSubscriptionRenewal' => true,
                            'bkashEnabled' => $this->shouldShowSubscriptionPayment($setting),
                            'bkashMonthlyAmount' => $this->monthlyRenewalAmount($sub, $setting),
                            'bkashMonthlyPayableAmount' => $this->gatewayAmount($this->monthlyRenewalAmount($sub, $setting)),
                            'bkashRenewalMonths' => $sub ? $sub->renewalMonthsDue() : 1,
                            'bkashYearlyAmount' => $this->resolvedYearlyAmount($setting),
                            'bkashYearlyPayableAmount' => $this->gatewayAmount($this->resolvedYearlyAmount($setting)),
                            'subscriptionDefaultPeriod' => config('subscription.default_period', 'monthly'),
                            'isSandbox' => (bool) ($setting?->is_sandbox ?? true),
                            'subscriptionEnforced' => true,
                            'subscriptionActive' => false,
                        ]);
                    }
                }

                // Get remember value from request - supports both 'on' string and boolean
                $remember = $request->boolean('remember');
                Auth::guard('admin')->login($userInfo, $remember);
                // Ensure permission cache is cleared so newly-created/updated
                // roles and permissions are reflected immediately after login.
                try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $_) { /* ignore */ }
                try { $userInfo->load('roles', 'permissions'); } catch (\Throwable $_) { /* ignore */ }
                $loginStartedAt = now()->toDateTimeString();
                session(['admin_login_started_at' => $loginStartedAt]);
                if (!DefaultDeveloperManager::isDeveloper($userInfo)) {
                    ActivityLogService::logLogin($userInfo->email ?? $userInfo->name ?? 'admin', $loginStartedAt);
                } else {
                    Cache::forget('activity.log.alerts.' . now()->toDateString());
                }

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
    function loginPage(\Illuminate\Http\Request $request)
    {
        $enforce = $this->shouldEnforceSubscription();
        $sub = Subscription::getCurrent();
        $active = $sub ? $sub->isActive() : false;
        $setting = $this->getBkashSetting();
        $bkashEnabled = $this->shouldShowSubscriptionPayment($setting);
        $showSubscriptionRenewal = $enforce && Subscription::tableExists() && ! $active;
        $renewalMonths = $sub ? $sub->renewalMonthsDue() : 1;
        $subscriptionDaysRemaining = $sub?->daysRemaining() ?? 0;

        $pendingPaymentId = session('bkash_pending_payment_id');
        if ($pendingPaymentId) {
            $pendingPayment = Payment::find($pendingPaymentId);
            if (! $pendingPayment || $pendingPayment->provider !== 'bkash' || ! in_array($pendingPayment->status, ['initiated', 'pending'], true)) {
                session()->forget('bkash_pending_payment_id');
                $pendingPaymentId = null;
            } elseif ($pendingPayment->created_at && $pendingPayment->created_at->lt(now()->subMinutes(30))) {
                session()->forget('bkash_pending_payment_id');
                $pendingPaymentId = null;
            }
        }

        // Check if user has a valid remember token and pre-fill email
        $rememberEmail = '';
        $hasValidRememberToken = false;
        try {
            // Try to get user from remember token
            auth('admin')->viaRemember();
            if (auth('admin')->check()) {
                $rememberEmail = auth('admin')->user()->email;
                $hasValidRememberToken = true;
                // Don't actually log them in here - the middleware will handle that
                auth('admin')->logout();
            }
        } catch (\Exception $e) {
            // Invalid or expired remember token, just ignore
        }

        return Inertia::render('Login', [
            'subscriptionEnforced' => $enforce,
            'subscriptionActive' => $active,
            'subscriptionDaysRemaining' => $subscriptionDaysRemaining,
            'showSubscriptionRenewal' => $showSubscriptionRenewal,
            'bkashEnabled' => $bkashEnabled,
            'bkashMonthlyAmount' => $this->monthlyRenewalAmount($sub, $setting),
            'bkashMonthlyPayableAmount' => $this->gatewayAmount($this->monthlyRenewalAmount($sub, $setting)),
            'bkashRenewalMonths' => $renewalMonths,
            'bkashYearlyAmount' => $this->resolvedYearlyAmount($setting),
            'bkashYearlyPayableAmount' => $this->gatewayAmount($this->resolvedYearlyAmount($setting)),
            'subscriptionDefaultPeriod' => config('subscription.default_period', 'monthly'),
            'isSandbox' => (bool) ($setting?->is_sandbox ?? true),
            'pendingBkashPaymentId' => $pendingPaymentId,
            'successMessage' => session('successMessage'),
            'errorMessage' => session('errorMessage'),
            'warningMessage' => session('warningMessage'),
            'rememberEmail' => $rememberEmail,
            'hasValidRememberToken' => $hasValidRememberToken,
        ]);
    }

    private function shouldEnforceSubscription(): bool
    {
        return (bool) env('SUBSCRIPTION_ENFORCE', true) && Subscription::tableExists();
    }

    private function shouldShowSubscriptionPayment(?BkashSetting $setting = null): bool
    {
        $paymentEnabled = config('payment.enabled') && ($setting ? (bool) ($setting->is_enabled ?? false) : true);
        $monthlyAmount = $this->resolvedMonthlyAmount($setting);
        $yearlyAmount = $this->resolvedYearlyAmount($setting);

        return $paymentEnabled && ($monthlyAmount > 0 || $yearlyAmount > 0);
    }

    private function monthlyRenewalAmount(?Subscription $subscription, ?BkashSetting $setting): float
    {
        $monthlyAmount = $this->resolvedMonthlyAmount($setting);
        $monthsDue = $subscription ? $subscription->renewalMonthsDue() : 1;

        return round($monthlyAmount * $monthsDue, 2);
    }

    /**
     * Resolve monthly subscription amount.
     * Priority: DB (bkash_settings.monthly_amount) > .env > config default.
     * This prevents stale config:cache from overriding the admin-set DB value.
     */
    private function resolvedMonthlyAmount(?BkashSetting $setting): float
    {
        if ($setting && (float) $setting->monthly_amount > 0) {
            return (float) $setting->monthly_amount;
        }

        return (float) config('subscription.monthly_amount', 0);
    }

    private function resolvedYearlyAmount(?BkashSetting $setting): float
    {
        $yearly = (float) config('subscription.yearly_amount', 0);
        if ($yearly <= 0 && $setting && (float) $setting->monthly_amount > 0) {
            return round((float) $setting->monthly_amount * 12, 2);
        }

        return $yearly;
    }

    private function gatewayAmount(float $baseAmount): float
    {
        $feePercent = (float) config('subscription.gateway_fee_percent', 1.5);

        return round($baseAmount * (1 + ($feePercent / 100)), 2);
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

    public function logout(\Illuminate\Http\Request $request)
    {
        $currentUser = auth('admin')->user();
        $loginStartedAt = session('admin_login_started_at');

        if ($currentUser && !DefaultDeveloperManager::isDeveloper($currentUser)) {
            ActivityLogService::logLogout($currentUser->name ?? $currentUser->email ?? 'Admin', $loginStartedAt);
        }

        $rememberCookieName = auth('admin')->getRecallerName();

        auth('admin')->logout();
        $request->session()->forget('admin_login_started_at');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = redirect()->route('backend.auth.login2');
        $response->withCookie(cookie()->forget($rememberCookieName));
        $request->session()->flash('message', 'সফলভাবে লগ আউট হয়েছেন।');

        return $response;
    }
}
