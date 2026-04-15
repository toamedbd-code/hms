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
            <div class="meta">Bill No: {{ $billing->bill_number ?? 'N/A' }}</div>
        </div>
        <a href="#" class="print-btn" onclick="window.print(); return false;">Print</a>
    </div>

    <div class="grid">
        @forelse($barcodes as $barcode)
            <div class="card">
                <div class="title">{{ $barcode['name'] }}</div>
                <div class="meta">{{ $barcode['category'] }}</div>
                <img src="{{ $barcode['barcode'] }}" alt="Barcode">
                <div class="code">{{ $barcode['code'] }}</div>
            </div>
        @empty
            <div>No pending samples for barcode.</div>
        @endforelse
    </div>
</body>
</html>
