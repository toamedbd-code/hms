<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Referral Commission Payment</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 30px; }
        .card { max-width: 520px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 12px; }
        .row { margin-bottom: 10px; font-size: 14px; }
        .label { color: #374151; font-weight: bold; margin-right: 6px; }
        .input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .actions { margin-top: 16px; display: flex; gap: 10px; }
        .btn { padding: 10px 14px; border-radius: 6px; border: 0; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; text-decoration: none; display: inline-flex; align-items: center; }
        .note { font-size: 12px; color: #6b7280; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">Referral Commission Payment</div>
        <div class="row"><span class="label">Bill:</span>{{ $referral->billing->bill_number ?? 'N/A' }}</div>
        <div class="row"><span class="label">Payee:</span>{{ $referral->payee->name ?? 'N/A' }}</div>
        <div class="row"><span class="label">Total কমিশন:</span>৳{{ number_format($referral->total_commission_amount ?? 0, 2) }}</div>
        <div class="row"><span class="label">Already Paid:</span>৳{{ number_format($referral->paid_amount ?? 0, 2) }}</div>
        <div class="row"><span class="label">Pending:</span>৳{{ number_format($pendingAmount, 2) }}</div>

        <form method="POST" action="{{ route('backend.referral.commission.payment', $referral->id) }}">
            @csrf
            <input type="hidden" name="payment_type" value="partial">
            <div class="row">
                <label class="label" for="amount">Partial Paid Amount</label>
                <input id="amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $pendingAmount }}" class="input" required>
                <div class="note">Max: ৳{{ number_format($pendingAmount, 2) }}</div>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Submit Payment</button>
                <a href="{{ route('backend.referral.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
