<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InvoiceQrRedirectTest extends TestCase
{
    public function test_short_invoice_qr_redirects_to_the_cached_relative_destination(): void
    {
        Cache::put(
            'invoice_qr_short:AbCdEf0123',
            '/download-invoice?id=42&signature=valid',
            now()->addMinutes(5)
        );

        $this->get(route('backend.invoice.qr.resolve', 'AbCdEf0123'))
            ->assertRedirect('/download-invoice?id=42&signature=valid');
    }

    public function test_short_invoice_qr_cannot_redirect_to_an_external_url(): void
    {
        Cache::put(
            'invoice_qr_short:AbCdEf0123',
            '//example.com/invoice',
            now()->addMinutes(5)
        );

        $this->get(route('backend.invoice.qr.resolve', 'AbCdEf0123'))
            ->assertNotFound();
    }

    public function test_unknown_short_invoice_qr_returns_not_found(): void
    {
        $this->get(route('backend.invoice.qr.resolve', 'AbCdEf0123'))
            ->assertNotFound();
    }
}
