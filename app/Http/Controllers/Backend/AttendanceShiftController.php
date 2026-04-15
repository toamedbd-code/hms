<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceShift;

class AttendanceShiftController extends Controller
{
    public function index()
    {
        $this->middleware('auth:admin');
        return AttendanceShift::orderByDesc('id')->get();
    }

    public function store(Request $request)
    {
        $this->middleware('auth:admin');
        $data = $request->validate([
            'employee_code' => 'required|string',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
        ]);

        $shift = AttendanceShift::create($data);
        return response()->json(['data' => $shift], 201);
    }

    public function update(Request $request, AttendanceShift $attendanceShift)
    {
        $this->middleware('auth:admin');
        $data = $request->validate([
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
        ]);

        $attendanceShift->update($data);
        return response()->json(['data' => $attendanceShift]);
    }

    public function destroy(AttendanceShift $attendanceShift)
    {
        $this->middleware('auth:admin');
        $attendanceShift->delete();
        return response()->json(['ok' => true]);
    }
}
