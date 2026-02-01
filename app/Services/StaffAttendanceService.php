<?php

namespace App\Services;

use App\Models\StaffAttendance;
use Illuminate\Support\Facades\DB;

class StaffAttendanceService
{
    protected $staffAttendanceModel;

    public function __construct(StaffAttendance $staffAttendanceModel)
    {
        $this->staffAttendanceModel = $staffAttendanceModel;
    }

    public function list()
    {
        return $this->staffAttendanceModel->whereNull('deleted_at');
    }

    public function all()
    {
        return $this->staffAttendanceModel->with('role')->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return $this->staffAttendanceModel->find($id);
    }

    public function create(array $data)
    {
        return $this->staffAttendanceModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo = $this->staffAttendanceModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo = $this->staffAttendanceModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($request)
    {
        $dataInfo = $this->staffAttendanceModel->findOrFail($request->id);

        $dataInfo->update(['status' => $request->status]);

        return $dataInfo;
    }

    public function activeList()
    {
        return $this->staffAttendanceModel->with('role')->whereNull('deleted_at')->where('status', 'Active')->get();
    }

    public function getRecordsByDate($date)
    {
        return DB::table('staff_attendances')
            ->whereDate('attendance_date', $date)
            ->get(['staff_id', 'name', 'attendance_status', 'in_time', 'out_time', 'note']);
    }

    public function findByDateAndStaffId($date, $staffId)
    {
        return $this->staffAttendanceModel
            ->whereDate('attendance_date', $date)
            ->where('staff_id', $staffId)
            ->first();
    }

    public function getAttendanceRecordsForMonth($staffId, $month = null, $year = null)
    {
        $month = $month ?? date('m');
        $year = $year ?? date('Y');

        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        return StaffAttendance::where('staff_id', $staffId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();
    }

    public function getAttendanceRecordsForStaff($staffId)
    {
        return StaffAttendance::where('staff_id', $staffId)->get();
    }
}
