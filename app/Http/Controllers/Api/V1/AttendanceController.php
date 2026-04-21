<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $query = Attendance::with('employee')->orderBy('date', 'desc');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->get('employee_id'));
        }
        if ($request->filled('date')) {
            $query->where('date', $request->get('date'));
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'check_in' => 'nullable|date_format:H:i:s',
            'check_out' => 'nullable|date_format:H:i:s',
            'device_id' => 'nullable|string|max:100',
            'status' => 'nullable|in:present,absent,leave',
        ]);

        $attendance = Attendance::updateOrCreate([
            'employee_id' => $data['employee_id'],
            'date' => $data['date'],
        ], $data);

        return response()->json($attendance, 201);
    }

    public function show(Attendance $attendance)
    {
        return response()->json($attendance);
    }

    public function update(Request $request, Attendance $attendance)
    {
        $data = $request->validate([
            'check_in' => 'nullable|date_format:H:i:s',
            'check_out' => 'nullable|date_format:H:i:s',
            'device_id' => 'nullable|string|max:100',
            'status' => 'nullable|in:present,absent,leave',
        ]);

        $attendance->update($data);
        return response()->json($attendance);
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return response()->json(null, 204);
    }
}
