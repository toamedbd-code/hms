@extends('layouts.backend')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">OPD Due Collection</h5>
            </div>

            <div class="card-body">

                <table class="table table-bordered mb-4">
                    <tr>
                        <th>OPD Invoice</th>
                        <td>OPD-{{ str_pad((string) $opdPatient->id, 4, '0', STR_PAD_LEFT) }}</td>
                    </tr>
                    <tr>
                        <th>Patient</th>
                        <td>{{ $opdPatient?->patient?->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Total</th>
                        <td>{{ number_format((float) $opdPatient->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Paid</th>
                        <td>{{ number_format((float) $opdPatient->paid_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Due</th>
                        <td class="text-danger fw-bold">
                            {{ number_format((float) $opdPatient->balance_amount, 2) }}
                        </td>
                    </tr>
                </table>

                <form method="POST" action="{{ route('backend.opd.due.collect.store', $opdPatient->id) }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pay Amount</label>
                        <input
                            type="number"
                            name="amount"
                            class="form-control"
                            min="1"
                            max="{{ (float) $opdPatient->balance_amount }}"
                            step="0.01"
                            required
                        >
                        @error('amount')
                            <div class="text-danger mt-1">{{ $message }}</div>
                        @enderror
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
