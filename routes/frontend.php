<?php
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\AssetController;
use App\Http\Controllers\Frontend\DebugController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

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


Route::get('/cache-clear', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    Artisan::call('clear-compiled');
    Artisan::call('optimize:clear');
    Artisan::call('storage:link');
    // Artisan::call('optimize');
    session()->flash('message', 'System Updated Successfully.');
    return redirect()->route('frontend.home');
});

Route::get('/', [HomeController::class, 'index'])->name('home');

// Serve storage files from storage/app/public when public/storage symlink is missing
Route::get('/storage/{path}', [AssetController::class, 'storage'])->where('path', '.*');

// Debug: list featured doctors with resolved image URLs and existence checks
Route::get('/debug/featured-doctors', [DebugController::class, 'featuredDoctors'])->name('debug.featured.doctors');