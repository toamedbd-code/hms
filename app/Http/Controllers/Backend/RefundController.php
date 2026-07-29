<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    /**
     * Get list of bills with refund amounts available
     */
    public function index()
    {
        $refundBills = Billing::where('status', 'Active')
            ->where(function ($query) {
                $query->where('return_amt', '>', 0)
                    ->orWhere(function ($overpaidQuery) {
                        $overpaidQuery->whereColumn('paid_amt', '>', 'payable_amount')
                            ->where(function ($nested) {
                                $nested->whereNull('return_amt')
                                    ->orWhere('return_amt', '<=', 0);
                            });
                    });
            })
            ->select(['id', 'bill_number', 'invoice_number', 'total', 'paid_amt', 'return_amt', 'payable_amount', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => $refundBills
        ]);
    }

    /**
     * Process refund payment for a bill
     */
    public function processRefund(Request $request)
    {
        $validated = $request->validate([
            'billing_id' => 'required|exists:billings,id',
            'refund_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $billing = Billing::find($validated['billing_id']);

            if (!$billing || $billing->status !== 'Active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bill not found or inactive'
                ], 404);
            }

            $availableRefund = max(0, (float) ($billing->return_amt ?? 0));
            if ($availableRefund <= 0.0001) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No refundable amount available for this bill.'
                ], 422);
            }

            if ($validated['refund_amount'] > $availableRefund) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Refund amount exceeds available refund amount'
                ], 422);
            }

            $billing->return_amt = round(max(0, $availableRefund - $validated['refund_amount']), 2);
            $billing->save();

            // Create a log entry for the refund transaction
            DB::table('refund_transactions')->insert([
                'billing_id' => $billing->id,
                'bill_number' => $billing->bill_number,
                'refund_amount' => $validated['refund_amount'],
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'note' => $validated['note'] ?? null,
                'processed_by' => auth('admin')->id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Refund processed successfully',
                'data' => [
                    'billing_id' => $billing->id,
                    'bill_number' => $billing->bill_number,
                    'refund_amount' => $validated['refund_amount'],
                    'remaining_refund' => $billing->return_amt
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process refund: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get refund details for a specific bill
     */
    public function show($billingId)
    {
        $billing = Billing::select(['id', 'bill_number', 'invoice_number', 'total', 'paid_amt', 'return_amt', 'created_at'])
            ->find($billingId);

        if (!$billing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bill not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $billing
        ]);
    }
}
