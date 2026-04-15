const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

(async () => {
  const y4m = path.resolve(__dirname, '../tests/fake_video.y4m');
  const harPath = path.resolve(__dirname, '../tests/playwright_network.har');
  if (fs.existsSync(harPath)) fs.unlinkSync(harPath);
  const browser = await chromium.launch({ headless: false, args: [
    '--use-fake-ui-for-media-stream',
    '--use-fake-device-for-media-stream',
    `--use-file-for-fake-video-capture=${y4m}`
  ]});

  const context = await browser.newContext({
    permissions: ['camera'],
    recordHar: { path: harPath }
  });

  const page = await context.newPage();

  page.on('console', msg => console.log('PAGE console', msg.type(), msg.text()));
  page.on('pageerror', err => console.error('PAGE ERROR', err && err.stack ? err.stack : err));
  page.on('request', req => console.log('REQ', req.method(), req.url()));
  page.on('response', async res => {
    try {
      console.log('RES', res.status(), res.url());
      if (res.url().includes('/test/attendance/face/register') || res.url().includes('/models/')) {
        const text = await res.text().catch(() => '<no-text>');
        console.log('RESP BODY for', res.url(), ':', text.substring(0, 200));
      }
    } catch (e) {
      console.log('response log error', e && e.message ? e.message : e);
    }
  });

  console.log('Navigating to test registration page...');
  await page.goto('http://127.0.0.1:8000/test/attendance/face/register-page', { waitUntil: 'domcontentloaded' });

  console.log('Clicking Start Camera to load models and start video');
  await page.click('#startBtn');

  console.log('Waiting for models loaded...');
  await page.waitForFunction(() => {
    const el = document.getElementById('status');
    return el && el.innerText.includes('Models loaded.');
  }, { timeout: 60000 });
  console.log('Models loaded in page');

  // Run detection and POST descriptor to test endpoint inside the page
  try {
    const result = await page.evaluate(async () => {
      const code = 'E2E_AUTO_' + Math.floor(Math.random() * 10000);
      const video = document.getElementById('video');
      const detections = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
      if (!detections) return { ok: false, message: 'No face detected' };
      const descriptor = Array.from(detections.descriptor);
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const resp = await fetch('/test/attendance/face/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ employee_code: code, descriptor })
      });
      const data = await resp.json().catch(() => ({}));
      return { ok: resp.ok, status: resp.status, data, code };
    });
    console.log('Register result:', result);
  } catch (err) {
    console.error('Page evaluate error:', err && err.stack ? err.stack : err);
  }

  console.log('Closing browser and saving HAR at', harPath);
  await context.close();
  await browser.close();
  console.log('Done');
})();
