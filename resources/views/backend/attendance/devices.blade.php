@extends('layouts.backend')

@section('content')
<div class="container">
    <h1>Attendance Devices</h1>

    <!-- Setup Guide -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <button class="btn btn-link" data-toggle="collapse" data-target="#setupGuide" aria-expanded="false">
                    Device Setup Guide
                </button>
            </h5>
        </div>
        <div id="setupGuide" class="collapse">
            <div class="card-body">
                <h6>Fingerprint Devices (e.g., ZKTeco)</h6>
                <ol>
                    <li>Connect the fingerprint device to your network.</li>
                    <li>Note the device's IP address or serial number.</li>
                    <li>Configure the device to send webhooks to: <code>{{ url('/api/attendance/device/webhook') }}</code></li>
                    <li>Set the payload format as JSON: <code>{"device_id": "IP/serial", "employee_code": "EMP001", "type": "in/out", "timestamp": "2026-03-14T09:00:00"}</code></li>
                    <li>Optional: Set a shared secret for HMAC validation.</li>
                </ol>

                <h6>Face Recognition Devices</h6>
                <ol>
                    <li>Connect the face device to your network.</li>
                    <li>Note the device's identifier.</li>
                    <li>Configure webhook URL and payload similar to fingerprint.</li>
                    <li>Ensure the device supports face detection and sends employee_code.</li>
                    <li>For uFace ADMS/iClock mode, set server URL to: <code>{{ url('/api/attendance/device/adms') }}</code></li>
                </ol>

                <h6>Laptop Webcam Attendance (Built-in Camera)</h6>
                <ol>
                    <li>Go to Face Attendance page: <code>{{ route('backend.attendance.face') }}</code></li>
                    <li>Register employee face first from: <code>{{ route('backend.attendance.face.register') }}</code></li>
                    <li>Allow camera permission in browser.</li>
                    <li>Keep <code>public/models</code> available for face-api models.</li>
                    <li>Use this mode for laptop/self-service attendance kiosk.</li>
                </ol>

                <h6>Laptop Fingerprint (Windows Hello) - Important</h6>
                <ol>
                    <li>Browser fingerprint works through WebAuthn/Windows Hello for login verification.</li>
                    <li>It does not provide employee fingerprint template matching like ZKTeco devices.</li>
                    <li>For true biometric staff attendance matching, use a supported attendance fingerprint device and webhook integration.</li>
                </ol>

                <h6>Recommended Settings</h6>
                <ol>
                    <li>Open General Settings and set Attendance Device Type = <strong>Both</strong>.</li>
                    <li>Enable modules: Face Attendance, Fingerprint, Leave, Duty Roster, Salary Sheet.</li>
                    <li>Set Sync Mode to Realtime for instant attendance impact on reports.</li>
                </ol>

                <h6>Testing</h6>
                <p>Use tools like Postman to simulate webhook calls for testing.</p>

                <h6>Final Onboarding Checklist (Before Real Device)</h6>
                <ol>
                    <li>Register one device below with correct Identifier and Secret.</li>
                    <li>Open Device Settings and confirm webhook signature algorithm/header values.</li>
                    <li>Run signed webhook smoke test command from project root:</li>
                </ol>
                <pre><code>php scripts/test_signed_webhook.php http://hms.test YOUR_DEVICE_IDENTIFIER YOUR_DEVICE_SECRET EMP001 in</code></pre>
                <p>If response shows <code>{"ok":true}</code>, then webhook security and payload mapping are ready for ZKTeco device add.</p>
            </div>
        </div>
    </div>

    <form id="device-form">
        <div class="form-group"><label>Name</label><input name="name" class="form-control" /></div>
        <div class="form-group"><label>Identifier (IP or serial)</label><input name="identifier" class="form-control" required /></div>
        <div class="form-group"><label>Type</label><select name="type" class="form-control"><option value="fingerprint">Fingerprint</option><option value="face">Face</option></select></div>
        <div class="form-group"><label>Secret (optional)</label><input name="secret" class="form-control" /></div>
        <button type="submit" class="btn btn-primary">Register Device</button>
    </form>

    <hr />
    <h2>Registered Devices</h2>
    <table id="devices-table" class="table table-bordered">
        <thead><tr><th>ID</th><th>Name</th><th>Identifier</th><th>Type</th><th>Secret</th><th>Status</th><th>Action</th></tr></thead>
        <tbody></tbody>
    </table>
</div>

<div class="container mb-3">
    <a href="{{ route('backend.attendance.face') }}" class="btn btn-success btn-sm mr-2">Open Face Attendance</a>
    <a href="{{ route('backend.attendance.face.register') }}" class="btn btn-info btn-sm mr-2">Open Face Register</a>
    <a href="{{ route('backend.attendance.face.encodings') }}" class="btn btn-secondary btn-sm mr-2">Face Encoding List</a>
    <a href="{{ route('backend.websetting.create') }}" class="btn btn-primary btn-sm">Open Device Settings</a>
</div>

<script>
async function loadDevices(){
  const listRes = await fetch('/api/attendance/devices');
  const items = await listRes.json().then(r=>r.data || r).catch(()=>[]);
  const tbody = document.querySelector('#devices-table tbody'); tbody.innerHTML='';
  items.forEach(d=>{
    const tr = document.createElement('tr');
    tr.innerHTML=`<td>${d.id}</td><td>${d.name||''}</td><td>${d.identifier||''}</td><td>${d.type||''}</td><td>${d.secret? '***':''}</td><td>${d.status||''}</td><td><button data-id="${d.id}" class="deact">Deactivate</button> <button data-id="${d.id}" class="delperm">Delete Permanently</button></td>`;
    tbody.appendChild(tr);
  });
  document.querySelectorAll('.deact').forEach(btn=>btn.addEventListener('click', async e=>{
    const id = btn.getAttribute('data-id');
    if(!confirm('Deactivate device?')) return;
    await fetch('/api/attendance/device/' + id, {method:'DELETE', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}});
    loadDevices();
  }));

  document.querySelectorAll('.delperm').forEach(btn=>btn.addEventListener('click', async e=>{
    const id = btn.getAttribute('data-id');
    if(!confirm('Permanently delete this device? This cannot be undone.')) return;
    await fetch('/api/attendance/device/' + id + '?force=1', {method:'DELETE', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}});
    loadDevices();
  }));
}

    document.getElementById('device-form').addEventListener('submit', async e=>{
  e.preventDefault();
  const form = e.target; const data = Object.fromEntries(new FormData(form).entries());
  await fetch('/api/attendance/device/register', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify(data)});
  form.reset(); loadDevices();
});

    // Add a sync button to trigger sync from this UI
    const syncBtn = document.createElement('button');
    syncBtn.type = 'button';
    syncBtn.textContent = 'Sync Device Attendance';
    syncBtn.addEventListener('click', async ()=>{
      if(!confirm('Sync attendance from all active devices now?')) return;
      await fetch('{{ route("backend.attendance.sync") }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}});
      alert('Sync request sent. Check notifications for result.');
    });
    document.querySelector('.container').insertBefore(syncBtn, document.querySelector('.container').firstChild);

window.addEventListener('DOMContentLoaded', loadDevices);
</script>

@endsection
