<?php

use App\Http\Controllers\Backend\AdminController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\DashboardSettingController;
use App\Http\Controllers\Backend\PermissionController;
use App\Http\Controllers\Backend\RoleController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\Backend\JournalEntryController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Backend\PatientController;
use App\Http\Controllers\Backend\TpaController;
use App\Http\Controllers\Backend\EmployeeController;
use App\Http\Controllers\Backend\BillingController;
use App\Http\Controllers\Backend\AppoinmentController;
use App\Http\Controllers\Backend\OpdPatientController;
use App\Http\Controllers\Backend\IpdPatientController;
use App\Http\Controllers\Backend\IpdChargeController;

use App\Http\Controllers\Backend\PharmacyController;
use App\Http\Controllers\Backend\PathologyController;
use App\Http\Controllers\Backend\RadiologyController;
use App\Http\Controllers\Backend\BloodBankController;
use App\Http\Controllers\Backend\AmbulanceController;
use App\Http\Controllers\Backend\FrontOfficeController;
use App\Http\Controllers\Backend\BirthDeathRecordController;
use App\Http\Controllers\Backend\HumanResourceController;
use App\Http\Controllers\Backend\DutyRoasterController;
use App\Http\Controllers\Backend\AnnualCalendarController;
use App\Http\Controllers\Backend\ApplyLeaveController;
use App\Http\Controllers\Backend\ReferralController;
use App\Http\Controllers\Backend\FinanceController;
use App\Http\Controllers\Backend\AccountController;
use App\Http\Controllers\Backend\LedgerController;
use App\Http\Controllers\Backend\AccountAuditController;
use App\Http\Controllers\Backend\AccountApiController;
use App\Http\Controllers\Backend\LedgerApiController;
use App\Http\Controllers\Backend\InventoryController;
use App\Http\Controllers\Backend\CertificateController;
use App\Http\Controllers\Backend\ReportsController;
use App\Http\Controllers\Backend\SampleCollectionController;
use App\Http\Controllers\Backend\ReportingController;
use App\Http\Controllers\Backend\ReportSettingController;
use App\Http\Controllers\Backend\ReportDeliveryController;
use App\Http\Controllers\Backend\SetupController;
use App\Http\Controllers\Backend\DesignationController;
use App\Http\Controllers\Backend\DepartmentController;
use App\Http\Controllers\Backend\SpecialistController;
use App\Http\Controllers\Backend\RefundController;
use App\Http\Controllers\Backend\BloodIssueController;
use App\Http\Controllers\Backend\BloodComponentIssueController;
use App\Http\Controllers\Backend\BedTypeController;
use App\Http\Controllers\Backend\BedGroupController;
use App\Http\Controllers\Backend\FloorController;
use App\Http\Controllers\Backend\BedController;
use App\Http\Controllers\Backend\PathologyTestController;
use App\Http\Controllers\Backend\PathologyCategoryController;
use App\Http\Controllers\Backend\PathologyUnitController;
use App\Http\Controllers\Backend\PathologyParameterController;
use App\Http\Controllers\Backend\ChargeController;
use App\Http\Controllers\Backend\ChargeCategoryController;
use App\Http\Controllers\Backend\ChargeTypeController;
use App\Http\Controllers\Backend\ChargeTaxCategoryController;
use App\Http\Controllers\Backend\ChargeUnitTypeController;
use App\Http\Controllers\Backend\DefineLeaveController;
use App\Http\Controllers\Backend\MedicineGroupController;
use App\Http\Controllers\Backend\MedicineCompanyController;
use App\Http\Controllers\Backend\MedicineUnitController;
use App\Http\Controllers\Backend\DoseDurationController;
use App\Http\Controllers\Backend\InvoiceController;
use App\Http\Controllers\Backend\MedicineDoseIntervalController;
use App\Http\Controllers\Backend\MedicineDosageController;
use App\Http\Controllers\Backend\MedicineCategoryController;
use App\Http\Controllers\Backend\MedicineSupplierController;
use App\Http\Controllers\Backend\MedicineInventoryController;
use App\Http\Controllers\Backend\MedicinePurchaseController;
use App\Http\Controllers\Backend\SitePurchaseController;
use App\Http\Controllers\Backend\SupplierPaymentController;
use App\Http\Controllers\Backend\ProductReturnController;
use App\Http\Controllers\Backend\StockManagementController;
use App\Http\Controllers\Backend\ReferralCategoryController;

use App\Http\Controllers\Backend\ReferralPersonController;
use App\Http\Controllers\Backend\SymptomTypeController;


use App\Http\Controllers\Backend\InvoiceDesignController;
use App\Http\Controllers\Backend\LeaveTypeController;
use App\Http\Controllers\Backend\ReportController;
use App\Http\Controllers\Backend\DoctorSummaryController;
use App\Http\Controllers\Backend\WebSettingController;
use App\Http\Controllers\Backend\ProfileController;
use App\Http\Controllers\Backend\BkashSettingController;
use App\Http\Controllers\Backend\PayslipController;

use App\Http\Controllers\Backend\PharmacyBillController;
use App\Http\Controllers\Backend\CashCounterController;

Route::get('/test-upload', function () {
    return ini_get('upload_tmp_dir');
});

Route::prefix('cash-counter')->name('cash-counter.')->group(function () {
    Route::get('/', [CashCounterController::class, 'index'])->name('index');
    Route::get('/{sessionId}/handover-print', [CashCounterController::class, 'handoverPrint'])->name('handover-print');
    Route::post('/quick-start', [CashCounterController::class, 'quickStart'])->name('quick-start');
    Route::post('/quick-close', [CashCounterController::class, 'quickClose'])->name('quick-close');
    Route::post('/start', [CashCounterController::class, 'start'])->name('start');
    Route::post('/input', [CashCounterController::class, 'input'])->name('input');
    Route::post('/handover', [CashCounterController::class, 'handover'])->name('handover');
    Route::post('/close', [CashCounterController::class, 'close'])->name('close');
});

use App\Http\Controllers\Backend\StaffAttendanceController;
use App\Http\Controllers\Backend\AttendanceSyncController;
use App\Http\Controllers\Backend\FaceAttendanceController;

use App\Http\Controllers\Backend\ExpenseHeadController;


use App\Http\Controllers\Backend\ExpenseController;

use App\Http\Controllers\Backend\DueCollectController;
use App\Http\Controllers\Backend\BillingDoctorController;
use App\Http\Controllers\Backend\ChargeImportController;
use App\Http\Controllers\Backend\FinanceReportController;
use App\Http\Controllers\Backend\ActivityLogController;
use App\Http\Controllers\Backend\PathologyMachineIntegrationLogController;
use App\Http\Controllers\Backend\PharmacyStockReportController;
use App\Http\Controllers\Backend\BulkSmsController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\PatientPortal\PatientPortalController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\AssetController;
use App\Http\Controllers\Frontend\DebugController;
use App\Http\Controllers\Payment\BkashController;

//don't remove this comment from route namespace

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/services', [HomeController::class, 'services'])->name('website.services');
Route::get('/doctors', [HomeController::class, 'doctors'])->name('website.doctors');
Route::get('/facilities', [HomeController::class, 'facilities'])->name('website.facilities');
Route::get('/appointment', [HomeController::class, 'appointment'])->name('website.appointment');
Route::get('/contact', [HomeController::class, 'contact'])->name('website.contact');

Route::get('/language/{locale}', function (string $locale, Request $request) {
    $locale = strtolower(trim($locale));
    if (!in_array($locale, ['en', 'bn'], true)) {
        $locale = 'en';
    }

    session(['locale' => $locale]);
    app()->setLocale($locale);

    return redirect()->back()->withCookie(cookie('locale', $locale, 60 * 24 * 30));
})->name('language.switch');

// Serve storage files when symlink may be missing (fallback)
Route::get('/storage/{path}', [AssetController::class, 'storage'])->where('path', '.*');

// Debug route to inspect featured doctors image URLs
Route::get('/debug/featured-doctors', [DebugController::class, 'featuredDoctors'])->name('debug.featured.doctors');

// Debug route: render login page without AuthCheck middleware (for troubleshooting)
Route::get('/dev/login', function () {
    $enforce = (bool) env('SUBSCRIPTION_ENFORCE', true);
    $sub = \App\Models\Subscription::getCurrent();
    $active = $sub ? $sub->isActive() : false;
    $setting = \App\Models\BkashSetting::first();

    return \Inertia\Inertia::render('Login', [
        'subscriptionEnforced' => $enforce,
        'subscriptionActive' => $active,
        'bkashEnabled' => $setting->is_enabled ?? false,
        'bkashMonthlyAmount' => $setting->monthly_amount ?? 0,
    ]);
})->name('debug.login');

// Debug route: create or enable a sandbox bKash setting for local tests
Route::get('/dev/bkash-setup', function () {
    $setting = \App\Models\BkashSetting::first();
    if (! $setting) {
        $setting = \App\Models\BkashSetting::create([
            'is_enabled' => true,
            'is_sandbox' => true,
            'monthly_amount' => env('SUBSCRIPTION_MONTHLY_AMOUNT', 2000),
        ]);
    } else {
        $setting->is_enabled = true;
        $setting->is_sandbox = true;
        $setting->monthly_amount = env('SUBSCRIPTION_MONTHLY_AMOUNT', 2000);
        $setting->save();
    }

    return response()->json(['ok' => true, 'setting' => $setting]);
})->name('debug.bkash.setup');

// Debug route: inspect side menu generation for current or specified admin (local/dev only)
Route::get('/dev/debug-side-menus', function (Request $request) {
    if (!app()->environment('local') && !config('app.debug')) {
        abort(404);
    }

    $adminGuardCheck = auth()->guard('admin')->check();
    $adminGuardUser = auth()->guard('admin')->user();
    $requestAdminUser = $request->user('admin');
    $defaultUser = $request->user();

    $sideMenusCurrent = $adminGuardUser ? getSideMenus($adminGuardUser) : [];

    $email = $request->query('email');
    $sideMenusForEmail = null;
    $foundAdmin = null;
    if ($email) {
        $foundAdminModel = \App\Models\Admin::where('email', $email)->first();
        if ($foundAdminModel) {
            $foundAdmin = ['id' => $foundAdminModel->id, 'email' => $foundAdminModel->email];
            $sideMenusForEmail = getSideMenus($foundAdminModel);
        }
    }

    return response()->json([
        'env' => app()->environment(),
        'debug' => config('app.debug'),
        'admin_guard_check' => $adminGuardCheck,
        'admin_guard_user' => $adminGuardUser ? ['id' => $adminGuardUser->id, 'email' => $adminGuardUser->email ?? null, 'status' => $adminGuardUser->status ?? null] : null,
        'request_user_admin' => $requestAdminUser ? ['id' => $requestAdminUser->id, 'email' => $requestAdminUser->email ?? null] : null,
        'default_user' => $defaultUser ? ['id' => $defaultUser->id, 'email' => $defaultUser->email ?? null] : null,
        'sideMenusCurrent' => $sideMenusCurrent,
        'sideMenusForEmail' => $sideMenusForEmail,
        'foundAdmin' => $foundAdmin,
    ]);
})->name('dev.debug.side-menus');

// Dev debug: expose cached web setting and session companyInfo (local/dev only)
Route::get('/dev/debug-websetting', function (Request $request) {
    if (!app()->environment('local') && !config('app.debug')) {
        abort(404);
    }

    $webSetting = function_exists('get_cached_web_setting') ? get_cached_web_setting(true) : null;
    $companyInfo = session('companyInfo');

    return response()->json([
        'webSetting' => $webSetting?->toArray() ?? null,
        'companyInfo' => $companyInfo,
    ]);
})->name('dev.debug.websetting');

Route::get('/dev/debug-websetting/view', function (Request $request) {
    if (!app()->environment('local') && !config('app.debug')) {
        abort(404);
    }

    $webSetting = function_exists('get_cached_web_setting') ? get_cached_web_setting(true) : null;
    $companyInfo = session('companyInfo');

    return view('debug.websetting', compact('webSetting', 'companyInfo'));
})->name('dev.debug.websetting.view');

// Dev route: dump Inertia shared props for the current request
Route::get('/dev/debug-shared-props', function (Request $request) {
    if (!app()->environment('local') && !config('app.debug')) {
        abort(404);
    }

    $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
    $shared = $middleware->share($request);

    return response()->json($shared);
})->name('dev.debug.shared-props');

// Test-only registration endpoint (no admin auth) for automated browser tests
Route::post('/test/attendance/face/register', [FaceAttendanceController::class, 'registerStoreTest']);
Route::post('/test/attendance/face/mark', [FaceAttendanceController::class, 'markTest']);
Route::get('/test/attendance/face/register-page', function () {
    return view('backend.staffattendance.face_register', ['testMode' => true]);
});
Route::get('/test/attendance/face/page', function () {
    return view('backend.staffattendance.face', ['testMode' => true]);
});

// Public invoice download (allow anonymous access via query string)
Route::get('/download-invoice', [InvoiceController::class, 'downloadInvoice'])->name('download.invoice');

// Public IPD invoice download (anonymous access)
Route::get('/download/ipd/invoice', [InvoiceController::class, 'downloadIpdInvoice'])->name('download.ipd.invoice');

Route::get('/cache-clear', function () {
    try {
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('clear-compiled');
        Artisan::call('optimize:clear');
        Artisan::call('storage:link');

        // Running `optimize` (route caching) from a web request is unsafe.
        // Skip optimization here; run `php artisan optimize` from CLI when needed.
        \Illuminate\Support\Facades\Log::info('/cache-clear: skipped running Artisan::optimize() via web request.');

        session()->flash('message', 'System Updated Successfully.');
    } catch (\Throwable $e) {
        return response('Cache clear failed: ' . $e->getMessage(), 500);
    }

    // Redirect to the admin dashboard. Route names may be prefixed
    // by the RouteServiceProvider (e.g. 'backend.'). Try several
    // likely names before falling back to root.
    try {
        if (\Illuminate\Support\Facades\Route::has('backend.dashboard')) {
            return redirect()->route('backend.dashboard');
        }
        if (\Illuminate\Support\Facades\Route::has('admin.dashboard')) {
            return redirect()->route('admin.dashboard');
        }
        if (\Illuminate\Support\Facades\Route::has('dashboard')) {
            return redirect()->route('dashboard');
        }
    } catch (\Throwable $e) {
        // If route generation fails for any reason, fallback to home URL
    }

    return redirect('/');
});

Route::get('/favicon-dynamic.ico', [WebSettingController::class, 'favicon'])->name('favicon.dynamic');
Route::get('/public-storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('public.storage.file');

Route::get('/webSetting/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*');

Route::group(['as' => 'auth.'], function () {
    Route::get('/login', [LoginController::class, 'loginPage'])->name('login2')->middleware('AuthCheck');
    Route::post('/login', [LoginController::class, 'login'])->name('login');
    Route::match(['get','post'], '/logout', [LoginController::class, 'logout'])->name('logout');
});

// Legacy redirects: keep old /admin/login and /admin/logout working
Route::permanentRedirect('/admin/login', '/login');
Route::permanentRedirect('/admin/logout', '/logout');

// Public bKash endpoints for subscription renewal (used from login page)
Route::get('payment/bkash/renew', [\App\Http\Controllers\Payment\BkashController::class, 'publicInitiate'])->name('payment.bkash.initiate.public');
Route::match(['get', 'post'], 'payment/bkash/callback', [\App\Http\Controllers\Payment\BkashController::class, 'callback'])->name('payment.bkash.callback');
Route::get('payment/bkash/waiting/{payment}', [\App\Http\Controllers\Payment\BkashController::class, 'waiting'])->name('payment.bkash.waiting');
Route::get('payment/bkash/waiting/{payment}/poll', [\App\Http\Controllers\Payment\BkashController::class, 'waitingPoll'])->name('payment.bkash.waiting.poll');
Route::get('payment/bkash/confirm-pending', [\App\Http\Controllers\Payment\BkashController::class, 'confirmPending'])->name('payment.bkash.confirm.pending');
Route::post('payment/bkash/webhook', [\App\Http\Controllers\Payment\BkashWebhookController::class, 'handle'])->name('payment.bkash.webhook');
Route::get('payment/bkash/unsubscribe', [\App\Http\Controllers\Payment\BkashController::class, 'publicUnsubscribe'])->name('payment.bkash.unsubscribe.public');
Route::get('payment/bkash/simulate-public/{payment}', [\App\Http\Controllers\Payment\BkashController::class, 'publicSimulatePage'])->name('payment.bkash.simulate.public.page');
Route::post('payment/bkash/simulate-public/{payment}/approve', [\App\Http\Controllers\Payment\BkashController::class, 'publicSimulateApprove'])->name('payment.bkash.simulate.approve.public');

Route::post('/website/appointment', [HomeController::class, 'storeAppointment'])
    ->middleware('throttle:8,1')
    ->name('website.appointment.store');

Route::group(['middleware' => ['AdminAuth', 'org.context', 'branch.scope']], function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    // Ensure a plain 'dashboard' route name exists so the final named route
    // (prefixed by the RouteServiceProvider as 'backend.dashboard') matches
    // menu entries that reference 'backend.dashboard'. Avoid naming the route
    // with the 'backend.' prefix here to prevent double-prefixing.
    if (!\Illuminate\Support\Facades\Route::has('backend.dashboard')) {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    }

    // API: return side menus as JSON for client-side fallback
    Route::get('/admin/side-menus', function () {
        $user = auth()->guard('admin')->user();
        return response()->json(getSideMenus($user));
    })->name('api.side-menus');

    // Account Management (Chart of Accounts, Ledger, Audit)
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/balances', [AccountController::class, 'balances'])->name('accounts.balances');
    Route::get('accounts/trial-balance', [AccountController::class, 'trialBalance'])->name('accounts.trial-balance');
    Route::get('accounts/profit-loss', [AccountController::class, 'profitLoss'])->name('accounts.profit-loss');
    Route::get('accounts/balance-sheet', [AccountController::class, 'balanceSheet'])->name('accounts.balance-sheet');
    Route::get('accounts/cash-flow', [AccountController::class, 'cashFlow'])->name('accounts.cash-flow');
    Route::get('accounts/vendor-payment', [SupplierPaymentController::class, 'index'])->name('accounts.vendor-payment.index');
    Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
    Route::get('accounts/audit', [AccountAuditController::class, 'index'])->name('accounts.audit');

    // Account API (JSON) for Chart of Accounts CRUD
    Route::get('accounts/list', [AccountApiController::class, 'index'])->name('accounts.list');
    Route::get('accounts/opening-balances/status', [AccountApiController::class, 'openingBalanceStatus'])->name('accounts.opening-balances.status');
    Route::get('accounts/opening-balances/history', [AccountApiController::class, 'openingBalanceHistory'])->name('accounts.opening-balances.history');
    Route::get('accounts/{id}/show', [AccountApiController::class, 'show'])->name('accounts.show');
    Route::post('accounts', [AccountApiController::class, 'store'])->name('accounts.store');
    Route::post('accounts/opening-balances/save', [AccountApiController::class, 'saveOpeningBalances'])->name('accounts.opening-balances.save');
    Route::post('accounts/opening-balances/post', [AccountApiController::class, 'postOpeningBalances'])->name('accounts.opening-balances.post');
    Route::put('accounts/{id}', [AccountApiController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{id}', [AccountApiController::class, 'destroy'])->name('accounts.destroy');

    // Ledger API (JSON) for browsing transactions
    Route::get('ledger/list', [LedgerApiController::class, 'index'])->name('ledger.list');
    Route::get('ledger/export', [LedgerApiController::class, 'export'])->name('ledger.export');
    Route::get('ledger/{id}/show', [LedgerApiController::class, 'show'])->name('ledger.show');
    Route::get('accounts/trial-balance/list', [LedgerApiController::class, 'trialBalance'])->name('accounts.trial-balance.list');
    Route::get('accounts/profit-loss/list', [LedgerApiController::class, 'profitLoss'])->name('accounts.profit-loss.list');
    Route::get('accounts/balance-sheet/list', [LedgerApiController::class, 'balanceSheet'])->name('accounts.balance-sheet.list');
    Route::get('accounts/cash-flow/list', [LedgerApiController::class, 'cashFlow'])->name('accounts.cash-flow.list');
    Route::get('accounts/financial-summary/list', [LedgerApiController::class, 'financialSummary'])->name('accounts.financial-summary.list');

    // Generic approval inbox + actions
    Route::get('approval-requests/inbox', [\App\Http\Controllers\Backend\ApprovalRequestController::class, 'index'])->name('approval.requests.index');
    Route::post('approval-requests/{approvalRequest}/approve', [\App\Http\Controllers\Backend\ApprovalRequestController::class, 'approve'])->name('approval.requests.approve');
    Route::post('approval-requests/{approvalRequest}/reject', [\App\Http\Controllers\Backend\ApprovalRequestController::class, 'reject'])->name('approval.requests.reject');

    // bKash settings (admin)
    Route::get('settings/payment/bkash', [BkashSettingController::class, 'index'])->name('settings.payment.bkash');
    Route::post('settings/payment/bkash', [BkashSettingController::class, 'store'])->name('settings.payment.bkash.store');
    Route::post('settings/payment/bkash/test', [BkashSettingController::class, 'test'])->name('settings.payment.bkash.test');

    // bKash payment endpoints (admin)
    Route::post('payment/bkash/initiate', [\App\Http\Controllers\Payment\BkashController::class, 'initiate'])->name('payment.bkash.initiate');
    Route::get('payment/bkash/simulate/{payment}/approve', [\App\Http\Controllers\Payment\BkashController::class, 'simulateApprove'])->name('payment.bkash.simulate.approve');
    Route::post('payment/bkash/{payment}/mark-ready', [BkashController::class, 'markReady'])->name('payment.bkash.markReady');

    Route::get('/dashboard-setting', [DashboardSettingController::class, 'edit'])->name('dashboard-setting.edit');
    Route::post('/dashboard-setting', [DashboardSettingController::class, 'update'])->name('dashboard-setting.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::post('/symptom-types', [SymptomTypeController::class, 'store'])->name('symptom-types.store');

    Route::resource('admin', AdminController::class);
    Route::resource('journal-entry', JournalEntryController::class);
    Route::get('admin/{id}/modules', [AdminController::class, 'editModules'])->name('admin.modules.edit');
    Route::post('admin/{id}/modules', [AdminController::class, 'updateModules'])->name('admin.modules.update');
    Route::get('admin/{id}/status/{status}/change', [AdminController::class, 'changeStatus'])->name('admin.status.change');

    // for role
    Route::resource('role', RoleController::class);

    // Attendance sync (admin trigger)
    Route::post('attendance/sync', [AttendanceSyncController::class, 'sync'])->name('attendance.sync');
    Route::post('attendance/sync/{id}', [AttendanceSyncController::class, 'syncDevice'])->name('attendance.sync.device');
    Route::get('attendance/devices', [\App\Http\Controllers\Backend\AttendanceAdminController::class, 'devices'])->name('attendance.devices');
    Route::get('attendance/shifts', [\App\Http\Controllers\Backend\AttendanceAdminController::class, 'shifts'])->name('attendance.shifts');

    // for permission entry
    Route::resource('permission', PermissionController::class);

    //for Patient
    Route::resource('patient', PatientController::class);
    Route::get('patient/{id}/status/{status}/change', [PatientController::class, 'changeStatus'])->name('patient.status.change');

    // Doctor portal (doctor-wise OPD list for prescribing)
    Route::get('doctor-portal/opd', [OpdPatientController::class, 'doctorPortal'])->name('doctor.portal.opd');


    //for Tpa
    Route::resource('tpa', TpaController::class);
    Route::get('tpa/{id}/status/{status}/change', [TpaController::class, 'changeStatus'])->name('tpa.status.change');

    // Employee admin UI (Inertia)
    Route::resource('employee', EmployeeController::class);

    // Payslip download (PDF/HTML preview)
    Route::get('payslip/{payslip}/download', [PayslipController::class, 'download'])->name('payslip.download');


    //for Billing
    // Exclude 'show' from resource routes to avoid conflict with custom routes
    // like 'billing/hospital-charges' which would otherwise be captured by
    // the resource `show` route and dispatch to a non-existent `show()` method.
    Route::resource('billing', BillingController::class)->except(['show']);
    Route::get('billing/{id}/status/{status}/change', [BillingController::class, 'changeStatus'])->name('billing.status.change');
    Route::get('billing', [BillingController::class, 'billing'])->name('billing.Page');
    Route::get('view-billing-page', [BillingController::class, 'billingPage'])->name('billing.view');
    // Hospital charges feature removed — Billing uses Item/Test lists only.
    // Hospital charges route removed — feature disabled and UI removed.
    Route::get('pending-billings', [BillingController::class, 'pendingList'])->name('pending.list');

    Route::get('view-billing-list-page', [BillingController::class, 'index'])->name('billing.list');
    Route::match(['post', 'put'], '/print/bill', [BillingController::class, 'printBill'])->name('print.bill');

    Route::get('/invoice/{id}', [InvoiceController::class, 'downloadInvoice'])->name('invoice');
    Route::get('/download-report', [ReportingController::class, 'downloadReport'])->name('download.report');
    Route::get('/search/billing', [BillingController::class, 'searchShow'])->name('billing.search');
    Route::get('/billing/prescriptions/suggest', [BillingController::class, 'searchPrescriptionSuggestions'])->name('billing.prescriptions.suggest');
    Route::get('/billing/prescriptions/search', [BillingController::class, 'searchPrescription'])->name('billing.prescriptions.search');

    Route::get('/billing/doctors/search', [BillingController::class, 'searchDoctors'])->name('billing.doctors.search');
    Route::post('/billing/doctors/create', [BillingController::class, 'createBillingDoctor'])->name('billing.doctors.create');

    // Refund Management Routes
    Route::get('refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::post('refunds/process', [RefundController::class, 'processRefund'])->name('refunds.process');
    Route::get('refunds/list', [BillingController::class, 'refundList'])->name('refunds.list');
    Route::get('refunds/{billing_id}', [RefundController::class, 'show'])->whereNumber('billing_id')->name('refunds.show');

    //for Appoinment
    Route::resource('appoinment', AppoinmentController::class);
    Route::get('appoinment-website-inbox', [AppoinmentController::class, 'websiteInbox'])->name('appoinment.website-inbox');
    Route::get('appoinment/{id}/status/{status}/change', [AppoinmentController::class, 'changeStatus'])->name('appoinment.status.change');
    Route::post('/doctors', [AppoinmentController::class, 'doctorStore'])->name('doctors.store');
    Route::get('/download/appointment/invoice', [InvoiceController::class, 'downloadAppointmentInvoice'])->name('download.appointment.invoice');

    //for OpdPatient
    Route::resource('opdpatient', OpdPatientController::class);
    Route::get('opdpatient/{id}/status/{status}/change', [OpdPatientController::class, 'changeStatus'])->name('opdpatient.status.change');
    Route::get('opdpatient/{id}/prescription', [OpdPatientController::class, 'prescription'])->name('opdpatient.prescription');
    Route::post('opdpatient/{id}/prescription', [OpdPatientController::class, 'storePrescription'])->name('opdpatient.prescription.store');
    Route::get('opdpatient/{id}/prescription/print', [OpdPatientController::class, 'printPrescription'])->name('opdpatient.prescription.print');
    Route::get('opdpatient/{id}/prescription/pdf', [OpdPatientController::class, 'downloadPrescriptionPdf'])->name('opdpatient.prescription.pdf');
        Route::get('/download-opd-bill-print', [InvoiceController::class, 'downloadOpdInvoice'])->name('download.opd.bill');
    Route::get('/download/ipd/invoice', [InvoiceController::class, 'downloadIpdInvoice'])->name('download.ipd.invoice');
    Route::get('/download/ipd/final-bill', [InvoiceController::class, 'downloadIpdFinalBill'])->name('download.ipd.final-bill');
        Route::get('/print/ipd/invoice', [InvoiceController::class, 'printIpdInvoice'])->name('print.ipd.invoice');
        Route::get('/print/ipd/final-bill', [InvoiceController::class, 'printIpdFinalBill'])->name('print.ipd.final-bill');



        



        

    //for IpdPatient
    // NOTE: keep this route before Route::resource(), otherwise "discharged" may match the resource show route.
    Route::get('ipdpatient/discharged', [IpdPatientController::class, 'discharged'])->name('ipdpatient.discharged');

    Route::resource('ipdpatient', IpdPatientController::class);
    Route::get('ipdpatient/{id}/status/{status}/change', [IpdPatientController::class, 'changeStatus'])->name('ipdpatient.status.change');
    Route::post('ipdpatient/{id}/discharge-billing/regenerate', [IpdPatientController::class, 'regenerateDischargeBilling'])->name('ipdpatient.discharge-billing.regenerate');
    Route::get('ipdpatient/{id}/prescription', [IpdPatientController::class, 'prescription'])->name('ipdpatient.prescription');
    Route::post('ipdpatient/{id}/prescription', [IpdPatientController::class, 'storePrescription'])->name('ipdpatient.prescription.store');
        Route::get('ipdpatient/{id}/prescription/print', [IpdPatientController::class, 'printPrescription'])->name('ipdpatient.prescription.print');
    Route::get('ipdpatient/{id}/prescription/pdf', [IpdPatientController::class, 'downloadPrescriptionPdf'])->name('ipdpatient.prescription.pdf');
        Route::get('ipdpatient/{id}/running-bill/print', [IpdPatientController::class, 'printRunningBill'])->name('ipdpatient.running-bill.print');

    // IPD Payments - simple create endpoint for payments related to an IPD admission
    Route::post('ipdpatient/{id}/payments', [IpdPatientController::class, 'storePayment'])->name('ipdpatient.payments.store');
    // Update an existing payment (AJAX)
    Route::post('ipdpatient/{id}/payments/{paymentId}', [IpdPatientController::class, 'updatePayment'])->name('ipdpatient.payments.update');
    // Apply discount to billing for an IPD patient
    Route::post('ipdpatient/{id}/billing/discount', [IpdPatientController::class, 'applyBillingDiscount'])->name('ipdpatient.billing.apply_discount');
    // IPD Notes (nurse notes, consultant register, operations, bed history)
    Route::post('ipdpatient/{id}/notes', [IpdPatientController::class, 'storeNote'])->name('ipdpatient.notes.store');
    // Live consultation toggle/update
    Route::post('ipdpatient/{id}/live-consultation', [IpdPatientController::class, 'updateLiveConsultation'])->name('ipdpatient.live_consultation.update');

    // IPD Charges (room rent / bed / OT)
    Route::post('ipdpatient/{id}/charges/room-rent', [IpdChargeController::class, 'storeRoomRent'])->name('ipdpatient.charges.room-rent.store');
    Route::delete('ipdpatient/{id}/charges/room-rent/{chargeId}', [IpdChargeController::class, 'destroyRoomRent'])->name('ipdpatient.charges.room-rent.destroy');

    Route::post('ipdpatient/{id}/charges/bed', [IpdChargeController::class, 'storeBedCharge'])->name('ipdpatient.charges.bed.store');
    Route::delete('ipdpatient/{id}/charges/bed/{chargeId}', [IpdChargeController::class, 'destroyBedCharge'])->name('ipdpatient.charges.bed.destroy');

        Route::post('ipdpatient/{id}/charges/ot', [IpdChargeController::class, 'storeOtCharge'])->name('ipdpatient.charges.ot.store');
    Route::delete('ipdpatient/{id}/charges/ot/{chargeId}', [IpdChargeController::class, 'destroyOtCharge'])->name('ipdpatient.charges.ot.destroy');

    Route::post('ipdpatient/{id}/charges/doctor-visit', [IpdChargeController::class, 'storeDoctorVisitCharge'])->name('ipdpatient.charges.doctor-visit.store');
    Route::delete('ipdpatient/{id}/charges/doctor-visit/{chargeId}', [IpdChargeController::class, 'destroyDoctorVisitCharge'])->name('ipdpatient.charges.doctor-visit.destroy');

    // Add hospital charges (select from Charge list) to IPD and create/append billing
    Route::post('ipdpatient/{id}/charges/hospital', [IpdPatientController::class, 'addHospitalCharges'])->name('ipdpatient.charges.hospital.store');
    // Add a manual hospital charge (quick add an item from IPD UI and bill immediately)
    Route::post('ipdpatient/{id}/charges/hospital/manual', [IpdPatientController::class, 'addManualHospitalCharge'])->name('ipdpatient.charges.hospital.manual.store');



    // Discharge Certificate

    Route::get('ipdpatient/{id}/discharge-certificate/prepare', [IpdPatientController::class, 'prepareDischargeCertificate'])->name('ipdpatient.discharge-certificate.prepare');
    Route::get('ipdpatient/{id}/discharge-certificate/print', [IpdPatientController::class, 'printDischargeCertificate'])->name('ipdpatient.discharge-certificate.print');
    Route::get('ipdpatient/{id}/discharge-certificate/pdf', [IpdPatientController::class, 'downloadDischargeCertificatePdf'])->name('ipdpatient.discharge-certificate.pdf');







    //for Pharmacy
    Route::get('pharmacy/stock-report', [PharmacyStockReportController::class, 'index'])->name('pharmacy.stock.report');
    Route::resource('pharmacy', PharmacyController::class)->whereNumber('pharmacy');
    Route::get('pharmacy/{id}/status/{status}/change', [PharmacyController::class, 'changeStatus'])->name('pharmacy.status.change');


    //for Pathology
    Route::resource('pathology', PathologyController::class);
    Route::get('pathology/{id}/status/{status}/change', [PathologyController::class, 'changeStatus'])->name('pathology.status.change');


    //for Radiology
    Route::resource('radiology', RadiologyController::class);
    Route::get('radiology/{id}/status/{status}/change', [RadiologyController::class, 'changeStatus'])->name('radiology.status.change');


    //for BloodBank
    Route::resource('bloodbank', BloodBankController::class);
    Route::get('bloodbank/{id}/status/{status}/change', [BloodBankController::class, 'changeStatus'])->name('bloodbank.status.change');


    //for Ambulance
    Route::resource('ambulance', AmbulanceController::class);
    Route::get('ambulance/{id}/status/{status}/change', [AmbulanceController::class, 'changeStatus'])->name('ambulance.status.change');


    //for FrontOffice
    Route::resource('frontoffice', FrontOfficeController::class);
    Route::get('frontoffice/{id}/status/{status}/change', [FrontOfficeController::class, 'changeStatus'])->name('frontoffice.status.change');
    Route::post('frontoffice/import', [FrontOfficeController::class, 'import'])->name('frontoffice.import');


    //for BirthDeathRecord
    Route::resource('birthdeathrecord', BirthDeathRecordController::class);
    Route::get('birthdeathrecord/{id}/status/{status}/change', [BirthDeathRecordController::class, 'changeStatus'])->name('birthdeathrecord.status.change');
    Route::get('birthdeathrecord/{id}/certificate/print', [BirthDeathRecordController::class, 'printCertificate'])->name('birthdeathrecord.certificate.print');

    //for DutyRoaster
    Route::resource('dutyroaster', DutyRoasterController::class);
    Route::get('dutyroaster/{id}/status/{status}/change', [DutyRoasterController::class, 'changeStatus'])->name('dutyroaster.status.change');


    //for AnnualCalendar
    Route::resource('annualcalendar', AnnualCalendarController::class);
    Route::get('annualcalendar/{id}/status/{status}/change', [AnnualCalendarController::class, 'changeStatus'])->name('annualcalendar.status.change');


    //for Referral
    Route::resource('referral', ReferralController::class);
    Route::get('referral/{id}/status/{status}/change', [ReferralController::class, 'changeStatus'])->name('referral.status.change');
    Route::post('referral/commission/preview', [ReferralController::class, 'commissionPreview'])->name('referral.commission.preview');
    Route::post('referral/{id}/commission-payment', [ReferralController::class, 'commissionPayment'])->name('referral.commission.payment');
    Route::get('referral/{id}/commission-payment/paid', [ReferralController::class, 'commissionPaymentPaid'])->name('referral.commission.payment.paid');
    Route::get('referral/{id}/commission-payment/partial', [ReferralController::class, 'commissionPaymentForm'])->name('referral.commission.payment.form');
    Route::get('referral/payee/{payeeId}/commission-payment/paid', [ReferralController::class, 'commissionPaymentPayeePaid'])->name('referral.commission.payment.payee.paid');
    Route::get('referral/payee/{payeeId}/commission-payment/partial', [ReferralController::class, 'commissionPaymentPayeeForm'])->name('referral.commission.payment.payee.form');
    Route::get('referral/payee/{payeeId}/commission-payment/bills', [ReferralController::class, 'commissionPaymentPayeeBills'])->name('referral.commission.payment.payee.bills');
    Route::get('referral/payee/{payeeId}/commission-payment/print', [ReferralController::class, 'commissionPaymentPayeePrint'])->name('referral.commission.payment.payee.print');
    Route::post('referral/payee/{payeeId}/commission-payment', [ReferralController::class, 'commissionPaymentPayee'])->name('referral.commission.payment.payee');


    //for Inventory
    Route::resource('inventory', InventoryController::class);
    Route::get('inventory/{id}/status/{status}/change', [InventoryController::class, 'changeStatus'])->name('inventory.status.change');

    // Manufacturing / MRP
    Route::resource('bom', \App\Http\Controllers\Backend\BillOfMaterialController::class);
    Route::resource('production-order', \App\Http\Controllers\Backend\ProductionOrderController::class);
    Route::resource('work-order', \App\Http\Controllers\Backend\WorkOrderController::class);

    // Fixed Assets
    Route::resource('fixedasset', \App\Http\Controllers\Backend\FixedAssetController::class);

    // Currency & Exchange Rates
    Route::resource('currency', \App\Http\Controllers\Backend\CurrencyController::class);
    Route::get('exchange-rate', [\App\Http\Controllers\Backend\ExchangeRateController::class, 'index'])->name('exchange-rate.index');
    Route::post('exchange-rate', [\App\Http\Controllers\Backend\ExchangeRateController::class, 'store'])->name('exchange-rate.store');
    Route::delete('exchange-rate/{exchangeRate}', [\App\Http\Controllers\Backend\ExchangeRateController::class, 'destroy'])->name('exchange-rate.destroy');


    //for Certificate
    Route::resource('certificate', CertificateController::class);
    Route::get('certificate/{id}/status/{status}/change', [CertificateController::class, 'changeStatus'])->name('certificate.status.change');


    //for Reports
    Route::resource('reports', ReportsController::class);
    Route::get('reports/{id}/status/{status}/change', [ReportsController::class, 'changeStatus'])->name('reports.status.change');

    // for Sample Collection
    Route::get('sample-collection', [SampleCollectionController::class, 'index'])->name('sample-collection.index');
    Route::post('sample-collection/{billing}/collect', [SampleCollectionController::class, 'collect'])->name('sample-collection.collect');
    Route::post('sample-collection/item/{billItem}/collect', [SampleCollectionController::class, 'collectItem'])->name('sample-collection.item.collect');
    Route::get('sample-collection/{billing}/barcode', [SampleCollectionController::class, 'barcode'])->name('sample-collection.barcode');

    // for Reporting
    Route::get('reporting', [ReportingController::class, 'index'])->name('reporting.index');
    Route::get('reporting/search', [ReportingController::class, 'search'])->name('reporting.search');
    Route::get('reporting/{billing}/edit', [ReportingController::class, 'edit'])->name('reporting.edit');
    Route::post('reporting/{billing}', [ReportingController::class, 'update'])->name('reporting.update');
    Route::post('reporting/item/{billItem}', [ReportingController::class, 'updateItem'])->name('reporting.item.update');
    Route::get('reporting/item/{billItem}/file', [ReportingController::class, 'viewFile'])->name('reporting.item.file');
    Route::post('reporting/item/{billItem}/import-text', [ReportingController::class, 'importStoredFileText'])->name('reporting.item.import-text');
    Route::get('reporting/print/{billItem}', [ReportingController::class, 'print'])->name('reporting.print');

    // Radiology-specific reporting menus
    Route::get('reporting/ultrasound', [ReportingController::class, 'ultrasoundIndex'])->name('reporting.ultrasound');
    Route::get('reporting/xray', [ReportingController::class, 'xrayIndex'])->name('reporting.xray');
    Route::get('reporting/pathology', [ReportingController::class, 'pathologyIndex'])->name('reporting.pathology');

    // for Report Delivery
    Route::get('report-delivery', [ReportDeliveryController::class, 'index'])->name('report-delivery.index');
    Route::post('report-delivery/{billItem}/send', [ReportDeliveryController::class, 'send'])->name('report-delivery.send');
    Route::post('report-delivery/{billItem}/deliver', [ReportDeliveryController::class, 'deliver'])->name('report-delivery.deliver');
    // Billing-level send/deliver actions (bulk for an invoice)
    Route::post('report-delivery/{billing}/send-all', [ReportDeliveryController::class, 'sendAll'])->name('report-delivery.sendAll');
    Route::post('report-delivery/{billing}/deliver-all', [ReportDeliveryController::class, 'deliverAll'])->name('report-delivery.deliverAll');


    //for Setup
    Route::resource('setup', SetupController::class);
    Route::get('setup/{id}/status/{status}/change', [SetupController::class, 'changeStatus'])->name('setup.status.change');


    //for Designation
    Route::resource('designation', DesignationController::class);
    Route::get('designation/{id}/status/{status}/change', [DesignationController::class, 'changeStatus'])->name('designation.status.change');


    //for Department
    Route::resource('department', DepartmentController::class);
    Route::get('department/{id}/status/{status}/change', [DepartmentController::class, 'changeStatus'])->name('department.status.change');


    //for Specialist
    Route::resource('specialist', SpecialistController::class);
    Route::get('specialist/{id}/status/{status}/change', [SpecialistController::class, 'changeStatus'])->name('specialist.status.change');


    //for BloodIssue
    Route::resource('bloodissue', BloodIssueController::class);
    Route::get('bloodissue/{id}/status/{status}/change', [BloodIssueController::class, 'changeStatus'])->name('bloodissue.status.change');


    //for BloodComponentIssue
    Route::resource('bloodcomponentissue', BloodComponentIssueController::class);
    Route::get('bloodcomponentissue/{id}/status/{status}/change', [BloodComponentIssueController::class, 'changeStatus'])->name('bloodcomponentissue.status.change');


    //for BedType
    Route::resource('bedtype', BedTypeController::class);
    Route::get('bedtype/{id}/status/{status}/change', [BedTypeController::class, 'changeStatus'])->name('bedtype.status.change');


    //for BedGroup
    Route::resource('bedgroup', BedGroupController::class);
    Route::get('bedgroup/{id}/status/{status}/change', [BedGroupController::class, 'changeStatus'])->name('bedgroup.status.change');


    //for Floor
    Route::resource('floor', FloorController::class);
    Route::get('floor/{id}/status/{status}/change', [FloorController::class, 'changeStatus'])->name('floor.status.change');


    //for Bed
    Route::get('bed/status/snapshot', [BedController::class, 'statusSnapshot'])->name('bed.status.snapshot');
    Route::resource('bed', BedController::class);
    Route::get('bed/{id}/status/{status}/change', [BedController::class, 'changeStatus'])->name('bed.status.change');


    // PathologyTest: legacy "testpathology" routes removed in favor of unified
    // `itemcharge` endpoints. Legacy redirects below keep backward compatibility.


    //for PathologyCategory
    Route::resource('pathologycategory', PathologyCategoryController::class);
    Route::get('pathologycategory/{id}/status/{status}/change', [PathologyCategoryController::class, 'changeStatus'])->name('pathologycategory.status.change');


    //for PathologyUnit
    Route::resource('pathologyunit', PathologyUnitController::class);
    Route::get('pathologyunit/{id}/status/{status}/change', [PathologyUnitController::class, 'changeStatus'])->name('pathologyunit.status.change');


    //for PathologyParameter
    Route::resource('parameterofpathology', PathologyParameterController::class);
    Route::get('parameterofpathology/{id}/status/{status}/change', [PathologyParameterController::class, 'changeStatus'])->name('parameterofpathology.status.change');


    //for Charge
    // Redirect hospitalcharge create to unified Itemcharge create page
    Route::permanentRedirect('hospitalcharge/create', 'Itemcharge/create');

    Route::resource('hospitalcharge', ChargeController::class);
    Route::get('hospitalcharge/{id}/status/{status}/change', [ChargeController::class, 'changeStatus'])->name('hospitalcharge.status.change');

    // Alias Itemcharge -> reuse ChargeController so a single Item charge UI exists
    Route::resource('Itemcharge', ChargeController::class);
    Route::get('Itemcharge/{id}/status/{status}/change', [ChargeController::class, 'changeStatus'])->name('Itemcharge.status.change');

    // Legacy redirects: keep old /testpathology paths redirecting to /itemcharge
    Route::permanentRedirect('testpathology', 'itemcharge');
    Route::permanentRedirect('testpathology/create', 'itemcharge/create');

    // PathologyTest (served as Item Charge)
    Route::get('itemcharge/search', [PathologyTestController::class, 'search'])->name('itemcharge.search');
    Route::get('itemcharge/sample-csv', [PathologyTestController::class, 'downloadSampleCsv'])->name('itemcharge.sample-csv');
    Route::post('itemcharge/import', [PathologyTestController::class, 'importCsv'])->name('itemcharge.import');

    // Lowercase alias for itemcharge (serve pathology test UI to match legacy URLs)
    Route::resource('itemcharge', PathologyTestController::class);
    Route::get('itemcharge/{id}/status/{status}/change', [PathologyTestController::class, 'changeStatus'])->name('itemcharge.status.change');


    //for ChargeCategory
    Route::resource('chargecategory', ChargeCategoryController::class);
    Route::get('chargecategory/{id}/status/{status}/change', [ChargeCategoryController::class, 'changeStatus'])->name('chargecategory.status.change');


    //for ChargeType
    Route::resource('chargetype', ChargeTypeController::class);
    Route::get('chargetype/{id}/status/{status}/change', [ChargeTypeController::class, 'changeStatus'])->name('chargetype.status.change');
    Route::post('chargetype/toggle-module', [ChargeTypeController::class, 'toggleModule'])->name('chargetype.toggle-module');


    //for ChargeTaxCategory
    Route::resource('chargetaxcategory', ChargeTaxCategoryController::class);
    Route::get('chargetaxcategory/{id}/status/{status}/change', [ChargeTaxCategoryController::class, 'changeStatus'])->name('chargetaxcategory.status.change');


    //for ChargeUnitType
    Route::resource('chargeunittype', ChargeUnitTypeController::class);
    Route::get('chargeunittype/{id}/status/{status}/change', [ChargeUnitTypeController::class, 'changeStatus'])->name('chargeunittype.status.change');


    //for MedicineGroup
    Route::resource('medicinegroup', MedicineGroupController::class);
    Route::get('medicinegroup/{id}/status/{status}/change', [MedicineGroupController::class, 'changeStatus'])->name('medicinegroup.status.change');


    //for MedicineCompany
    Route::resource('medicinecompany', MedicineCompanyController::class);
    Route::get('medicinecompany/{id}/status/{status}/change', [MedicineCompanyController::class, 'changeStatus'])->name('medicinecompany.status.change');


    //for MedicineUnit
    Route::resource('medicineunit', MedicineUnitController::class);
    Route::get('medicineunit/{id}/status/{status}/change', [MedicineUnitController::class, 'changeStatus'])->name('medicineunit.status.change');


    //for DoseDuration
    Route::resource('doseduration', DoseDurationController::class);
    Route::get('doseduration/{id}/status/{status}/change', [DoseDurationController::class, 'changeStatus'])->name('doseduration.status.change');


    //for MedicineDoseInterval
    Route::resource('medicinedoseinterval', MedicineDoseIntervalController::class);
    Route::get('medicinedoseinterval/{id}/status/{status}/change', [MedicineDoseIntervalController::class, 'changeStatus'])->name('medicinedoseinterval.status.change');


    //for MedicineDosage
    Route::resource('medicinedosage', MedicineDosageController::class);
    Route::get('medicinedosage/{id}/status/{status}/change', [MedicineDosageController::class, 'changeStatus'])->name('medicinedosage.status.change');


    //for MedicineCategory
    Route::resource('medicinecategory', MedicineCategoryController::class);
    Route::get('medicinecategory/{id}/status/{status}/change', [MedicineCategoryController::class, 'changeStatus'])->name('medicinecategory.status.change');


    //for MedicineSupplier
    Route::resource('medicinesupplier', MedicineSupplierController::class);
    Route::get('medicinesupplier/{id}/status/{status}/change', [MedicineSupplierController::class, 'changeStatus'])->name('medicinesupplier.status.change');


    // for MedicineInventory
    Route::get(
        'medicineinventory/search',
        [MedicineInventoryController::class, 'search']
    )->name('medicineinventory.search');

    Route::get(
        'medicineinventory/sample-csv',
        [MedicineInventoryController::class, 'downloadSampleCsv']
    )->name('medicineinventory.sample-csv');

    Route::resource(
        'medicineinventory',
        MedicineInventoryController::class
    );

    Route::get(
        'medicineinventory/{id}/status/{status}/change',
        [MedicineInventoryController::class, 'changeStatus']
    )->name('medicineinventory.status.change');

    Route::post(
        'medicineinventory/import-csv',
        [MedicineInventoryController::class, 'importCsv']
    )->name('medicineinventory.import.csv');

    // for MedicinePurchase
    Route::resource('medicinepurchase', \App\Http\Controllers\Backend\MedicinePurchaseController::class);
    Route::post('medicinepurchase/{medicinepurchase}/receive', [\App\Http\Controllers\Backend\MedicinePurchaseController::class, 'receiveItems'])->name('medicinepurchase.receive');

    // for SitePurchase (all-site procurement)
    Route::resource('sitepurchase', SitePurchaseController::class);

    // for SupplierPayment
    Route::resource('supplierpayment', \App\Http\Controllers\Backend\SupplierPaymentController::class);
    Route::post('supplierpayment/{supplierpayment}/partial', [\App\Http\Controllers\Backend\SupplierPaymentController::class, 'addPartialPayment'])->name('supplierpayment.partial');
    Route::post('supplierpayment/pay-due-by-supplier/{supplier}', [\App\Http\Controllers\Backend\SupplierPaymentController::class, 'payDueBySupplier'])->name('supplierpayment.pay-due-by-supplier');
    Route::get('supplierpayment/report/stock-due', [\App\Http\Controllers\Backend\SupplierPaymentController::class, 'stockDueReport'])->name('supplierpayment.report.stock-due');

    // for ProductReturn
    Route::resource('productreturn', \App\Http\Controllers\Backend\ProductReturnController::class);
    Route::post('productreturn/{productreturn}/approve', [\App\Http\Controllers\Backend\ProductReturnController::class, 'approve'])->name('productreturn.approve');
    Route::post('productreturn/{productreturn}/process', [\App\Http\Controllers\Backend\ProductReturnController::class, 'process'])->name('productreturn.process');
    Route::post('productreturn/{productreturn}/pay', [\App\Http\Controllers\Backend\ProductReturnController::class, 'pay'])->name('productreturn.pay');

    // for StockManagement
    Route::get('stock', [\App\Http\Controllers\Backend\StockManagementController::class, 'index'])->name('stock.index');
    Route::get('stock/item/create', [\App\Http\Controllers\Backend\StockManagementController::class, 'createItem'])->name('stock.item.create');
    Route::post('stock/item', [\App\Http\Controllers\Backend\StockManagementController::class, 'storeItem'])->name('stock.item.store');
    Route::get('stock/requisitions', [\App\Http\Controllers\Backend\StockManagementController::class, 'requisitions'])->name('stock.requisitions');
    Route::get('stock/requisition/create', [\App\Http\Controllers\Backend\StockManagementController::class, 'createRequisition'])->name('stock.requisition.create');
    Route::post('stock/requisition', [\App\Http\Controllers\Backend\StockManagementController::class, 'storeRequisition'])->name('stock.requisition.store');
    Route::post('stock/requisition/{id}/decision', [\App\Http\Controllers\Backend\StockManagementController::class, 'requisitionDecision'])->name('stock.requisition.decision');
    Route::get('stock/requisition/{id}/print', [\App\Http\Controllers\Backend\StockManagementController::class, 'requisitionPrint'])->name('stock.requisition.print');
    Route::get('stock/requisition/{id}/issue-slip', [\App\Http\Controllers\Backend\StockManagementController::class, 'requisitionIssueSlip'])->name('stock.requisition.issue-slip');
    Route::get('stock/grns', [\App\Http\Controllers\Backend\StockManagementController::class, 'grns'])->name('stock.grns');
    Route::get('stock/grn/create', [\App\Http\Controllers\Backend\StockManagementController::class, 'createGrn'])->name('stock.grn.create');
    Route::post('stock/grn', [\App\Http\Controllers\Backend\StockManagementController::class, 'storeGrn'])->name('stock.grn.store');
    Route::get('stock/grn/{id}/print', [\App\Http\Controllers\Backend\StockManagementController::class, 'grnPrint'])->name('stock.grn.print');
    Route::get('stock/monthly-closing', [\App\Http\Controllers\Backend\StockManagementController::class, 'monthlyClosingReport'])->name('stock.monthly-closing');
    Route::get('stock/adjustments', [\App\Http\Controllers\Backend\StockManagementController::class, 'adjustments'])->name('stock.adjustments');
    Route::get('stock/adjustment/create', [\App\Http\Controllers\Backend\StockManagementController::class, 'createAdjustment'])->name('stock.adjustment.create');
    Route::post('stock/adjustment', [\App\Http\Controllers\Backend\StockManagementController::class, 'storeAdjustment'])->name('stock.adjustment.store');
    Route::get('stock/low-stock-report', [\App\Http\Controllers\Backend\StockManagementController::class, 'lowStockReport'])->name('stock.low-stock-report');
    Route::get('stock/movement-report', [\App\Http\Controllers\Backend\StockManagementController::class, 'stockMovementReport'])->name('stock.movement-report');

    //for ReferralCategory
    Route::resource('referralcategory', ReferralCategoryController::class);
    Route::get('referralcategory/{id}/status/{status}/change', [ReferralCategoryController::class, 'changeStatus'])->name('referralcategory.status.change');


    //for ReferralPerson
    Route::resource('referralperson', ReferralPersonController::class);
    Route::get('referralperson/{id}/status/{status}/change', [ReferralPersonController::class, 'changeStatus'])->name('referralperson.status.change');


    //for InvoiceDesign
    Route::resource('invoicedesign', InvoiceDesignController::class);
    Route::match(['post', 'delete'], 'invoicedesign/{id}/delete', [InvoiceDesignController::class, 'destroy'])->name('invoicedesign.delete');
    Route::get('invoicedesign/{id}/status/{status}/change', [InvoiceDesignController::class, 'changeStatus'])->name('invoicedesign.status.change');

    //for report
    Route::get('all-report', [ReportController::class, 'index'])->name('report.index');
    Route::get('/report/generate-pdf', [ReportController::class, 'generatePdf'])->name('report.generate-pdf');
    Route::get('report-summary', [DoctorSummaryController::class, 'index'])->middleware('permission:report-management')->name('report-summary.index');

    // for General Setting (canonical URL)
    Route::get('general-setting', [WebSettingController::class, 'create'])->name('websetting.create');
    Route::get('general-setting/section/general', [WebSettingController::class, 'section'])->defaults('section', 'general')->name('websetting.section.general');
    Route::get('general-setting/section/cms', [WebSettingController::class, 'section'])->defaults('section', 'cms')->name('websetting.section.cms');
    Route::get('general-setting/section/prefix', [WebSettingController::class, 'section'])->defaults('section', 'prefix')->name('websetting.section.prefix');
    Route::get('general-setting/section/sms', [WebSettingController::class, 'section'])->defaults('section', 'sms')->name('websetting.section.sms');
    Route::get('general-setting/section/module', [WebSettingController::class, 'section'])->defaults('section', 'module')->name('websetting.section.module');
    Route::get('general-setting/module/attendance', [WebSettingController::class, 'module'])->defaults('module', 'attendance')->name('websetting.module.attendance');
    Route::get('general-setting/module/pathology', [WebSettingController::class, 'module'])->defaults('module', 'pathology')->name('websetting.module.pathology');
    Route::get('general-setting/module/payroll', [WebSettingController::class, 'module'])->defaults('module', 'payroll')->name('websetting.module.payroll');
    Route::get('general-setting/module/reporting', [WebSettingController::class, 'module'])->defaults('module', 'reporting')->name('websetting.module.reporting');
    Route::get('general-setting/section/other', [WebSettingController::class, 'section'])->defaults('section', 'other')->name('websetting.section.other');
    Route::get('general-setting/section/sidebar', [WebSettingController::class, 'section'])->defaults('section', 'sidebar')->name('websetting.section.sidebar');
    Route::match(['get', 'post'], 'general-setting-store', [WebSettingController::class, 'store'])->name('websetting.store');

    // Bulk SMS
    Route::get('bulk-sms', [BulkSmsController::class, 'index'])->name('bulk-sms.index');
    Route::post('bulk-sms/send', [BulkSmsController::class, 'send'])->name('bulk-sms.send');

    // legacy URLs kept for backward compatibility
    Route::get('websetting-create', [WebSettingController::class, 'create']);
    Route::match(['get', 'post'], 'websetting-store', [WebSettingController::class, 'store']);

    // for Report Settings
    Route::get('report-setting', [ReportSettingController::class, 'edit'])->name('report-setting.edit');
    Route::post('report-setting', [ReportSettingController::class, 'update'])->name('report-setting.update');

    //for PharmacyBill
    Route::resource('pharmacybill', PharmacyBillController::class);
    Route::get('pharmacybill/{id}/status/{status}/change', [PharmacyBillController::class, 'changeStatus'])->name('pharmacybill.status.change');
    Route::get('pharmacybill/export/{format}', [PharmacyBillController::class, 'export'])->name('pharmacybill.export');

    // for StaffAttendance
    Route::resource('staffattendance', StaffAttendanceController::class);
    Route::get('staffattendance/{id}/status/{status}/change', [StaffAttendanceController::class, 'changeStatus'])->name('staffattendance.status.change');
    Route::get('/backend/staffattendance/fetch', [StaffAttendanceController::class, 'fetchDate'])->name('staffattendance.fetch');
    Route::get('/backend/staffattendance/report', [StaffAttendanceController::class, 'attendanceReport'])->name('staffattendance.report');

    // Simple webcam face-detect attendance (testing)
    Route::get('attendance/face', [FaceAttendanceController::class, 'index'])->name('attendance.face');
    Route::post('attendance/face/mark', [FaceAttendanceController::class, 'mark'])->name('attendance.face.mark');
    Route::get('attendance/face/register', [FaceAttendanceController::class, 'registerIndex'])->name('attendance.face.register');
    Route::post('attendance/face/register', [FaceAttendanceController::class, 'registerStore'])->name('attendance.face.register.store');
    Route::get('attendance/face/encodings', [FaceAttendanceController::class, 'registerList'])->name('attendance.face.encodings');
    Route::get('attendance/face/encodings/{id}/edit', [FaceAttendanceController::class, 'registerEdit'])->name('attendance.face.encodings.edit');
    Route::put('attendance/face/encodings/{id}', [FaceAttendanceController::class, 'registerUpdate'])->name('attendance.face.encodings.update');
    Route::delete('attendance/face/encodings/{id}', [FaceAttendanceController::class, 'registerDelete'])->name('attendance.face.encodings.delete');
    Route::get('/backend/staffattendance/salary-sheet', [StaffAttendanceController::class, 'salarySheet'])->name('staffattendance.salary-sheet');
    Route::get('/backend/staffattendance/salary-sheet/print', [StaffAttendanceController::class, 'salarySheetPrint'])->name('staffattendance.salary-sheet.print');
    Route::get('/backend/staffattendance/salary-sheet/pdf', [StaffAttendanceController::class, 'downloadSalarySheetPdf'])->name('staffattendance.salary-sheet.pdf');
    Route::post('/backend/staffattendance/salary-sheet/lock', [StaffAttendanceController::class, 'lockSalarySheet'])->name('staffattendance.salary-sheet.lock');
    Route::get('/backend/staffattendance/salary-sheet/breakdown-print', [StaffAttendanceController::class, 'salaryBreakdownPrint'])->name('staffattendance.salary-sheet.breakdown-print');
    Route::get('/backend/staffattendance/salary-sheet/breakdown-pdf', [StaffAttendanceController::class, 'downloadBreakdownPdf'])->name('staffattendance.salary-sheet.breakdown-pdf');
    Route::get('/backend/staffattendance/salary-sheet/holiday-audit', [StaffAttendanceController::class, 'downloadHolidayAudit'])->name('staffattendance.salary-sheet.holiday-audit');
    Route::post('/backend/staffattendance/salary-sheet/pay', [StaffAttendanceController::class, 'salaryPay'])->name('staffattendance.salary-sheet.pay');
    Route::post('/backend/staffattendance/salary-sheet/settings/save', [StaffAttendanceController::class, 'saveSalarySheetSettings'])->name('staffattendance.salary-sheet.settings.save');
    Route::get('/backend/staffattendance/duty-roster', [\App\Http\Controllers\Backend\DutyRosterController::class, 'index'])->name('staffattendance.duty-roster');
    Route::get('/backend/staffattendance/duty-roster/print', [\App\Http\Controllers\Backend\DutyRosterController::class, 'print'])->name('staffattendance.duty-roster.print');
    Route::post('/backend/staffattendance/duty-roster', [\App\Http\Controllers\Backend\DutyRosterController::class, 'store'])->name('staffattendance.duty-roster.store');
    Route::delete('/backend/staffattendance/duty-roster/{id}', [\App\Http\Controllers\Backend\DutyRosterController::class, 'destroy'])->name('staffattendance.duty-roster.destroy');
    Route::get('/staffattendance/report/{id}', [StaffAttendanceController::class, 'attendanceReportDetails'])->name('staffattendance.report.details');
    Route::get('/staff/payslip/{id}', [StaffAttendanceController::class, 'staffPaySlip'])->name('staff.payslip');
    Route::get('/payslip/download', [StaffAttendanceController::class, 'download'])->name('download.payslip');

    // for LeaveType
    Route::resource('Leavetype', LeaveTypeController::class);
    Route::get('approval-leave-request', [LeaveTypeController::class, 'approvalRequest'])->name('approval.request');
    Route::get('pending-leave-request', [LeaveTypeController::class, 'pendingRequest'])->name('pending.request');
    Route::get('all-apply-list', [LeaveTypeController::class, 'applyList'])->name('apply.list');
    Route::post('add-leave-type', [LeaveTypeController::class, 'storeLeaveType'])->name('store.leave.type');
    Route::get('pending/leave/{id}', [LeaveTypeController::class, 'pendingLeave'])->name('pending.leave');
    Route::get('approve/leave/{id}', [LeaveTypeController::class, 'approveLeave'])->name('approve.leave');
    Route::get('approve/leave/confirm/{id}', [LeaveTypeController::class, 'confirmApproval'])->name('approved.leave.confirm');
    Route::get('reject/leave/{id}', [LeaveTypeController::class, 'rejectLeave'])->name('reject.leave');
    Route::get('view/leave/list/{id}', [LeaveTypeController::class, 'leaveListView'])->name('leave.list.view');
    Route::get('Leavetype/{id}/status/{status}/change', [LeaveTypeController::class, 'changeStatus'])->name('Leavetype.status.change');

    // for DefineLeave
    Route::resource('defineleave', DefineLeaveController::class);
    Route::get('defineleave/{id}/status/{status}/change', [DefineLeaveController::class, 'changeStatus'])->name('defineleave.status.change');

    // for ApplyLeave
    Route::resource('applyleave', ApplyLeaveController::class);
    Route::get('applyleave/{id}/status/{status}/change', [ApplyLeaveController::class, 'changeStatus'])->name('applyleave.status.change');

    //for ExpenseHead
    Route::resource('expensehead', ExpenseHeadController::class);
    Route::get('expensehead/{id}/status/{status}/change', [ExpenseHeadController::class, 'changeStatus'])->name('expensehead.status.change');


    //for Expense
    Route::resource('expense', ExpenseController::class);
    Route::get('expense/{id}/print', [ExpenseController::class, 'print'])->name('expense.print');
    Route::get('expense/{id}/status/{status}/change', [ExpenseController::class, 'changeStatus'])->name('expense.status.change');


    //for BillingDoctor
    Route::resource('billingdoctor', BillingDoctorController::class);
    Route::get('billingdoctor/{id}/status/{status}/change', [BillingDoctorController::class, 'changeStatus'])->name('billingdoctor.status.change');


    //bulk import
    Route::get('/charges/import', [ChargeImportController::class, 'showImportForm'])->name('charges.import.form');
    Route::post('/charges/import', [ChargeImportController::class, 'processImport'])->name('charges.import.process');
    Route::get('/charges/import/sample-csv', [ChargeImportController::class, 'downloadSampleCsv'])->name('charges.import.sample');

    //for finance report
    Route::get('finance/report', [FinanceReportController::class, 'reportPage'])->name('finance-report.index');
    Route::get('/finance/report/download-pdf', [FinanceReportController::class, 'downloadPDF'])->name('finance.report.pdf');

    // for activity logs
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('activity-logs/print', [ActivityLogController::class, 'print'])->name('activity-logs.print');
    Route::get('activity-logs/user/summary', [ActivityLogController::class, 'userSummary'])->name('activity-logs.user-summary');
    Route::get('activity-logs/module/summary', [ActivityLogController::class, 'moduleSummary'])->name('activity-logs.module-summary');
    Route::get('activity-logs/alerts', [ActivityLogController::class, 'alerts'])->name('activity-logs.alerts');
    Route::get('activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');
    Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    Route::post('activity-logs/delete-old', [ActivityLogController::class, 'deleteOldLogs'])->name('activity-logs.delete-old');

    // for pathology machine integration logs
    Route::get('pathology-machine-logs', [PathologyMachineIntegrationLogController::class, 'index'])->name('pathology-machine-logs.index');
    Route::get('pathology-machine-logs/export', [PathologyMachineIntegrationLogController::class, 'export'])->name('pathology-machine-logs.export');
    Route::post('pathology-machine-logs/{log}/retry-simulate', [PathologyMachineIntegrationLogController::class, 'retrySimulate'])->name('pathology-machine-logs.retry-simulate');



    //Route::middleware(['auth:admin'])->group(function () {

    // Pending billings
    //Route::get('pending-billings', [BillingController::class, 'pending'])
       // ->name('pending.billings');
Route::get('due-collect/{id}', [DueCollectController::class, 'index'])
    ->name('due.collect');

Route::post('due-collect/{id}', [DueCollectController::class, 'store'])
    ->name('due.collect.store');

Route::get('opd-due-collect/{id}', [DueCollectController::class, 'opdIndex'])
    ->name('opd.due.collect');

Route::post('opd-due-collect/{id}', [DueCollectController::class, 'opdStore'])
    ->name('opd.due.collect.store');
    // Auto-register placeholder routes for menu entries that reference
    // named routes which are not defined. This prevents menu links from
    // throwing exceptions when the app tries to generate URLs for them.
    try {
        $menuRoutes = \App\Models\Menu::whereNotNull('route')->pluck('route')->unique()->filter()->values();
        $createdPlaceholderNames = []; // track created placeholder names to avoid duplicates
        foreach ($menuRoutes as $menuRoute) {
            $menuRoute = trim((string) $menuRoute);
            if ($menuRoute === '') continue;

            // Determine the final route name that will exist when this file
            // is loaded inside RouteServiceProvider which applies the
            // `as('backend.')` name prefix. If the menu route already
            // contains that prefix, strip it before naming the new route
            // so the final registered name matches the menu value.
            $registrationName = $menuRoute;
            if (str_starts_with($registrationName, 'backend.')) {
                $registrationName = substr($registrationName, strlen('backend.'));
            }

            // Normalize the registration name to lowercase to reduce
            // accidental case-sensitive collisions from DB entries.
            $safeRegistrationName = strtolower($registrationName);

            // If either the raw menu route or the final prefixed name
            // already exists, skip creating a placeholder.
            if (\Illuminate\Support\Facades\Route::has($menuRoute)
                || \Illuminate\Support\Facades\Route::has('backend.' . $registrationName)
                || \Illuminate\Support\Facades\Route::has('backend.' . $safeRegistrationName)
                || \Illuminate\Support\Facades\Route::has('backend.__placeholder.' . $safeRegistrationName)) {
                continue;
            }

            $slug = \Illuminate\Support\Str::slug($menuRoute);

            // Create the placeholder route but guard the naming step so
            // a naming collision doesn't throw and abort route loading.
            // Use a reserved '__placeholder.' name prefix so these auto-
            // registered routes cannot collide with real route names.
            // If we've already created a placeholder for this safe name during
            // this request, skip creating another one. This prevents multiple
            // placeholder routes from being registered with the same name
            // (which causes route:cache to fail during serialization).
            if (in_array($safeRegistrationName, $createdPlaceholderNames, true)) {
                continue;
            }

            try {
                \Illuminate\Support\Facades\Route::get('/__placeholder/' . $slug, function () use ($menuRoute) {
                    return \Inertia\Inertia::render('Backend/Placeholder', ['title' => $menuRoute]);
                })->name('__placeholder.' . $safeRegistrationName);
                $createdPlaceholderNames[] = $safeRegistrationName;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Skipping placeholder for ' . $menuRoute . ': ' . $e->getMessage());
                continue;
            }
        }
    } catch (\Throwable $e) {
        // ignore placeholder registration errors during route loading
    }

});

// Patient Portal
Route::middleware(['patient.panel'])->group(function () {
    Route::get('patient-portal/login', [PatientPortalController::class, 'loginForm'])->name('patient.portal.login');
    Route::post('patient-portal/login', [PatientPortalController::class, 'login'])->name('patient.portal.login.post');
    Route::match(['get', 'post'], 'patient-portal/payment/callback', [PatientPortalController::class, 'paymentCallback'])->name('patient.portal.payment.callback');

    Route::middleware(['patient.auth'])->group(function () {
        Route::get('patient-portal/dashboard', [PatientPortalController::class, 'dashboard'])->name('patient.portal.dashboard');
        Route::get('patient-portal/payment/{billing?}', [PatientPortalController::class, 'paymentGateway'])->name('patient.portal.payment');
        Route::get('patient-portal/reports/billing/{billing}', [PatientPortalController::class, 'downloadBillingReport'])->name('patient.portal.report.download');
        Route::post('patient-portal/logout', [PatientPortalController::class, 'logout'])->name('patient.portal.logout');
    });
});
