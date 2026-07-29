<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Preparing Invoice...</title>
  <style>
    body{font-family:Helvetica,Arial,sans-serif;background:#f5f7fa;margin:0;padding:0}
    .center{max-width:800px;margin:60px auto;background:white;padding:24px;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,0.08)}
    .loader{height:6px;background:#eee;overflow:hidden;margin-top:12px}
    .bar{width:0;height:6px;background:#16a34a;animation:progress 3s linear infinite}
    @keyframes progress{0%{width:0}50%{width:60%}100%{width:100%}}
    .small{color:#666;font-size:13px}
    .btn{display:inline-block;padding:8px 12px;background:#0ea5a3;color:white;border-radius:4px;text-decoration:none}
  </style>
</head>
<body>
  <div class="center">
    <h2>Preparing invoice...</h2>
    <div class="small">We are preparing the invoice. This tab will load the invoice automatically when ready.</div>
    <div class="loader"><div class="bar"></div></div>
    <div style="margin-top:12px"><a class="btn" id="forceOpen">Open Now</a></div>
  </div>

  <script>
    const checkUrl = "{{ $check_route }}";
    const module = "{{ $module }}";

    async function check() {
      try {
        const res = await fetch(checkUrl, { credentials: 'same-origin' });
        if (res.ok) {
          const js = await res.json();
          if (js.ready && js.id) {
            // Redirect to the main download URL so browser displays cached PDF inline
            const downloadUrl = new URL("{{ route('backend.download.invoice') }}", window.location.origin);
            downloadUrl.searchParams.set('id', js.id);
            downloadUrl.searchParams.set('module', module);
            window.location.href = downloadUrl.toString();
            return;
          }
        }
      } catch (e) {
        // ignore
      }
      setTimeout(check, 800);
    }

    document.getElementById('forceOpen').addEventListener('click', function(){ check(); });
    check();
  </script>
</body>
</html>
