@extends('layouts.backend')

@section('content')
<div class="container">
    <h2>Attendance Kiosk (Face)</h2>

    <div class="alert alert-info">
        Enter Kiosk PIN, then click <strong>Start Camera</strong>. One scan = IN, next scan = OUT.
    </div>

    <div style="max-width:720px">
        <label>Kiosk PIN</label>
        <input id="kioskPin" class="form-control" placeholder="Enter PIN">

        <div style="margin-top:10px">
            <video id="video" width="640" height="480" autoplay muted style="border:1px solid #ccc"></video>
        </div>

        <div style="margin-top:10px">
            <button id="startBtn" class="btn btn-primary">Start Camera</button>
            <button id="stopBtn" class="btn btn-secondary" disabled>Stop</button>
        </div>

        <div id="status" style="margin-top:10px"></div>
    </div>
</div>

<meta name="csrf-token" content="{{ csrf_token() }}">

<script src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
    const startBtn = document.getElementById('startBtn');
    const stopBtn = document.getElementById('stopBtn');
    const video = document.getElementById('video');
    const statusEl = document.getElementById('status');
    const kioskPinEl = document.getElementById('kioskPin');

    let stream = null;
    let detectInterval = null;
    let isMarking = false;

    async function loadModels() {
        if (typeof faceapi === 'undefined') {
            statusEl.innerText = 'face-api.js not loaded. Check internet.';
            return;
        }
        statusEl.innerText = 'Loading face models...';
        await faceapi.nets.tinyFaceDetector.loadFromUri('/models');
        await faceapi.nets.faceRecognitionNet.loadFromUri('/models');
        await faceapi.nets.faceLandmark68Net.loadFromUri('/models');
        statusEl.innerText = 'Models loaded. Enter PIN and Start Camera.';
    }

    async function startCamera() {
        const pin = kioskPinEl.value.trim();
        if (!pin) {
            statusEl.innerText = 'Enter Kiosk PIN.';
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            video.srcObject = stream;

            startBtn.disabled = true;
            stopBtn.disabled = false;
            statusEl.innerText = 'Camera started. Detecting face...';

            detectInterval = setInterval(async () => {
                if (isMarking) {
                    return;
                }

                try {
                    const detections = await faceapi
                        .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                        .withFaceLandmarks()
                        .withFaceDescriptor();

                    if (!detections) {
                        statusEl.innerText = 'No face detected yet.';
                        return;
                    }

                    isMarking = true;
                    statusEl.innerText = 'Face detected. Marking attendance...';

                    const resp = await fetch('/kiosk/attendance/face/mark', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'X-Kiosk-Pin': pin,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ descriptor: Array.from(detections.descriptor) })
                    });

                    const raw = await resp.text();
                    let data = {};
                    try {
                        data = raw ? JSON.parse(raw) : {};
                    } catch (_) {
                        data = { message: raw };
                    }

                    if (resp.ok) {
                        statusEl.innerText = `OK: ${data.employee_code} marked as ${data.marked_as.toUpperCase()}`;
                        stopCamera();
                    } else {
                        const isHtml = typeof raw === 'string' && raw.trim().startsWith('<!DOCTYPE');
                        if (resp.status === 401 || resp.status === 419 || isHtml) {
                            statusEl.innerText = 'Session/PIN সমস্যা বা authorization issue. পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
                        } else {
                            statusEl.innerText = 'Failed: ' + (data.message || JSON.stringify(data));
                        }
                    }
                } catch (err) {
                    statusEl.innerText = 'Error: ' + err.message;
                } finally {
                    isMarking = false;
                }
            }, 1200);

        } catch (err) {
            statusEl.innerText = 'Camera error: ' + err.message;
        }
    }

    function stopCamera() {
        if (detectInterval) clearInterval(detectInterval);
        detectInterval = null;

        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
        }
        video.srcObject = null;

        startBtn.disabled = false;
        stopBtn.disabled = true;
    }

    startBtn.addEventListener('click', startCamera);
    stopBtn.addEventListener('click', stopCamera);

    loadModels();
</script>
@endsection
