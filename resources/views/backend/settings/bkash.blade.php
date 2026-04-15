@extends('layouts.backend')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                @if(Route::has('backend.websetting.create'))
                                    <a href="{{ route('backend.websetting.create') }}" class="btn btn-sm btn-secondary me-2">Back</a>
                                @else
                                    <a href="javascript:history.back()" class="btn btn-sm btn-secondary me-2">Back</a>
                                @endif
                                <h5 class="mb-0">bKash Settings</h5>
                            </div>
                            <small class="text-muted">Configure merchant credentials & monthly billing</small>
                        </div>

                    <div class="card-body">
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <form method="POST" action="{{ route('backend.settings.payment.bkash.store') }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">App Key</label>
                                <input name="app_key" class="form-control" value="{{ old('app_key', optional($setting)->app_key) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">App Secret</label>
                                <input name="app_secret" class="form-control" value="{{ old('app_secret', optional($setting)->app_secret) }}">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Merchant Username</label>
                                    <input name="username" class="form-control" value="{{ old('username', optional($setting)->username) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Merchant Number</label>
                                    <input name="merchant_number" class="form-control" value="{{ old('merchant_number', optional($setting)->merchant_number) }}">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Merchant Password</label>
                                <input name="password" type="password" class="form-control" value="">
                                <small class="form-text text-muted">Leave blank to keep existing password.</small>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Monthly Amount (BDT)</label>
                                    <input name="monthly_amount" type="number" step="0.01" class="form-control" value="{{ old('monthly_amount', optional($setting)->monthly_amount ?? 0) }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Sandbox Mode</label>
                                    <select name="is_sandbox" class="form-select">
                                        <option value="1" {{ optional($setting)->is_sandbox ? 'selected' : '' }}>Yes</option>
                                        <option value="0" {{ optional($setting) && !optional($setting)->is_sandbox ? 'selected' : '' }}>No</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Enable bKash Payments</label>
                                    <select name="is_enabled" class="form-select">
                                        <option value="1" {{ optional($setting)->is_enabled ? 'selected' : '' }}>Enabled</option>
                                        <option value="0" {{ optional($setting) && !optional($setting)->is_enabled ? 'selected' : '' }}>Disabled</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary">Save</button>
                                @if(optional($setting)->is_enabled && (float) optional($setting)->monthly_amount > 0)
                                    <form method="POST" action="{{ route('backend.payment.bkash.initiate') }}" style="display:inline">
                                        @csrf
                                        <input type="hidden" name="amount" value="{{ optional($setting)->monthly_amount }}">
                                        <button type="submit" class="btn btn-success">Pay Monthly Now (BDT {{ number_format(optional($setting)->monthly_amount, 2) }})</button>
                                    </form>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var msg = @json(session('success'));
                var toast = document.createElement('div');
                toast.textContent = msg;
                Object.assign(toast.style, {
                    position: 'fixed',
                    top: '1rem',
                    right: '1rem',
                    background: '#28a745',
                    color: '#fff',
                    padding: '0.75rem 1rem',
                    borderRadius: '0.375rem',
                    boxShadow: '0 2px 6px rgba(0,0,0,0.2)',
                    zIndex: 1060,
                    opacity: '0',
                    transition: 'opacity 0.25s ease-in-out, transform 0.25s ease-in-out',
                    transform: 'translateY(-10px)'
                });
                document.body.appendChild(toast);
                requestAnimationFrame(function(){
                    toast.style.opacity = '1';
                    toast.style.transform = 'translateY(0)';
                });
                setTimeout(function(){
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(-10px)';
                    setTimeout(function(){ toast.remove(); }, 250);
                }, 3500);
            });
        </script>
    @endif

@endsection
