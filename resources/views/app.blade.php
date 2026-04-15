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
    @if($initialFavicon)
        <link rel="icon" href="{{ $initialFavicon }}" data-app-favicon="true" data-favicon-rel="icon">
        <link rel="shortcut icon" href="{{ $initialFavicon }}" data-app-favicon="true" data-favicon-rel="shortcut icon">
    @endif

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;700&display=swap" rel="stylesheet">
 
    <!-- Scripts -->
    @routes
    @php $viteManifest = public_path('build/manifest.json'); @endphp
    @if (file_exists($viteManifest))
        @vite('resources/js/app.js')
    @else
        <!-- Vite build not found and dev server not running — skipping @vite to avoid 404s. -->
    @endif
    @inertiaHead
</head>

<body class="font-sans antialiased duration-1000 overflow-hidden">
    @inertia
</body>

</html>
