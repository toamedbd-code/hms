@extends('layouts.backend')

@section('content')
<div class="modal fade show" id="dueGateModal" tabindex="-1" role="dialog" style="display:block; background:rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Collect Due Before Discharge Certificate</h5>
                <a href="{{ route('backend.ipdpatient.index') }}" class="btn-close" aria-label="Close"></a>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <strong>Due amount is pending.</strong> Please collect the due first to open the discharge certificate.
                </div>

                <table class="table table-bordered mb-3">
                    <tr>
                        <th>Patient</th>
                        <td>{{ $ipdpatient->patient->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Invoice / Billing</th>
                        <td>{{ $billing->invoice_number ?? ($billing->bill_number ?? 'N/A') }}</td>
                    </tr>
                    <tr>
                        <th>Due Amount</th>
                        <td class="text-danger fw-bold">{{ number_format((float) ($billing->due_amount ?? 0), 2) }}</td>
                    </tr>
                </table>

                <form method="POST" action="{{ route('backend.due.collect.store', $billing->id ?? 0) }}">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                    <input type="hidden" name="ipd_patient_id" value="{{ $ipdpatient->id ?? '' }}">
                    <input type="hidden" name="submission_token" value="{{ \Illuminate\Support\Str::uuid() }}">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Collect Amount</label>
                        <input type="number" name="amount" class="form-control" min="0.01" max="{{ (float) ($billing->due_amount ?? 0) }}" step="0.01" required>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">💰 Collect Due</button>
                        <a href="{{ route('backend.ipdpatient.index') }}" class="btn btn-secondary">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
