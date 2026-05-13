<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sample Barcodes</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 20px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; }
        .card { border: 1px solid #e5e7eb; padding: 12px; border-radius: 8px; }
        .code { font-size: 12px; color: #6b7280; margin-top: 6px; }
        .title { font-size: 14px; font-weight: 600; }
        .meta { font-size: 12px; color: #374151; margin-top: 4px; }
        .print-btn { padding: 6px 12px; background: #2563eb; color: #fff; border-radius: 6px; text-decoration: none; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="title">Sample Barcodes</div>
            <div class="meta">Bill No: <?php echo e($billing->bill_number ?? 'N/A'); ?></div>
        </div>
        <a href="#" class="print-btn" onclick="window.print(); return false;">Print</a>
    </div>

    <div class="grid">
        <?php $__empty_1 = true; $__currentLoopData = $barcodes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $barcode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="card">
                <div class="title"><?php echo e($barcode['name']); ?></div>
                <div class="meta"><?php echo e($barcode['category']); ?></div>
                <img src="<?php echo e($barcode['barcode']); ?>" alt="Barcode">
                <div class="code"><?php echo e($barcode['code']); ?></div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div>No pending samples for barcode.</div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php /**PATH C:\laragon\www\hms\resources\views/backend/sample_collection/barcode.blade.php ENDPATH**/ ?>