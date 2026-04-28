<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Debug WebSetting</title>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial;padding:20px;background:#f7fafc;color:#0f172a}pre{background:#ffffff;padding:12px;border:1px solid #e2e8f0;border-radius:6px;overflow:auto}</style>
</head>
<body>
    <h1>Debug: WebSetting</h1>
    <h2>get_cached_web_setting()</h2>
    <pre>{{ json_encode(optional($webSetting)->toArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>

    <h2>session('companyInfo')</h2>
    <pre>{{ json_encode($companyInfo, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>

    <p>Routes (dev):
        <ul>
            <li><a href="/dev/debug-websetting">JSON output</a></li>
            <li><a href="/dev/debug-websetting/view">Blade view (this page)</a></li>
        </ul>
    </p>
</body>
</html>
