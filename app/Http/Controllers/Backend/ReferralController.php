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
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

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

        $datas = $query->get();

        $grouped = $datas->groupBy('payee_id');
        $page = request()->get('page', 1);
        $perPage = request()->numOfData ?? 10;
        $offset = ($page - 1) * $perPage;

        $formatedDatas = $grouped->values()->map(function ($items, $index) {
            $first = $items->first();
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->payee_id = $first->payee_id;
            $customData->payee_name = $first->payee->name ?? 'N/A';
            $customData->payee_phone = $first->payee->phone ?? 'N/A';
            $customData->bill_count = $items->count();
            $billNumbers = $items->map(function ($item) {
                return $item->billing->bill_number ?? null;
            })->filter()->values();
            $customData->bill_list = $billNumbers->isNotEmpty()
                ? implode(', ', $billNumbers->all())
                : 'N/A';

            $totalCommission = (float) $items->sum('total_commission_amount');
            $paidAmount = (float) $items->sum('paid_amount');
            $pendingAmount = max(0, $totalCommission - $paidAmount);
            $actionId = $first->payee_id . '|' . number_format($pendingAmount, 2, '.', '');

            $customData->commission_amount = '৳' . number_format($totalCommission, 2);
            $customData->paid_amount = '৳' . number_format($paidAmount, 2);
            $customData->pending_amount = '৳' . number_format($pendingAmount, 2);

            if ($pendingAmount <= 0) {
                $customData->paid_status = 'Paid';
            } elseif ($paidAmount > 0) {
                $customData->paid_status = 'Partial Paid';
            } else {
                $customData->paid_status = 'Unpaid';
            }

            $customData->status = getStatusText('Active');
            $customData->hasLink = true;
            $customData->links = [];

            if (Gate::allows('referral-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-blue-500 text-white semi-bold',
                    'linkLabel' => getLinkLabel('Partial Paid', null, null),
                    'link' => route('backend.referral.commission.payment.payee.form', $first->payee_id),
                    'action_name' => 'commission-pay-partial',
                    'action_id' => $actionId,
                ];

                $customData->links[] = [
                    'linkClass' => 'bg-green-600 text-white semi-bold',
                    'linkLabel' => getLinkLabel('Paid', null, null),
                    'link' => route('backend.referral.commission.payment.payee.paid', $first->payee_id),
                    'action_name' => 'commission-pay-full',
                    'action_id' => $actionId,
                ];
            }

            $customData->links[] = [
                'linkClass' => 'bg-slate-600 text-white semi-bold',
                'linkLabel' => getLinkLabel('Print', null, null),
                'link' => route('backend.referral.commission.payment.payee.print', $first->payee_id),
                'target' => '_blank',
            ];

            return $customData;
        })->slice($offset, $perPage)->values();

        return regeneratePagination($formatedDatas, $grouped->count(), $perPage, $page);
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center w-10'],
            ['fieldName' => 'payee_name', 'class' => 'text-left w-72 whitespace-nowrap'],
            ['fieldName' => 'payee_phone', 'class' => 'text-center'],
            ['fieldName' => 'bill_count', 'class' => 'text-center'],
            ['fieldName' => 'bill_list', 'class' => 'text-left'],
            ['fieldName' => 'commission_amount', 'class' => 'text-center'],
            ['fieldName' => 'paid_amount', 'class' => 'text-center'],
            ['fieldName' => 'pending_amount', 'class' => 'text-center'],
            ['fieldName' => 'paid_status', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Payee Name',
            'Phone',
            'Bill Count',
            'Bill List',
            'Commission Total',
            'Paid',
            'Pending',
            'Paid Status',
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

    public function commissionPayment(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_type' => 'required|in:paid,partial',
            'amount' => 'required_if:payment_type,partial|nullable|numeric|min:0.01',
        ]);

        DB::beginTransaction();

        try {
            Log::info('Referral commission payment requested', [
                'referral_id' => $id,
                'payment_type' => $validated['payment_type'],
                'amount' => $validated['amount'] ?? null,
            ]);

            $referral = $this->referralService->recordCommissionPayment(
                $id,
                $validated['payment_type'],
                $validated['amount'] ?? null
            );

            Log::info('Referral commission payment processed', [
                'referral_id' => $referral->id,
                'bill_number' => $referral->billing->bill_number ?? null,
                'paid_amount' => $referral->paid_amount,
                'paid_status' => $referral->paid_status,
            ]);

            $message = 'Commission payment updated. Paid: ৳' . number_format($referral->paid_amount, 2);
            $this->storeAdminWorkLog($referral->id, 'referrals', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralController', 'payCommission', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Failed to update commission payment.');
        }
    }

    public function commissionPaymentPaid($id)
    {
        DB::beginTransaction();

        try {
            $referral = $this->referralService->recordCommissionPayment($id, 'paid');

            $message = 'Commission payment updated. Paid: ৳' . number_format($referral->paid_amount, 2);
            $this->storeAdminWorkLog($referral->id, 'referrals', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralController', 'commissionPaymentPaid', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Failed to update commission payment.');
        }
    }

    public function commissionPaymentForm($id)
    {
        $referral = $this->referralService->find($id);

        if (!$referral) {
            return redirect()
                ->route('backend.referral.index')
                ->with('errorMessage', 'Referral not found.');
        }

        $totalCommission = (float) $referral->total_commission_amount;
        $paidAmount = (float) $referral->paid_amount;
        $pendingAmount = max(0, $totalCommission - $paidAmount);

        return view('backend.referral.commission_payment', [
            'referral' => $referral,
            'pendingAmount' => $pendingAmount,
        ]);
    }

    public function commissionPaymentPayeePaid($payeeId)
    {
        DB::beginTransaction();

        try {
            $referrals = $this->referralService->recordCommissionPaymentByPayee($payeeId, 'paid');

            if (!$referrals) {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'Referral not found.');
            }

            $message = 'Commission payment updated for payee.';
            $this->storeAdminWorkLog($payeeId, 'referrals', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralController', 'commissionPaymentPayeePaid', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Failed to update commission payment.');
        }
    }

    public function commissionPaymentPayeeForm($payeeId)
    {
        $referrals = $this->referralService->activeList()
            ->where('payee_id', $payeeId)
            ->get();

        if ($referrals->isEmpty()) {
            return redirect()
                ->route('backend.referral.index')
                ->with('errorMessage', 'Referral not found.');
        }

        $payee = $referrals->first()->payee;
        $totalCommission = (float) $referrals->sum('total_commission_amount');
        $paidAmount = (float) $referrals->sum('paid_amount');
        $pendingAmount = max(0, $totalCommission - $paidAmount);

        return view('backend.referral.commission_payment_payee', [
            'payee' => $payee,
            'pendingAmount' => $pendingAmount,
            'totalCommission' => $totalCommission,
            'paidAmount' => $paidAmount,
        ]);
    }

    public function commissionPaymentPayeePrint($payeeId)
    {
        $referrals = $this->referralService->activeList()
            ->where('payee_id', $payeeId)
            ->get();

        if ($referrals->isEmpty()) {
            return redirect()
                ->route('backend.referral.index')
                ->with('errorMessage', 'Referral not found.');
        }

        $payee = $referrals->first()->payee;
        $totalCommission = (float) $referrals->sum('total_commission_amount');
        $paidAmount = (float) $referrals->sum('paid_amount');
        $pendingAmount = max(0, $totalCommission - $paidAmount);
        $billList = $referrals->map(function ($referral) {
            return $referral->billing->bill_number ?? null;
        })->filter()->unique()->values()->implode(', ');

        $dateValues = $referrals->map(function ($referral) {
            return $referral->date ?? null;
        })->filter()->map(function ($date) {
            return Carbon::parse($date);
        })->values();

        $billDateRange = 'N/A';
        if ($dateValues->isNotEmpty()) {
            $minDate = $dateValues->min();
            $maxDate = $dateValues->max();
            $billDateRange = $minDate->format('d-M-Y');
            if ($maxDate->notEqualTo($minDate)) {
                $billDateRange .= ' to ' . $maxDate->format('d-M-Y');
            }
        }

        $billRows = $referrals->map(function ($referral) {
            $totalCommission = (float) ($referral->total_commission_amount ?? 0);
            $paidAmount = (float) ($referral->paid_amount ?? 0);
            $pendingAmount = max(0, $totalCommission - $paidAmount);
            return [
                'bill_no' => $referral->billing->bill_number ?? 'N/A',
                'date' => $referral->date ? Carbon::parse($referral->date)->format('d-M-Y') : 'N/A',
                'commission' => $totalCommission,
                'paid' => $paidAmount,
                'pending' => $pendingAmount,
            ];
        })->values();

        return view('backend.referral.commission_payment_payee_print', [
            'payee' => $payee,
            'pendingAmount' => $pendingAmount,
            'totalCommission' => $totalCommission,
            'paidAmount' => $paidAmount,
            'billList' => $billList,
            'billDateRange' => $billDateRange,
            'billRows' => $billRows,
        ]);
    }

    public function commissionPaymentPayee(Request $request, $payeeId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();

        try {
            $referrals = $this->referralService->recordCommissionPaymentByPayee(
                $payeeId,
                'partial',
                $validated['amount']
            );

            if (!$referrals) {
                DB::rollBack();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'Referral not found.');
            }

            $message = 'Commission payment updated for payee.';
            $this->storeAdminWorkLog($payeeId, 'referrals', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralController', 'commissionPaymentPayee', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Failed to update commission payment.');
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
