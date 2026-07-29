<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $faviconVersion = now()->timestamp;
        $initialFavicon = route('backend.favicon.dynamic', ['v' => $faviconVersion]);
    @endphp

    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    <!-- Favicon declarations: use the static site favicon on initial render for reliable tab icons -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;700&display=swap" rel="stylesheet">
 
    <!-- Scripts -->
    @routes
    <script>
        (function () {
            try {
                var __lp = localStorage.getItem('__last_branding_payload');
                if (__lp) {
                    window.__last_branding_payload = JSON.parse(__lp);
                }
            } catch (e) {
                // ignore
            }
        })();
    </script>
    @php $viteManifest = public_path('build/manifest.json'); @endphp
    @if (file_exists($viteManifest))
        @vite('resources/js/app.js')
    @else
        <!-- Vite build not found and dev server not running — skipping @vite to avoid 404s. -->
    @endif
    @inertiaHead
</head>

<body class="font-sans antialiased duration-1000">
    @inertia
</body>

</html>
