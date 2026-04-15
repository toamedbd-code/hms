<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <?php
        $faviconVersion = now()->timestamp;
        $initialFavicon = route('backend.favicon.dynamic', ['v' => $faviconVersion]);
    ?>

    <title inertia><?php echo e(config('app.name', 'Laravel')); ?></title>
    <?php if($initialFavicon): ?>
        <link rel="icon" href="<?php echo e($initialFavicon); ?>" data-app-favicon="true" data-favicon-rel="icon">
        <link rel="shortcut icon" href="<?php echo e($initialFavicon); ?>" data-app-favicon="true" data-favicon-rel="shortcut icon">
    <?php endif; ?>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;700&display=swap" rel="stylesheet">
 
    <!-- Scripts -->
    <?php echo app('Tighten\Ziggy\BladeRouteGenerator')->generate(); ?>
    <?php $viteManifest = public_path('build/manifest.json'); ?>
    <?php if(file_exists($viteManifest)): ?>
        <?php echo app('Illuminate\Foundation\Vite')('resources/js/app.js'); ?>
    <?php else: ?>
        <!-- Vite build not found and dev server not running — skipping <?php echo app('Illuminate\Foundation\Vite')(); ?> to avoid 404s. -->
    <?php endif; ?>
    <?php $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
</head>

<body class="font-sans antialiased duration-1000 overflow-hidden">
    <?php $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } else { ?><script data-page="app" type="application/json"><?php echo json_encode($page); ?></script><div id="app"></div><?php } ?>
</body>

</html>
<?php /**PATH C:\laragon\www\hms\resources\views/app.blade.php ENDPATH**/ ?>