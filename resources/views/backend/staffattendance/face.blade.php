@extends('layouts.backend')

@section('content')
<div class="container">
    <h2>Webcam Face-Detect Attendance</h2>

    <div style="max-width:720px">
        <div style="margin-top:10px">
            <video id="video" width="640" height="480" autoplay muted style="border:1px solid #ccc"></video>
        </div>

        <div style="margin-top:10px">
            <button id="startBtn" class="btn btn-primary">Start Camera</button>
            <button id="stopBtn" class="btn btn-secondary" disabled>Stop</button>
        </div>

        <div id="status" style="margin-top:10px"></div>

        <div id="registerForm" style="display:none; margin-top:10px">
            <h4>Face not recognized. Register new face:</h4>
            <label>Employee Code</label>
            <input id="regEmployeeCode" class="form-control" placeholder="Enter employee code">
            <button id="registerBtn" class="btn btn-success" style="margin-top:10px">Register Face</button>
            <button id="cancelBtn" class="btn btn-secondary" style="margin-top:10px">Cancel</button>
        </div>
    </div>

</div>

<meta name="csrf-token" content="{{ csrf_token() }}">

<script src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
    const startBtn = document.getElementById('startBtn');
    const stopBtn = document.getElementById('stopBtn');
    const video = document.getElementById('video');
    const statusEl = document.getElementById('status');
    let stream = null;
    let detectInterval = null;
    let isMarking = false;
    const isTestMode = window.location.pathname.startsWith('/test/attendance/face/');
    const rawMarkEndpoint = isTestMode
        ? '/test/attendance/face/mark'
        : '{{ route("backend.attendance.face.mark") }}';
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

    const markEndpoint = toSameOriginPath(rawMarkEndpoint);
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
        if (typeof faceapi === 'undefined') {
            statusEl.innerText = 'face-api.js not loaded. Check internet connection.';
            return;
        }
        statusEl.innerText = 'Loading face recognition models...';
        const bases = modelBaseCandidates();
        for (const base of bases) {
            try {
                await tryLoadModelsFrom(base);
                statusEl.innerText = 'Models loaded from: ' + base + '. Click Start Camera.';
                return;
            } catch (_) {
                // Try next candidate.
            }
        }

        statusEl.innerText = 'Failed to load models. Checked: ' + bases.join(', ');
    }

    async function startCamera() {
        if (!ensureSecureContextForCamera()) {
            return;
        }

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
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
            statusEl.innerText = 'Camera started. Detecting faces...';

            detectInterval = setInterval(async () => {
                if (isMarking) {
                    return;
                }

                try {
                    const detections = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
                    if (detections) {
                        isMarking = true;
                        statusEl.innerText = 'Face detected. Recognizing...';
                        await markAttendance(detections.descriptor);
                        stopCamera();
                    } else {
                        statusEl.innerText = 'No face detected yet.';
                    }
                } catch (err) {
                    console.error('Detection error', err);
                    statusEl.innerText = 'Detection error: ' + err.message;
                    clearInterval(detectInterval);
                }
            }, 1000);
        } catch (err) {
            console.error(err);
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
        if (detectInterval) clearInterval(detectInterval);
        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
        }
        video.srcObject = null;
        startBtn.disabled = false;
        stopBtn.disabled = true;
    }

    async function markAttendance(descriptor) {
        statusEl.innerText = 'Recognizing face...';

        try {
            const resp = await fetch(markEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin',
                body: JSON.stringify({ descriptor: descriptor })
            });

            const raw = await resp.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (_) {
                data = { message: raw };
            }

            if (resp.ok) {
                statusEl.innerText = 'Attendance marked successfully.';
                stopCamera();
            } else {
                if (data.message && data.message.includes('No match found')) {
                    statusEl.innerText = 'Face not recognized.';
                    showRegisterForm(descriptor);
                } else {
                    const isHtml = typeof raw === 'string' && raw.trim().startsWith('<!DOCTYPE');
                    if (resp.status === 401 || resp.status === 419 || isHtml) {
                        statusEl.innerText = 'Session expired or unauthorized. Please reload and login again.';
                    } else {
                        statusEl.innerText = 'Failed to mark attendance: ' + (data.message || JSON.stringify(data));
                    }
                }
            }
        } catch (err) {
            console.error(err);
            statusEl.innerText = 'Network error: ' + err.message;
        } finally {
            isMarking = false;
        }
    }

    function showRegisterForm(descriptor) {
        document.getElementById('registerForm').style.display = 'block';
        document.getElementById('registerBtn').onclick = () => registerFace(descriptor);
        document.getElementById('cancelBtn').onclick = () => {
            document.getElementById('registerForm').style.display = 'none';
            statusEl.innerText = 'Recognition cancelled. Try again.';
        };
    }

    async function registerFace(descriptor) {
        const code = document.getElementById('regEmployeeCode').value.trim();
        if (!code) {
            statusEl.innerText = 'Enter employee code to register.';
            return;
        }

        statusEl.innerText = 'Registering face...';

        try {
            const resp = await fetch(registerEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin',
                body: JSON.stringify({ employee_code: code, descriptor: descriptor })
            });

            const raw = await resp.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (_) {
                data = { message: raw };
            }

            if (resp.ok) {
                statusEl.innerText = 'Face registered successfully. Marking attendance...';
                document.getElementById('registerForm').style.display = 'none';
                // Now mark attendance
                await markAttendance(descriptor);
            } else {
                const isHtml = typeof raw === 'string' && raw.trim().startsWith('<!DOCTYPE');
                if (resp.status === 401 || resp.status === 419 || isHtml) {
                    statusEl.innerText = 'Session expired or unauthorized. Please reload and login again.';
                } else {
                    statusEl.innerText = 'Failed to register: ' + (data.message || JSON.stringify(data));
                }
            }
        } catch (err) {
            console.error(err);
            statusEl.innerText = 'Network error: ' + err.message;
        }
    }

    startBtn.addEventListener('click', startCamera);
    stopBtn.addEventListener('click', stopCamera);

    // Load models on page load
    loadModels();
</script>

@endsection
