<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminDetail;
use App\Models\ApplyLeave;
use App\Models\StaffAttendance;

class AdminService
{
    protected $adminModel, $adminDetailModel;

    public function __construct(Admin $adminModel, AdminDetail $adminDetailModel)
    {
        $this->adminModel = $adminModel;
        $this->adminDetailModel = $adminDetailModel;
    }

    public function list()
    {
        return $this->adminModel->whereNull('deleted_at');
    }

    public function all()
    {
        return $this->adminModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return $this->adminModel->with('details.department')->find($id);
    }

    public function create(array $data)
    {
        return $this->adminModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo = $this->adminModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo = $this->adminModel->find($id);
        $adminDetails = AdminDetail::where('admin_id', $id)->first();

        if (!empty($dataInfo)) {

            $dataInfo->update([
                'status' => 'Deleted',
                'deleted_at' => date('Y-m-d H:i:s')
            ]);

            if (!empty($adminDetails)) {
                $adminDetails->update([
                    'status' => 'Deleted',
                    'deleted_at' => date('Y-m-d H:i:s')
                ]);
            }

            return $dataInfo;
        }

        return false;
    }

    public function changeStatus($request)
    {
        $dataInfo = $this->adminModel->findOrFail($request->id);

        $dataInfo->update($request->all());

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return $this->adminModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return $this->adminModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

    public function adminDetails($id)
    {
        return $this->adminDetailModel->where('admin_id', $id)->first();
    }

    public function activeDoctors()
    {
        $doctors = $this->adminModel
            ->with('role')
            ->whereNull('deleted_at')
            ->whereHas('role', function ($query) {
                $query->where('name', 'Doctor');
            })
            ->get();

        return $doctors;
    }

    public function getAuthInfo()
    {
        return $this->adminDetailModel->where('admin_id', auth('admin')->user()->id)->with('admin', 'department', 'designation')->first();
    }

    public function getAttendanceRecordsForStaff($staffId)
    {
        $staffDetails = Admin::with('staffAttendance')->where('id', $staffId)->find($staffId);
        return $staffDetails;
    }

    public function getAttendanceDataByStaffId($staffId)
    {
        $attendanceData = StaffAttendance::where('staff_id', $staffId)->get()->toArray();

        $appleaves = ApplyLeave::with('LeaveType')->where('employee_id', $staffId)->get()->toArray();
        
        $staffLeaves = ['leaves' => $appleaves];

        $totals = $this->calculateAttendanceTotals($attendanceData);

        return [
            'attendanceData' => $attendanceData,
            'totals' => $totals,
            'leaves' => $staffLeaves,
        ];
    }

    private function calculateAttendanceTotals($attendanceData)
    {
        $totals = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'holiday' => 0,
        ];

        foreach ($attendanceData as $record) {
            switch ($record['attendance_status']) {
                case 'Present':
                    $totals['present']++;
                    break;
                case 'Absent':
                    $totals['absent']++;
                    break;
                case 'Late':
                    $totals['late']++;
                    break;
                case 'Holiday':
                    $totals['holiday']++;
                    break;
            }
        }

        return $totals;
    }

    public function accountStatement()
    {
        return $this->transactionHistoryModel
            ->with('staff')
            ->where('contact_person_type', 'Staff')
            ->groupBy('history_id')
            ->whereNotNull('payment_id');
    }

    public function staffCount()
    {
        return $this->adminModel->where('status', 'Active')->orWhere('status', 'Inactive')->count();
    }
}
