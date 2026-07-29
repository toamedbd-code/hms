<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Payment;
use Illuminate\Support\Facades\Route;

$ids = [912, 913];
// determine loginRoute similar to controller logic
$loginRoute = Route::has('backend.auth.login2') ? 'backend.auth.login2' : (Route::has('auth.login2') ? 'auth.login2' : 'home');
foreach ($ids as $id) {
    $payment = Payment::find($id);
    if (! $payment) {
        echo "Payment $id not found, skipping.\n";
        continue;
    }
    $html = view('payment.bkash.simulate-public', ['payment' => $payment, 'loginRoute' => $loginRoute])->render();
    $path = __DIR__ . '/../storage/logs/simulate_payment_'.$id.'.html';
    file_put_contents($path, $html);
    echo "Saved simulate page for payment $id to: $path\n";
}
return 0;
