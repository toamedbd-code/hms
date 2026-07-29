@extends('layouts.backend')

@section('content')
    @php
        $settingsBackRoute = null;
        if (Route::has('backend.dashboard-setting.edit')) {
            $settingsBackRoute = route('backend.dashboard-setting.edit');
        } elseif (Route::has('backend.report-setting.edit')) {
            $settingsBackRoute = route('backend.report-setting.edit');
        } elseif (Route::has('backend.websetting.section.cms')) {
            $settingsBackRoute = route('backend.websetting.section.cms');
        } elseif (Route::has('backend.websetting.create')) {
            $settingsBackRoute = route('backend.websetting.create');
        }
    @endphp

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                @if($settingsBackRoute)
                                    <a href="{{ $settingsBackRoute }}" class="btn btn-sm btn-danger me-2">Back</a>
                                @else
                                    <a href="javascript:history.back()" class="btn btn-sm btn-danger me-2">Back</a>
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

                            <div class="alert alert-info mt-3">
                                <div class="fw-bold mb-2">Official bKash Sandbox Test Instructions</div>
                                <p class="mb-1">Use your official sandbox credentials and set <strong>Sandbox Mode = Yes</strong>.</p>
                                <p class="mb-1"><strong>Mobile:</strong> 01770618575 or 01929918378</p>
                                <p class="mb-1"><strong>OTP:</strong> 123456</p>
                                <p class="mb-0"><strong>PIN:</strong> 12121</p>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-success btn-lg font-weight-bold" style="background-color: #16a34a; border-color: #16a34a; padding: 0.75rem 1.5rem; font-size: 1rem;">Save</button>
                                @if(optional($setting)->is_enabled && (float) optional($setting)->monthly_amount > 0)
                                    <form method="POST" action="{{ route('backend.payment.bkash.initiate') }}" style="display:inline">
                                        @csrf
                                        <input type="hidden" name="amount" value="{{ optional($setting)->monthly_amount }}">
                                        <button type="submit" class="btn btn-success">Pay Monthly Now (BDT {{ number_format(optional($setting)->monthly_amount, 2) }})</button>
                                    </form>
                                @endif
                                <button id="bkash-test" type="button" class="btn btn-outline-primary">Test connection</button>
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

    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const btn = document.getElementById('bkash-test');
            if (!btn) return;
            btn.addEventListener('click', function(){
                btn.disabled = true;
                const original = btn.textContent;
                btn.textContent = 'Testing...';
                fetch('{{ route('backend.settings.payment.bkash.test') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({})
                }).then(async res => {
                    const data = await res.json().catch(()=>({ ok: false, message: 'Invalid response' }));
                    const toast = document.createElement('div');
                    const statusText = data.ok ? 'OK' : 'Failed';
                    let message = data.message || statusText;
                    if (!data.ok && Array.isArray(data.results)) {
                        const failures = data.results.filter(r => !r.successful).length;
                        message += ` (${failures} endpoints failed)`;
                    }
                    toast.textContent = message;
                    Object.assign(toast.style, {
                        position: 'fixed', top: '1rem', right: '1rem', background: data.ok ? '#16a34a' : '#dc3545', color:'#fff', padding:'0.75rem 1rem', borderRadius:'6px', zIndex:1060, maxWidth: '420px', wordBreak: 'break-word'
                    });
                    document.body.appendChild(toast);
                    console.log('bKash test result:', data);
                    if (Array.isArray(data.results)) {
                        console.group('bKash token probe results');
                        data.results.slice(0, 10).forEach((result, index) => {
                            console.log(`#${index + 1}`, result.url, 'status=', result.status, 'success=', result.successful, 'error=', result.error || '');
                        });
                        if (data.results.length > 10) {
                            console.log(`...and ${data.results.length - 10} more results`);
                        }
                        console.groupEnd();
                    }
                    setTimeout(()=>toast.remove(), 3500);
                }).catch(err => {
                    const toast = document.createElement('div');
                    toast.textContent = 'Network error: ' + (err.message||err);
                    Object.assign(toast.style, { position: 'fixed', top: '1rem', right: '1rem', background: '#dc3545', color:'#fff', padding:'0.75rem 1rem', borderRadius:'6px', zIndex:1060, maxWidth: '420px', wordBreak: 'break-word' });
                    document.body.appendChild(toast);
                    console.error('bKash test network error:', err);
                    setTimeout(()=>toast.remove(), 4500);
                }).finally(()=>{ btn.disabled = false; btn.textContent = original; });
            });
        });
    </script>

@endsection
