<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Billing;
use App\Http\Controllers\Backend\InvoiceController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GenerateInvoiceFullPdf extends Command
{
    protected $signature = 'invoices:generate-full {id} {module=billing} {print_token?}';
    protected $description = 'Generate full-fidelity invoice PDF for a billing id and cache it';

    public function handle()
    {
        $id = $this->argument('id');
        $module = $this->argument('module') ?? 'billing';

        try {
            $billing = Billing::find($id);
            if (!$billing) {
                $this->error('Billing not found: ' . $id);
                return 1;
            }

            $controller = app(InvoiceController::class);
            $printToken = $this->argument('print_token') ?? null;
            $result = $controller->generateFullPdf($billing, $module);

            if ($result) {
                // If caller provided a print token, notify waiting preview by mapping token -> billing id
                if (!empty($printToken) && is_string($printToken)) {
                    try {
                        Cache::put('print_token_' . $printToken, $billing->id, now()->addMinutes(5));
                    } catch (\Throwable $e) {
                        Log::warning('Failed to put print_token cache: ' . $e->getMessage());
                    }
                }

                $this->info('Generated full invoice PDF: ' . $result);
                return 0;
            }

            $this->error('Failed to generate full invoice PDF');
            return 1;
        } catch (\Throwable $e) {
            Log::error('invoices:generate-full failed: ' . $e->getMessage());
            $this->error('Exception: ' . $e->getMessage());
            return 1;
        }
    }
}
