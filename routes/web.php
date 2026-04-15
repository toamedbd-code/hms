<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\Backend\AttendanceAdminController;
use App\Http\Controllers\Backend\FaceAttendanceController;
use App\Http\Controllers\Backend\ReportController as BackendReportController;
use App\Http\Controllers\Payment\BkashController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Test-only route to allow unauthenticated mark calls during debugging.
Route::post('test/attendance/face/mark', [FaceAttendanceController::class, 'markTest']);

// bKash payment webhook (public)
Route::post('payment/bkash/webhook', [\App\Http\Controllers\Payment\BkashWebhookController::class, 'handle']);

// Initiate bKash checkout (admin uses this form)
Route::post('payment/bkash/initiate', [BkashController::class, 'initiate'])->name('payment.bkash.initiate');

// Sandbox simulate approval (internal testing only)
Route::get('payment/bkash/simulate/{payment}/approve', [BkashController::class, 'simulateApprove'])->name('payment.bkash.simulate.approve');

// Temporary debug route for PDF generation troubleshooting
Route::get('/debug-report-pdf', [BackendReportController::class, 'debugPdf']);

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/test-upload', function () {
    return ini_get('upload_tmp_dir');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

require __DIR__ . '/backend.php';

// Attendance kiosk (public) - PIN protected for POST
Route::get('/kiosk/attendance/face', [\App\Http\Controllers\Kiosk\FaceKioskAttendanceController::class, 'index']);
Route::post('/kiosk/attendance/face/mark', [\App\Http\Controllers\Kiosk\FaceKioskAttendanceController::class, 'mark'])->middleware('kiosk.pin');

// Attendance admin UI pages
Route::middleware(['auth:admin'])->group(function () {
    Route::get('/admin/attendance/devices', [AttendanceAdminController::class, 'devices'])->name('admin.attendance.devices');
    Route::get('/admin/attendance/shifts', [AttendanceAdminController::class, 'shifts'])->name('admin.attendance.shifts');
});