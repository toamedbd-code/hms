<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Pathology;
use App\Models\PharmacyBill;
use App\Models\Radiology;

class BillingService
{
    protected $billingModel;
    protected $billingItemModel;
    protected $pathologyModel;
    protected $radiologyModel;
    protected $pharmacyModel;

    public function __construct(Billing $billingModel, BillItem $billingItemModel, Pathology $pathologyModel, Radiology $radiologyModel, PharmacyBill $pharmacyModel)
    {
        $this->billingModel = $billingModel;
        $this->billingItemModel = $billingItemModel;
        $this->pathologyModel = $pathologyModel;
        $this->radiologyModel = $radiologyModel;
        $this->pharmacyModel = $pharmacyModel;
    }

    public function list()
    {
        return $this->billingModel->whereNull('deleted_at');
    }

    public function all()
    {
        return $this->billingModel->whereNull('deleted_at')->get();
    }

    public function find($id)
    {
        return $this->billingModel->find($id);
    }

    public function findByBillNumber($id)
    {
        return $this->billingModel->where('bill_number', $id)->first();
    }

    /**
     * Find billing with items and related data
     */
    public function findWithItems($id)
    {
        return $this->billingModel->with(['billingItems', 'doctor'])
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Get the last billing record
     */
    public function getLastBilling()
    {
        return $this->billingModel->withTrashed()
            ->orderBy('id', 'desc')
            ->first();
    }

    public function getLastCaseId()
    {
        return $this->billingModel->withTrashed()
            ->orderBy('id', 'desc')
            ->first();
    }

    public function create(array $data)
    {
        return $this->billingModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo = $this->billingModel->findOrFail($id);
        $dataInfo->update($data);
        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo = $this->billingModel->find($id);

        if (!empty($dataInfo)) {
            $dataInfo->deleted_at = date('Y-m-d H:i:s');
            $dataInfo->status = 'Deleted';
            return $dataInfo->save();
        }
        return false;
    }

    public function deleteBIllingWithPathoRadioPharm($id)
    {
        $dataInfo = $this->billingModel->find($id);

        $billNumber = $dataInfo->bill_number ?? '';

        $pathologyBill = $this->pathologyModel->where('bill_no', $billNumber)->first();

        if ($pathologyBill) {
            $pathologyBill->deleted_at = date('Y-m-d H:i:s');
            $pathologyBill->status = 'Deleted';
            $pathologyBill->save();
        }

        $radiologyBill = $this->radiologyModel->where('bill_no', $billNumber)->first();
        if ($radiologyBill) {
            $radiologyBill->deleted_at = date('Y-m-d H:i:s');
            $radiologyBill->status = 'Deleted';
            $radiologyBill->save();
        }


        $pharmacyBill = $this->pharmacyModel->where('bill_no', $billNumber)->first();
       
        if ($pharmacyBill) {
            $pharmacyBill->deleted_at = date('Y-m-d H:i:s');
            $pharmacyBill->status = 'Deleted';
            $pharmacyBill->save();
        }

        if (!empty($dataInfo)) {
            $dataInfo->deleted_at = date('Y-m-d H:i:s');
            $dataInfo->status = 'Deleted';
            return $dataInfo->save();
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo = $this->billingModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();
        return $dataInfo;
    }

    public function activeList()
    {
        return $this->billingModel->with('billItems')->whereNull('deleted_at')->orderBy('id', 'desc')->where('status', 'Active');
    }

    public function pendingList()
    {
        return $this->billingModel->with('billItems')->whereNull('deleted_at')->orderBy('id', 'desc')->where('status', 'Active')->where('payment_status', '!=', 'Paid');
    }

    /**
     * Create billing item
     */
    public function createBillingItem(array $data)
    {
        return $this->billingItemModel->create($data);
    }

    /**
     * Delete billing items for a specific billing
     */
    public function deleteBillingItems($billingId)
    {
        return $this->billingItemModel->where('billing_id', $billingId)->delete();
    }

    /**
     * Get billing items for a specific billing
     */
    public function getBillingItems($billingId)
    {
        return $this->billingItemModel->where('billing_id', $billingId)->get();
    }

    /**
     * Get billing statistics
     */
    public function getBillingStats()
    {
        return [
            'total_bills' => $this->billingModel->whereNull('deleted_at')->count(),
            'active_bills' => $this->billingModel->whereNull('deleted_at')->where('status', 'Active')->count(),
            'total_amount' => $this->billingModel->whereNull('deleted_at')->sum('total_amount'),
            'paid_amount' => $this->billingModel->whereNull('deleted_at')->sum('paid_amt'),
        ];
    }

    /**
     * Search billings
     */
    public function searchBillings($searchTerm)
    {
        return $this->billingModel->whereNull('deleted_at')
            ->where(function ($q) use ($searchTerm) {
                $q->where('bill_no', 'like', '%' . $searchTerm . '%')
                    ->orWhere('patient_mobile', 'like', '%' . $searchTerm . '%')
                    ->orWhere('remarks', 'like', '%' . $searchTerm . '%');
            });
    }

    /**
     * Get billings by date range
     */
    public function getBillingsByDateRange($startDate, $endDate)
    {
        return $this->billingModel->whereNull('deleted_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get billings by doctor
     */
    public function getBillingsByDoctor($doctorId)
    {
        return $this->billingModel->whereNull('deleted_at')
            ->where('doctor_id', $doctorId)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get monthly billing report
     */
    public function getMonthlyReport($year, $month)
    {
        return $this->billingModel->whereNull('deleted_at')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('
                COUNT(*) as total_bills,
                SUM(total_amount) as total_amount,
                SUM(paid_amt) as paid_amount,
                SUM(corp_due_amt) as due_amount,
                AVG(total_amount) as average_bill_amount
            ')
            ->first();
    }

    /**
     * Check if bill number exists
     */
    public function billNumberExists($billNumber, $excludeId = null)
    {
        $query = $this->billingModel->where('bill_no', $billNumber);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get recent billings
     */
    public function getRecentBillings($limit = 10)
    {
        return $this->billingModel->with(['doctor'])
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Duplicate billing (for creating similar bills)
     */
    public function duplicateBilling($id)
    {
        $originalBilling = $this->findWithItems($id);

        if (!$originalBilling) {
            return false;
        }

        // Remove ID and timestamps for duplication
        $billingData = $originalBilling->toArray();
        unset($billingData['id'], $billingData['bill_no'], $billingData['created_at'], $billingData['updated_at']);

        // Create new billing
        $newBilling = $this->create($billingData);

        // Duplicate billing items
        foreach ($originalBilling->billingItems as $item) {
            $itemData = $item->toArray();
            unset($itemData['id'], $itemData['billing_id'], $itemData['created_at'], $itemData['updated_at']);
            $itemData['billing_id'] = $newBilling->id;
            $this->createBillingItem($itemData);
        }

        return $newBilling;
    }
}
