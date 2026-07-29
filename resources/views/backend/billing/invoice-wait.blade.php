<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Generating Invoice...</title>
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;background:#f7fafc;color:#222;display:flex;align-items:center;justify-content:center;height:100vh;margin:0} .card{background:#fff;padding:24px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.08);max-width:520px;width:100%;text-align:center} .spinner{width:48px;height:48px;border-radius:50%;border:6px solid #e2e8f0;border-top-color:#2563eb;margin:12px auto;animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}</style>
</head>
<body>
  <div class="card">
    <h2>Invoice is being generated</h2>
    <div class="spinner" aria-hidden="true"></div>
    <p id="message">Please wait — this tab will redirect to your invoice when ready.</p>
    <p style="font-size:12px;color:#666;margin-top:8px">If this page doesn't redirect within 30 seconds, click <a id="fallbackLink" href="#">here</a>.</p>
  </div>

  <script>
    (function(){
      const token = "{{ $token }}" || '';
      const fallback = document.getElementById('fallbackLink');
      if (!token) {
        document.getElementById('message').textContent = 'Invalid token provided.';
        fallback.style.display = 'none';
        return;
      }

      const checkUrl = new URL("{{ route('backend.billing.invoice.wait.check') }}", window.location.origin);
      checkUrl.searchParams.set('token', token);
      fallback.href = checkUrl.href;

      let attempts = 0;
      const interval = setInterval(async () => {
        attempts++;
        try {
          const r = await fetch(checkUrl.href, { headers: { Accept: 'application/json' }, cache: 'no-store' });
          const j = await r.json();
          if (j && j.ready && j.invoice_url) {
            clearInterval(interval);
            window.location.href = j.invoice_url;
          }
        } catch (e) {
          // ignore and continue
        }

        if (attempts > 60) { // ~60 seconds
          clearInterval(interval);
          document.getElementById('message').textContent = 'Taking longer than expected. Please wait or click the link below.';
        }
      }, 1000);
    })();
  </script>
</body>
</html>
