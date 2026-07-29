<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Invoice - {{ $bill_number ?? 'Invoice' }}</title>
  <style>
    /* Compact fast invoice template: minimal padding and smaller fonts for instant single-page */
    html,body{margin:0;padding:0;font-family:Helvetica, Arial, sans-serif;color:#111}
    .sheet{width:210mm;min-height:297mm;padding:6mm;margin:auto;box-sizing:border-box}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
    .header .title{font-size:14px;font-weight:700}
    .meta{margin-bottom:6px;font-size:12px}
    table{width:100%;border-collapse:collapse;font-size:12px}
    th,td{padding:4px;border:1px solid #ddd}
    th{background:#f4f4f4;text-align:left}
    .right{text-align:right}
    .totals{margin-top:6px;width:100%}
    .totals td{padding:4px}
    .print-actions{position:fixed;right:8px;top:8px}

    @media screen{
      body{background:#f5f7fa}
      .sheet{box-shadow:0 2px 8px rgba(0,0,0,0.08);margin:12px auto}
    }

    @media print{
      body,html{background:white}
      .print-actions{display:none}
      .sheet{box-shadow:none;margin:0;padding:4mm}
      @page{size:A4;margin:5mm}
    }
  </style>
</head>
<body>
  <div class="print-actions">
    <button onclick="printAndClose()">Print</button>
    <button onclick="closeInvoiceTab()">Cancel</button>
  </div>

  <div class="sheet" role="document">
    <div class="header">
      <div>
        <div class="title">{{ $bill_number ?? 'Invoice' }}</div>
        <div style="font-size:13px">{{ $patient_name ?? '' }} - {{ $contact_no ?? '' }}</div>
        <div style="font-size:12px;color:#666">{{ $invoiceDateTime ?? '' }}</div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:700">{{ $prepared_by ?? '' }}</div>
        <div style="font-size:12px;color:#666">{{ $amount_in_words ?? '' }}</div>
      </div>
    </div>

    <div class="meta">
      <strong>Remarks:</strong> {{ $remarks ?? '' }}
    </div>

    <table>
      <thead>
        <tr>
          <th style="width:6%">#</th>
          <th>Item</th>
          <th style="width:12%">Qty</th>
          <th style="width:22%" class="right">Amount</th>
        </tr>
      </thead>
      <tbody>
        @forelse($bill_items as $i => $item)
          <tr>
            <td class="right">{{ $i+1 }}</td>
            <td>{{ is_object($item) ? ($item->item_name ?? ($item['item_name'] ?? '')) : ($item['item_name'] ?? '') }}</td>
            <td class="right">{{ is_object($item) ? ($item->quantity ?? ($item['quantity'] ?? 1)) : ($item['quantity'] ?? 1) }}</td>
            <td class="right">{{ number_format(is_object($item) ? ($item->net_amount ?? ($item['total_amount'] ?? 0)) : ($item['total_amount'] ?? 0), 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="4">No items</td></tr>
        @endforelse
      </tbody>
    </table>

    <table class="totals">
      <tr>
        <td style="width:70%">&nbsp;</td>
        <td style="width:30%">
          <table style="width:100%">
            @php
              $baseTotal = round((float) ($base_total ?? $total_amount ?? 0), 2);
              $vatPercentLocal = round((float) ($vat_percentage ?? 0), 2);
              $vatComputed = $vatPercentLocal > 0 ? round(($baseTotal * $vatPercentLocal) / 100, 2) : 0.00;
              $extraDiscount = round((float) ($extra_flat_discount ?? 0), 2);
              if (strtolower((string) ($discount_type ?? 'flat')) === 'percentage') {
                  $discountPercentLocal = round((float) ($discount ?? 0), 2);
                  $discountComputed = round(($baseTotal * $discountPercentLocal) / 100, 2);
              } else {
                  $discountPercentLocal = null;
                  $discountComputed = round((float) ($discount ?? 0), 2);
              }
              $totalWithVat = round($baseTotal + $vatComputed, 2);
              $netComputed = round(max(0, $totalWithVat - $discountComputed - $extraDiscount), 2);
            @endphp

            <tr><td>Subtotal</td><td class="right">{{ number_format($baseTotal,2) }}</td></tr>
            @if($vatComputed != 0)
            <tr><td>Vat</td><td class="right">{{ number_format($vatComputed,2) }}</td></tr>            <tr><td><strong>Total With VAT</strong></td><td class="right"><strong>{{ number_format($totalWithVat,2) }}</strong></td></tr>            @endif
            @if($discountComputed != 0)
              @if($discountPercentLocal !== null)
                <tr><td>Discount ({{ number_format($discountPercentLocal,2) }}%)</td><td class="right">{{ number_format($discountComputed,2) }}</td></tr>
              @else
                <tr><td>Discount</td><td class="right">{{ number_format($discountComputed,2) }}</td></tr>
              @endif
            @endif
            @if($extraDiscount != 0)
              <tr><td>Extra Discount</td><td class="right">{{ number_format($extraDiscount,2) }}</td></tr>
            @endif
            <tr><td><strong>Net Payable</strong></td><td class="right"><strong>{{ number_format($netComputed,2) }}</strong></td></tr>
            <tr><td>Paid</td><td class="right">{{ number_format($paid ?? 0,2) }}</td></tr>
            @php
              $unadjustedDueFast = max(0, (float) ($net_payable ?? 0) - (float) ($paid ?? 0));
            @endphp
            <tr><td>Due</td><td class="right">{{ number_format($unadjustedDueFast,2) }}</td></tr>
            @if(isset($adjusted_due) && round((float) $adjusted_due,2) !== round((float) $unadjustedDueFast,2))
              <tr><td>Due After Returns</td><td class="right">{{ number_format($adjusted_due,2) }}</td></tr>
            @endif
          </table>
        </td>
      </tr>
    </table>

    <div style="margin-top:18px;font-size:12px;color:#666">Thank you for your visit.</div>
  </div>
  <script>
    function closeInvoiceTab() {
      try { window.open('', '_self'); } catch (e) {}
      try { window.close(); } catch (e) {}
    }

    function printAndClose() {
      var closed = false;
      var safeClose = function () {
        if (closed) return;
        closed = true;
        setTimeout(closeInvoiceTab, 120);
      };

      try {
        window.addEventListener('afterprint', safeClose, { once: true });
      } catch (e) {
        // ignore
      }

      try { window.print(); } catch (e) { safeClose(); }
    }
  </script>
  </body>
</html>