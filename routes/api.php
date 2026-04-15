<?php

use App\Http\Controllers\Api\V1\IpdPrescriptionController;
// use App\Http\Controllers\Api\V1\ModuleMakerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'v1', 'as' => 'v1.'], function () {
    // Route::post('/module-make', [ModuleMakerController::class, 'index'])->name('moduleMaker');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('ipd-admissions/{ipdpatient}/prescription', [IpdPrescriptionController::class, 'show'])
            ->name('ipd-admissions.prescription.show');

        Route::match(['post', 'put'], 'ipd-admissions/{ipdpatient}/prescription', [IpdPrescriptionController::class, 'upsert'])
            ->name('ipd-admissions.prescription.upsert');
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Attendance device webhook (devices POST here)
Route::post('/attendance/device/webhook', [\App\Http\Controllers\Backend\AttendanceDeviceController::class, 'webhook']);
Route::match(['GET', 'POST'], '/attendance/device/adms', [\App\Http\Controllers\Backend\AttendanceDeviceController::class, 'admsWebhook']);

// Pathology machine webhook (Hematology/Ultrasound integration)
Route::post('/pathology/machine/webhook', \App\Http\Controllers\Api\PathologyMachineWebhookController::class);

// Admin routes for device management
Route::middleware('auth:admin')->group(function () {
    Route::post('/attendance/device/register', [\App\Http\Controllers\Backend\AttendanceDeviceController::class, 'register']);
    Route::get('/attendance/devices', [\App\Http\Controllers\Backend\AttendanceDeviceController::class, 'index']);
    Route::delete('/attendance/device/{id}', [\App\Http\Controllers\Backend\AttendanceDeviceController::class, 'destroy']);
    // Attendance shift overrides (per-employee)
    Route::get('/attendance/shifts', [\App\Http\Controllers\Backend\AttendanceShiftController::class, 'index']);
    Route::post('/attendance/shifts', [\App\Http\Controllers\Backend\AttendanceShiftController::class, 'store']);
    Route::put('/attendance/shifts/{attendanceShift}', [\App\Http\Controllers\Backend\AttendanceShiftController::class, 'update']);
    Route::delete('/attendance/shifts/{attendanceShift}', [\App\Http\Controllers\Backend\AttendanceShiftController::class, 'destroy']);
});
