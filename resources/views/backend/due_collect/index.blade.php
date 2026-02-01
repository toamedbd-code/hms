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
                    <tr>
                        <th>Paid</th>
                        <td>{{ number_format($billing->paid_amt, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Due</th>
                        <td class="text-danger fw-bold">
                            {{ number_format($billing->due_amount, 2) }}
                        </td>
                    </tr>
                </table>

                <form method="POST" action="{{ url('due-collect/'.$billing->id) }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pay Amount</label>
                        <input
    type="number"
    name="amount"
    class="form-control"
    min="1"
    max="{{ $billing->due_amount }}"
    required
>

                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            💰 Collect Due
                        </button>

                        <a href="{{ url()->previous() }}" class="btn btn-secondary">
                            ⬅ Back
                        </a>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>
@endsection
