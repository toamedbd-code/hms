<?php
$token = 'eyJraWQiOiJEWXd6RU9Fd3h5Q3lkRS9ScllTUE5waTkwQ0FCRUF4dnRtSzVBdDgvaGlZPSIsImFsZyI6IlJTMjU2In0.eyJzdWIiOiI0OWRhNzUxYy1jMDYxLTcwOGItN2QzYS04MThhMTA3NmJjNTciLCJhdWQiOiJrNW1xNmJlaWs0aWR2ZDkxcDRmdGV0cHYzIiwiZXZlbnRfaWQiOiJlZTU2N2JjMi1hMjQ2LTQ4OTctYTdjNy04MDU0YjcxZDY2ZjUiLCJ0b2tlbl91c2UiOiJpZCIsImF1dGhfdGltZSI6MTc4NTg0MzE0NCwiaXNzIjoiaHR0cHM6Ly9jb2duaXRvLWlkcC5hcG0tc291dGhlYXN0LTEuYW1hem9uYXdzLmNvbS9hcC1zb3V0aGVhc3QtMV9UM1ZLQ2cyeSIsImNvZ25pdG86dXNlcm5hbWUiOiIwMTQxMDU5MjYzOCIsImV4cCI6MTc4NTg0Njc0NCwiaWF0IjoxNzg1ODQzMTQ0fQ.OMekRj0Ez6pIKYqaEjjUc98xbmWdkg0FeGQsi2shc4rCGk3hxg98ZdBzPQR3r3yMVPYlmMIL9kz83T44KwyVAW24gRjRwnUoAdo584EjgGsyPuqD-EYg3_PjsproQadLlrHs26hiTSSubSrZfVfxFM3A86LFRY_4NyAsKUP0l_kLVrFuZD3jUpXlxyIGXOcmXj_p4j75R6RBd7gPVr5QHSy6jVAQ4LnTt_ajGBcHndCxcuqXlGUNKhaJZEd6e2kVe4IhGqhzwyi3-EVHli_ipiudHWWApV4JwJGpWpUhCGHAcz655DqnP1YGY1CKgyjXBcz8FVT429gYxFigzuPPhA';
$url='https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/create';
$variants = [
    ['name'=>'minimal', 'payload'=>['mode'=>'0011','callbackURL'=>'https://merchant-callback.example.com/payment/bkash/callback','amount'=>'100.00','currency'=>'BDT','intent'=>'sale','merchantInvoiceNumber'=>'1001']],
    ['name'=>'with_payer_ref', 'payload'=>['mode'=>'0011','callbackURL'=>'https://merchant-callback.example.com/payment/bkash/callback','amount'=>'100.00','currency'=>'BDT','intent'=>'sale','merchantInvoiceNumber'=>'1001','payerReference'=>'01410592638']],
    ['name'=>'with_customer_msisdn', 'payload'=>['mode'=>'0011','callbackURL'=>'https://merchant-callback.example.com/payment/bkash/callback','amount'=>'100.00','currency'=>'BDT','intent'=>'sale','merchantInvoiceNumber'=>'1001','payerReference'=>'01410592638','customerMsisdn'=>'01410592638']],
    ['name'=>'with_invoice_only', 'payload'=>['mode'=>'0011','callbackURL'=>'https://merchant-callback.example.com/payment/bkash/callback','amount'=>'100.00','currency'=>'BDT','intent'=>'sale','merchantInvoiceNumber'=>'1001','payerReference'=>''],'],
];
foreach($variants as $variant){
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($variant['payload']),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $token,
            'X-App-Key: 5ET8KfYGKWvy4f1qjocFIdGatc',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "=== {$variant['name']} ===\n";
    echo "HTTP=$status\n";
    echo "$resp\n\n";
    curl_close($ch);
}
