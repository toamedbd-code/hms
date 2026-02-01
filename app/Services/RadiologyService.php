<?php

namespace App\Services;

use App\Models\Radiology;
use App\Models\RadiologyTest;
use Illuminate\Database\Eloquent\Builder;

class RadiologyService
{
    protected $radiologyModel;

    public function __construct(Radiology $radiologyModel)
    {
        $this->radiologyModel = $radiologyModel;
    }

    public function list()
    {
        return  $this->radiologyModel->whereNull('deleted_at')->orderby('id', 'desc');
    }

    public function all()
    {
        return  $this->radiologyModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->radiologyModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->radiologyModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->radiologyModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->radiologyModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->radiologyModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    /**
     * Get active radiologies
     */
    public function getActive(): Builder
    {
        return Radiology::active()
            ->with(['patient', 'referralDoctor'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get radiologies by patient
     */
    public function getByPatient(int $patientId): Builder
    {
        return Radiology::where('patient_id', $patientId)
            ->where('status', '!=', 'Deleted')
            ->with(['referralDoctor', 'radiologyTests'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get radiologies by doctor
     */
    public function getByDoctor(int $doctorId): Builder
    {
        return Radiology::where('referral_doctor_id', $doctorId)
            ->where('status', '!=', 'Deleted')
            ->with(['patient', 'radiologyTests'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get pending payment radiologies
     */
    public function getPendingPayments(): Builder
    {
        return Radiology::whereColumn('payment_amount', '<', 'net_amount')
            ->where('status', 'Active')
            ->with(['patient', 'referralDoctor'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get today's radiologies
     */
    public function getTodaysRadiologies(): Builder
    {
        return Radiology::whereDate('created_at', now()->toDateString())
            ->where('status', '!=', 'Deleted')
            ->with(['patient', 'referralDoctor'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Calculate total revenue for a date range
     */
    public function getTotalRevenue($startDate = null, $endDate = null): float
    {
        $query = Radiology::where('status', 'Active');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query->sum('payment_amount');
    }

    /**
     * Get radiology statistics
     */
    public function getStatistics($startDate = null, $endDate = null): array
    {
        $query = Radiology::where('status', 'Active');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $totalRadiologies = $query->count();
        $totalRevenue = $query->sum('payment_amount');
        $totalDue = $query->sum(\DB::raw('net_amount - payment_amount'));
        $averageAmount = $totalRadiologies > 0 ? $totalRevenue / $totalRadiologies : 0;

        return [
            'total_radiologies' => $totalRadiologies,
            'total_revenue' => $totalRevenue,
            'total_due' => $totalDue,
            'average_amount' => $averageAmount,
        ];
    }

    /**
     * Search radiologies
     */
    public function search(string $query): Builder
    {
        return Radiology::where('status', '!=', 'Deleted')
            ->where(function ($q) use ($query) {
                $q->where('case_id', 'like', "%{$query}%")
                    ->orWhere('bill_no', 'like', "%{$query}%")
                    ->orWhere('radiology_no', 'like', "%{$query}%")
                    ->orWhereHas('patient', function ($patientQuery) use ($query) {
                        $patientQuery->where('name', 'like', "%{$query}%")
                            ->orWhere('mobile', 'like', "%{$query}%");
                    });
            })
            ->with(['patient', 'referralDoctor'])
            ->orderBy('created_at', 'desc');
    }
}