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

                        @php
                            $subscription = \App\Models\Subscription::ensureExists();
                            $subscriptionActive = $subscription && $subscription->isActive();
                            $isSandbox = !filter_var($bkashEnv['use_production'], FILTER_VALIDATE_BOOLEAN);
                            $isEnabled = $setting ? (bool) $setting->is_enabled : true;
                        @endphp

                        <div class="alert alert-secondary d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <div class="fw-bold">Subscription Control</div>
                                <div class="small">Current status: <strong>{{ $subscriptionActive ? 'ACTIVE' : 'INACTIVE' }}</strong></div>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <form method="POST" action="{{ route('backend.settings.payment.bkash.subscription.toggle') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="mode" value="off">
                                    <button type="submit" class="btn btn-outline-danger">Inactive</button>
                                </form>
                                <form method="POST" action="{{ route('backend.settings.payment.bkash.subscription.toggle') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="mode" value="on">
                                    <label class="small text-muted">Active days
                                        <input type="number" name="duration_days" value="{{ old('duration_days', optional($subscription)->duration_days ?? 30) }}" min="1" max="3650" required class="form-control form-control-sm" style="width: 90px;">
                                    </label>
                                    <button type="submit" class="btn btn-success">Active</button>
                                </form>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('backend.settings.payment.bkash.store') }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Merchant Account</label>
                                <input name="merchant_account" class="form-control" value="{{ old('merchant_account', $bkashEnv['merchant_account']) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">App Key</label>
                                <input name="app_key" class="form-control" value="{{ old('app_key', $bkashEnv['app_key']) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">App Secret</label>
                                <input name="app_secret" class="form-control" value="{{ old('app_secret', $bkashEnv['app_secret']) }}">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Merchant Username</label>
                                    <input name="username" class="form-control" value="{{ old('username', $bkashEnv['username']) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Merchant Number</label>
                                    <input name="merchant_number" class="form-control" value="{{ old('merchant_number', $bkashEnv['merchant_number']) }}">
                                    <small class="form-text text-muted">Leave blank to hide merchant number on checkout.</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Merchant Password</label>
                                <input name="password" type="text" class="form-control" value="{{ old('password', $bkashEnv['password']) }}">
                            </div>

                            <hr>
                            <h6 class="mb-3">bKash .env Connection Settings</h6>
                            <div class="mb-3">
                                <label class="form-label">Sandbox Base URL</label>
                                <input name="sandbox_base_url" class="form-control" value="{{ old('sandbox_base_url', $bkashEnv['sandbox_base_url']) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Production Base URL</label>
                                <input name="production_base_url" class="form-control" value="{{ old('production_base_url', $bkashEnv['production_base_url']) }}">
                            </div>
                            <div class="row">
                                @foreach ([
                                    'token_endpoint' => 'Token Endpoint',
                                    'create_payment_endpoint' => 'Create Payment Endpoint',
                                    'execute_payment_endpoint' => 'Execute Payment Endpoint',
                                    'query_payment_endpoint' => 'Query Payment Endpoint',
                                ] as $field => $label)
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">{{ $label }}</label>
                                        <input name="{{ $field }}" class="form-control" value="{{ old($field, $bkashEnv[$field]) }}">
                                    </div>
                                @endforeach
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Callback URL</label>
                                <input name="callback_url" class="form-control" value="{{ old('callback_url', $bkashEnv['callback_url']) }}">
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">HTTP Timeout (seconds)</label>
                                    <input name="http_timeout" type="number" min="1" max="300" class="form-control" value="{{ old('http_timeout', $bkashEnv['http_timeout']) }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Verify SSL</label>
                                    <select name="http_verify_ssl" class="form-select">
                                        <option value="1" {{ filter_var(old('http_verify_ssl', $bkashEnv['http_verify_ssl']), FILTER_VALIDATE_BOOLEAN) ? 'selected' : '' }}>Yes</option>
                                        <option value="0" {{ !filter_var(old('http_verify_ssl', $bkashEnv['http_verify_ssl']), FILTER_VALIDATE_BOOLEAN) ? 'selected' : '' }}>No</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Monthly Amount (BDT)</label>
                                    <input name="monthly_amount" type="number" step="0.01" class="form-control" value="{{ old('monthly_amount', $monthlyAmount) }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Sandbox Mode</label>
                                    <select name="use_production" class="form-select">
                                        <option value="0" {{ $isSandbox ? 'selected' : '' }}>Yes</option>
                                        <option value="1" {{ !$isSandbox ? 'selected' : '' }}>No</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Enable bKash Payments</label>
                                    <select name="is_enabled" class="form-select">
                                        <option value="1" {{ $isEnabled ? 'selected' : '' }}>Enabled</option>
                                        <option value="0" {{ !$isEnabled ? 'selected' : '' }}>Disabled</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-success btn-lg font-weight-bold" style="background-color: #16a34a; border-color: #16a34a; padding: 0.75rem 1.5rem; font-size: 1rem;">Save</button>
                            </div>
                        </form>

                        <div class="mt-3 d-flex gap-2">
                            @if(optional($setting)->is_enabled && (float) optional($setting)->monthly_amount > 0)
                                <form method="POST" action="{{ route('backend.payment.bkash.initiate') }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="amount" value="{{ optional($setting)->monthly_amount }}">
                                    @php
                                        $monthlyBaseAmount = (float) optional($setting)->monthly_amount;
                                        $gatewayFeePercent = (float) config('subscription.gateway_fee_percent', 1.5);
                                        $monthlyGatewayAmount = round($monthlyBaseAmount * (1 + ($gatewayFeePercent / 100)), 2);
                                    @endphp
                                    <button type="submit" class="btn btn-success">Pay Monthly Now (BDT {{ number_format($monthlyGatewayAmount, 2) }})</button>
                                </form>
                            @endif

                            <button id="bkash-test" type="button" class="btn btn-outline-primary">Test connection</button>
                        </div>
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
