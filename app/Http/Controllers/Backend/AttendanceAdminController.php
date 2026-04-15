<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:attendance-settings')->only(['devices', 'shifts']);
    }

    public function devices()
    {
        return view('backend.attendance.devices');
    }

    public function shifts()
    {
        return view('backend.attendance.shifts');
    }
}
