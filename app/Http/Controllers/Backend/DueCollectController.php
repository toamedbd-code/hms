<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\DueCollection;
use Carbon\Carbon;

class DueCollectController extends Controller
{
    /**
     * 🔹 Due Collect Form
     */
    public function index($id)
    {//
       // dd($id);
        $billing = Billing::findOrFail($id);

        // safety check
        if ($billing->due_amount <= 0) {
            return redirect()
                ->route('pending.billings')
                ->with('error', 'No due amount available');
        }

        return view('backend.due_collect.index', compact('billing'));
    }

    /**
     * 🔹 Store Due Payment (FINAL LOGIC)
     */
  public function store(Request $request, $id)
{
   $request->validate([
    'amount' => 'required|numeric|min:1',
]);

$amount = $request->amount;


    $billing = Billing::findOrFail($id);

    // save due collection
    DueCollection::create([
        'billing_id'       => $billing->id,
        'collected_amount' => $request->amount,
        'collected_at'     => now(),
    ]);

    // update billing
    $billing->paid_amt += $request->amount;
    $billing->due_amount -= $request->amount;

    if ($billing->due_amount <= 0) {
        $billing->payment_status = 'Paid';
        $billing->due_amount = 0;
    }

    $billing->save();

    return redirect()->route('backend.billing.list')
        ->with('success', 'Due collected successfully');
}
}