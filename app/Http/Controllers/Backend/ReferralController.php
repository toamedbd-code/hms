<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferralRequest;
use App\Models\ReferralPerson;
use App\Services\BillingService;
use App\Services\ReferralPersonService;
use Illuminate\Support\Facades\DB;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ReferralController extends Controller
{
    use SystemTrait;

    protected $referralService, $billingService, $referrersService;

    public function __construct(ReferralService $referralService, BillingService $billingService, ReferralPersonService $referrersService)
    {
        $this->referralService = $referralService;
        $this->billingService = $billingService;
        $this->referrersService = $referrersService;

        $this->middleware('auth:admin');
        $this->middleware('permission:referral-list');
        $this->middleware('permission:referral-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:referral-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:referral-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:referral-list-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/Referral/Index',
            [
                'pageTitle' => fn() => 'Referral List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->referralService->list();

        if (request()->filled('payee_name')) {
            $query->whereHas('payee', function ($q) {
                $q->where('name', 'like', request()->payee_name . '%');
            });
        }

        if (request()->filled('bill_number')) {
            $query->whereHas('billing', function ($q) {
                $q->where('bill_number', 'like', request()->bill_number . '%');
            });
        }

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->bill_number = $data->billing->bill_number ?? 'N/A';
            $customData->payee_name = $data->payee->name ?? 'N/A';
            $customData->payee_phone = $data->payee->phone ?? 'N/A';
            $customData->date = $data->date->format('d-M-Y');
            $customData->total_bill_amount = '৳' . number_format($data->total_bill_amount, 2);
            $customData->commission_amount = '৳' . number_format($data->total_commission_amount, 2);
            $customData->status = getStatusText($data->status);

            $user = auth('admin')->user();

            
            $customData->hasLink = true;

            $customData->links = [];
            
            if ($user->can('referral-list-status-change')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.referral.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('referral-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.referral.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('referral-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.referral.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }


            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'bill_number', 'class' => 'text-center'],
            ['fieldName' => 'payee_name', 'class' => 'text-center'],
            ['fieldName' => 'payee_phone', 'class' => 'text-center'],
            ['fieldName' => 'date', 'class' => 'text-center'],
            ['fieldName' => 'total_bill_amount', 'class' => 'text-center'],
            ['fieldName' => 'commission_amount', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Bill Number',
            'Payee Name',
            'Phone',
            'Date',
            'Bill Amount',
            'Commission',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        $billings = $this->billingService->activeList()
            ->select('id', 'bill_number', 'invoice_number', 'patient_mobile', 'payable_amount')
            ->get()
            ->map(function ($billing) {
                return [
                    'id' => $billing->id,
                    'label' => $billing->bill_number . ' - ' . $billing->patient_mobile . ' (৳' . number_format($billing->payable_amount, 2) . ')',
                    'bill_number' => $billing->bill_number,
                    'invoice_number' => $billing->invoice_number,
                    'patient_mobile' => $billing->patient_mobile,
                    'amount' => $billing->payable_amount
                ];
            });

        $referrers = ReferralPerson::where('status', 'Active')
            ->select('id', 'name', 'phone', 'standard_commission', 'pathology_commission', 'radiology_commission', 'pharmacy_commission')
            ->get()
            ->map(function ($referrer) {
                return [
                    'id' => $referrer->id,
                    'label' => $referrer->name . ' - ' . $referrer->phone,
                    'name' => $referrer->name,
                    'phone' => $referrer->phone,
                    'standard_commission' => $referrer->standard_commission,
                    'pathology_commission' => $referrer->pathology_commission,
                    'radiology_commission' => $referrer->radiology_commission,
                    'pharmacy_commission' => $referrer->pharmacy_commission,
                ];
            });

        return Inertia::render(
            'Backend/Referral/Form',
            [
                'pageTitle' => fn() => 'Referral Create',
                'billings' => fn() => $billings,
                'referrers' => fn() => $referrers
            ]
        );
    }

    public function store(ReferralRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            if ($this->referralService->billingHasReferral($data['billing_id'], $data['payee_id'])) {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'A referral for this bill and payee combination already exists.');
            }

            $referral = $this->referralService->create($data);

            if ($referral) {
                $message = 'Referral created successfully with commission amount: ৳' . number_format($referral->total_commission_amount, 2);
                $this->storeAdminWorkLog($referral->id, 'referrals', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'Failed to create referral.');
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralController', 'store', substr($err->getMessage(), 0, 1000));
            DB::commit();

            return redirect()
                ->back()
                ->with('errorMessage', 'Server error occurred. Please try again.');
        }
    }

    public function show($id)
    {
        $commissionData = $this->referralService->getCommissionBreakdown($id);

        if (!$commissionData) {
            return redirect()
                ->route('backend.referral.index')
                ->with('errorMessage', 'Referral not found.');
        }

        return Inertia::render(
            'Backend/Referral/Show',
            [
                'pageTitle' => fn() => 'Referral Details',
                'referral' => fn() => $commissionData['referral'],
                'payee' => fn() => $commissionData['payee'],
                'billing' => fn() => $commissionData['billing'],
                'commissionBreakdown' => fn() => $commissionData['category_breakdown'],
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'Referral Manage'],
                    ['link' => route('backend.referral.index'), 'title' => 'Referral List'],
                    ['link' => null, 'title' => 'Referral Details'],
                ],
            ]
        );
    }

    public function edit($id)
    {
        $referral = $this->referralService->find($id);

        if (!$referral) {
            return redirect()
                ->route('backend.referral.index')
                ->with('errorMessage', 'Referral not found.');
        }

        $billings = $this->billingService->activeList()
            ->select('id', 'bill_number', 'invoice_number', 'patient_mobile', 'payable_amount')
            ->get()
            ->map(function ($billing) {
                return [
                    'id' => $billing->id,
                    'label' => $billing->bill_number . ' - ' . $billing->patient_mobile . ' (৳' . number_format($billing->payable_amount, 2) . ')',
                    'bill_number' => $billing->bill_number,
                ];
            });

        $referrers = ReferralPerson::where('status', 'Active')
            ->select('id', 'name', 'phone', 'standard_commission', 'pathology_commission', 'radiology_commission', 'pharmacy_commission')
            ->get()
            ->map(function ($referrer) {
                return [
                    'id' => $referrer->id,
                    'label' => $referrer->name . ' - ' . $referrer->phone,
                    'name' => $referrer->name,
                    'phone' => $referrer->phone,
                    'standard_commission' => $referrer->standard_commission,
                    'pathology_commission' => $referrer->pathology_commission,
                    'radiology_commission' => $referrer->radiology_commission,
                    'pharmacy_commission' => $referrer->pharmacy_commission,
                ];
            });

        return Inertia::render(
            'Backend/Referral/Form',
            [
                'pageTitle' => fn() => 'Referral Edit',
                'referral' => fn() => $referral,
                'id' => fn() => $id,
                'billings' => fn() => $billings,
                'referrers' => fn() => $referrers,
            ]
        );
    }

    public function update(ReferralRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            if ($this->referralService->billingHasReferral($data['billing_id'], $data['payee_id'], $id)) {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'A referral for this bill and payee combination already exists.');
            }

            $referral = $this->referralService->update($data, $id);

            if ($referral) {
                $message = 'Referral updated successfully. New commission amount: ৳' . number_format($referral->total_commission_amount, 2);
                $this->storeAdminWorkLog($referral->id, 'referrals', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'Failed to update referral.');
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralController', 'update', substr($err->getMessage(), 0, 1000));
            DB::commit();

            return redirect()
                ->back()
                ->with('errorMessage', 'Server error occurred. Please try again.');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            if ($this->referralService->delete($id)) {
                $message = 'Referral deleted successfully';
                $this->storeAdminWorkLog($id, 'referrals', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'Failed to delete referral.');
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralController', 'destroy', substr($err->getMessage(), 0, 1000));
            DB::commit();

            return redirect()
                ->back()
                ->with('errorMessage', 'Server error occurred. Please try again.');
        }
    }

    public function changeStatus(Request $request, $id, $status)
    {
        DB::beginTransaction();

        try {
            $referral = $this->referralService->changeStatus($id, $status);

            if ($referral->wasChanged()) {
                $message = 'Referral ' . $status . ' successfully';
                $this->storeAdminWorkLog($referral->id, 'referrals', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'Failed to change referral status.');
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();

            return redirect()
                ->back()
                ->with('errorMessage', 'Server error occurred. Please try again.');
        }
    }

    public function commissionPreview(Request $request)
    {

        $validated = $request->validate([
            'billing_id' => 'required',
            'payee_id' => 'required',
        ]);

        $billing = $this->billingService->find($validated['billing_id']);
        $payee = $this->referrersService->find($validated['payee_id']);

        if (!$billing || !$payee) {
            return response()->json([
                'error' => 'Invalid billing or payee'
            ], 400);
        }

        $billItems = $billing->billItems;
        $categoryBreakdown = [];
        $totalCommission = 0;
        $totalBillAmount = 0;


        foreach ($billItems as $item) {
            $category = strtolower($item->category);
            $itemAmount = $item->net_amount;
            $totalBillAmount += $itemAmount;

            if (!isset($categoryBreakdown[$category])) {
                $categoryBreakdown[$category] = [
                    'amount' => 0,
                    'commission_rate' => 0,
                    'commission_amount' => 0
                ];
            }

            $categoryBreakdown[$category]['amount'] += $itemAmount;

            $commissionRate = $this->getCommissionRateByCategory($payee, $category);
            $categoryBreakdown[$category]['commission_rate'] = $commissionRate;

            $itemCommission = ($itemAmount * $commissionRate) / 100;
            $categoryBreakdown[$category]['commission_amount'] += $itemCommission;
            $totalCommission += $itemCommission;
        }

        return response()->json([
            'total_bill_amount' => round($totalBillAmount, 2),
            'total_commission' => round($totalCommission, 2),
            'category_breakdown' => $categoryBreakdown,
        ]);
    }

    private function getCommissionRateByCategory($payee, $category)
    {
        $getRate = function ($value) {
            if ($value === null || $value === '' || $value === '0' || $value === '0.00') {
                return 0;
            }
            return (float) $value;
        };

        switch (strtolower(trim($category))) {
            case 'pathology':
                return $getRate($payee->pathology_commission);

            case 'radiology':
                return $getRate($payee->radiology_commission);

            case 'medicine':
            case 'pharmacy':
                return $getRate($payee->pharmacy_commission);

            case 'opd':
                return $getRate($payee->opd_commission);

            case 'ipd':
                return $getRate($payee->ipd_commission);

            case 'blood_bank':
            case 'bloodbank':
                return $getRate($payee->blood_bank_commission);

            case 'ambulance':
                return $getRate($payee->ambulance_commission);

            default:
                return $getRate($payee->standard_commission);
        }
    }
}
