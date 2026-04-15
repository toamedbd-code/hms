@extends('layouts.backend')

@section('content')
<div class="container">
    <h2>Register Face Encoding</h2>

    <p>Before using this page, download face-api.js models and place them in <strong>/public/models</strong> (see README notes below).</p>

    <div style="max-width:720px">
        <div style="margin-bottom:10px">
            <a href="{{ route('backend.attendance.face.encodings') }}" class="btn btn-outline-primary btn-sm">View Face Encoding List</a>
            <a href="{{ route('backend.attendance.face') }}" class="btn btn-outline-success btn-sm">Open Face Attendance</a>
        </div>

        <label>Employee Code</label>
        <input id="employee_code" class="form-control" placeholder="Enter employee code">

        <div style="margin-top:10px">
            <video id="video" width="640" height="480" autoplay muted style="border:1px solid #ccc"></video>
        </div>

        <div style="margin-top:10px">
            <button id="startBtn" class="btn btn-primary">Start Camera</button>
            <button id="captureBtn" class="btn btn-success" disabled>Capture & Register</button>
            <button id="stopBtn" class="btn btn-secondary" disabled>Stop</button>
        </div>

        <div id="status" style="margin-top:10px"></div>
    </div>

    <hr>
    <h4>Instructions</h4>
    <ul>
        <li>Download models from the face-api.js repo and put them in <code>public/models</code>.</li>
        <li>Open this page, enter `employee_code`, allow camera, then press <strong>Capture & Register</strong>.</li>
    </ul>
</div>

<meta name="csrf-token" content="{{ csrf_token() }}">

<script src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
    const startBtn = document.getElementById('startBtn');
    const stopBtn = document.getElementById('stopBtn');
    const captureBtn = document.getElementById('captureBtn');
    const video = document.getElementById('video');
    const statusEl = document.getElementById('status');
    let stream = null;
    let modelsLoaded = false;
    const isTestMode = window.location.pathname.startsWith('/test/attendance/face/');
    const rawRegisterEndpoint = isTestMode
        ? '/test/attendance/face/register'
        : '{{ route("backend.attendance.face.register.store") }}';

    function toSameOriginPath(endpoint) {
        if (!endpoint) return endpoint;
        if (!/^https?:\/\//i.test(endpoint)) {
            return endpoint;
        }

        try {
            const parsed = new URL(endpoint, window.location.origin);
            return parsed.pathname + parsed.search;
        } catch (_) {
            return endpoint;
        }
    }

    const registerEndpoint = toSameOriginPath(rawRegisterEndpoint);

    function isLocalHostName(hostname) {
        return hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
    }

    function ensureSecureContextForCamera() {
        if (window.isSecureContext || isLocalHostName(window.location.hostname)) {
            return true;
        }

        const path = window.location.pathname;
        const publicMarker = '/public';
        const publicIdx = path.indexOf('/public/');
        const hasTrailingPublic = path.endsWith('/public');

        let appBase = '/hms/public';
        let routePath = path;

        if (publicIdx >= 0) {
            appBase = path.substring(0, publicIdx + publicMarker.length);
            routePath = path.substring(publicIdx + publicMarker.length) || '/';
        } else if (hasTrailingPublic) {
            appBase = path;
            routePath = '/';
        }

        if (!routePath.startsWith('/')) {
            routePath = '/' + routePath;
        }

        const secureUrl = 'https://' + window.location.host + window.location.pathname + window.location.search;
        const localhostUrl = 'http://localhost' + appBase + routePath + window.location.search;
        statusEl.innerHTML = 'Camera requires HTTPS or localhost. <a href="' + secureUrl + '">Open HTTPS page</a> or <a href="' + localhostUrl + '">Open localhost page</a> then try again.';
        return false;
    }

    function modelBaseCandidates() {
        const origin = window.location.origin;
        const out = [origin + '/models'];

        const publicIdx = window.location.pathname.indexOf('/public/');
        if (publicIdx >= 0) {
            const appBase = window.location.pathname.substring(0, publicIdx + '/public'.length);
            out.unshift(origin + appBase + '/models');
        }

        return [...new Set(out)];
    }

    async function tryLoadModelsFrom(baseUrl) {
        await faceapi.nets.tinyFaceDetector.loadFromUri(baseUrl);
        await faceapi.nets.faceRecognitionNet.loadFromUri(baseUrl);
        await faceapi.nets.faceLandmark68Net.loadFromUri(baseUrl);
    }

    async function loadModels() {
        if (modelsLoaded) return true;
        if (typeof faceapi === 'undefined') {
            statusEl.innerText = 'face-api.js load হয়নি। ইন্টারনেট বা CDN access চেক করুন।';
            return false;
        }

        statusEl.innerText = 'Loading models...';
        const bases = modelBaseCandidates();

        for (const base of bases) {
            try {
                await tryLoadModelsFrom(base);
                modelsLoaded = true;
                statusEl.innerText = 'Models loaded from: ' + base;
                captureBtn.disabled = false;
                return true;
            } catch (_) {
                // Try next candidate.
            }
        }

        statusEl.innerText = 'Model load failed. Checked: ' + bases.join(', ');
        return false;
    }

    async function startCamera() {
        if (!ensureSecureContextForCamera()) {
            return;
        }

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                // try legacy prefixes
                const legacyGetUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia;
                if (!legacyGetUserMedia) {
                    statusEl.innerText = 'Camera not available: your browser or context does not allow camera access. Use Chrome/Edge on HTTPS or localhost.';
                    return;
                }

                stream = await new Promise((resolve, reject) => {
                    legacyGetUserMedia.call(navigator, { video: true, audio: false }, resolve, reject);
                });
            } else {
                stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            }

            video.srcObject = stream;
            startBtn.disabled = true;
            stopBtn.disabled = false;
            statusEl.innerText = 'Camera started.';
        } catch (err) {
            // More helpful error text when running insecurely or without permissions
            if (err && err.name === 'NotAllowedError') {
                statusEl.innerText = 'Camera permission denied. Allow camera access and try again.';
            } else if (err && err.name === 'NotFoundError') {
                statusEl.innerText = 'No camera found on this device.';
            } else {
                statusEl.innerText = 'Camera error: ' + (err && err.message ? err.message : String(err));
            }
        }
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
        }
        video.srcObject = null;
        startBtn.disabled = false;
        stopBtn.disabled = true;
    }

    async function captureAndRegister() {
        const code = document.getElementById('employee_code').value.trim();
        if (!code) { statusEl.innerText = 'Enter employee code.'; return; }
        if (!modelsLoaded) { statusEl.innerText = 'Model load হয়নি। আগে Start Camera দিন।'; return; }
        if (!stream || !video.srcObject) { statusEl.innerText = 'Camera started না। আগে Start Camera দিন।'; return; }

        statusEl.innerText = 'Detecting face and computing descriptor...';
        let detections = null;
        try {
            detections = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
        } catch (err) {
            statusEl.innerText = 'Face detection failed: ' + (err && err.message ? err.message : String(err));
            return;
        }
        if (!detections) { statusEl.innerText = 'No face detected. Try again.'; return; }

        const descriptor = Array.from(detections.descriptor);

        statusEl.innerText = 'Registering...';
        try {
            const resp = await fetch(registerEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin',
                body: JSON.stringify({ employee_code: code, descriptor })
            });

            const raw = await resp.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (_) {
                data = { message: raw };
            }

            if (resp.ok) {
                statusEl.innerText = 'Registered successfully for ' + code;
            } else {
                statusEl.innerText = 'Failed (' + resp.status + '): ' + (data.message || JSON.stringify(data));
            }
        } catch (err) {
            statusEl.innerText = 'Network error: ' + err.message;
        }
    }

    startBtn.addEventListener('click', async () => {
        const ok = await loadModels();
        if (!ok) return;
        await startCamera();
    });
    stopBtn.addEventListener('click', stopCamera);
    captureBtn.addEventListener('click', captureAndRegister);
</script>

@endsection
