@extends('layouts.backend')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Due Collection</h5>
            </div>

            <div class="card-body">

                <table class="table table-bordered mb-4">
                    <tr>
                        <th>Invoice</th>
                        <td>{{ $billing->invoice_number }}</td>
                    </tr>
                    <tr>
                        <th>Total</th>
                        <td>{{ number_format($billing->total, 2) }}</td>
                    </tr>
                    @php
                        $vatAmount = round((float) ($billing->vat_amount ?? 0), 2);
                        $discountAmount = round((float) ($billing->discount ?? 0), 2);
                        $extraDiscountAmount = round((float) ($billing->extra_flat_discount ?? 0), 2);
                        $effectiveNetAmount = max(0, (float) ($billing->payable_amount ?? ($billing->total - $discountAmount - $extraDiscountAmount + $vatAmount)));
                    @endphp
                    @if ($vatAmount > 0)
                    <tr>
                        <th>VAT</th>
                        <td>{{ number_format($vatAmount, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <th>Net Payable</th>
                        <td>{{ number_format($effectiveNetAmount, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Paid</th>
                        <td>{{ number_format($billing->paid_amt, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Due</th>
                        <td class="text-danger fw-bold">
                            {{ number_format($effectiveDueAmount, 2) }}
                        </td>
                    </tr>
                </table>

                <form id="dueCollectForm" method="POST" action="{{ url('due-collect/'.$billing->id) }}">
                    @csrf

                    <input type="hidden" name="return_to" value="{{ $returnTo ?? '' }}">
                    <input type="hidden" name="submission_token" value="{{ $submissionToken ?? '' }}">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pay Amount</label>
                        <input
    type="number"
    name="amount"
    class="form-control"
    min="0.01"
    max="{{ $effectiveDueAmount }}"
    step="0.01"
    required
>
                        <div class="form-text">Maximum collectable amount is {{ number_format($effectiveDueAmount, 2) }}.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button id="collectDueBtn" type="submit" class="btn btn-success">
                            💰 Collect Due
                        </button>

                        <a href="{{ $returnTo ?? url()->previous() }}" class="btn btn-secondary">
                            ⬅ Back
                        </a>
                    </div>

                </form>

                <script>
                    (function () {
                        const form = document.getElementById('dueCollectForm');
                        const button = document.getElementById('collectDueBtn');

                        if (!form || !button) return;

                        form.addEventListener('submit', function () {
                            if (!form.checkValidity()) return;
                            button.disabled = true;
                            button.innerText = 'Collecting...';
                        });
                    })();
                </script>

            </div>
        </div>

    </div>
</div>
@endsection
