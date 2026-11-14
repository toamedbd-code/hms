<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BillItem;
use App\Models\Billing;
use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportingPrintSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_signature_is_rendered_and_signature_left_margin_is_applied(): void
    {
        Storage::fake('public');
        $signatureBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS8AAAAASUVORK5CYII=');
        if (extension_loaded('gd')) {
            $signatureImage = imagecreatetruecolor(5, 5);
            $white = imagecolorallocate($signatureImage, 255, 255, 255);
            $ink = imagecolorallocate($signatureImage, 20, 30, 40);
            imagefill($signatureImage, 0, 0, $white);
            imagesetpixel($signatureImage, 2, 2, $ink);
            ob_start();
            imagepng($signatureImage);
            $signatureBytes = ob_get_clean();
            imagedestroy($signatureImage);
        }
        Storage::disk('public')->put('report-signatures/technologist.png', $signatureBytes);

        $role = Role::query()->create(['name' => 'report-print-test', 'guard_name' => 'admin']);
        $permission = Permission::query()->firstOrCreate([
            'name' => 'reporting',
            'guard_name' => 'admin',
        ]);
        $role->givePermissionTo($permission);

        $admin = Admin::query()->create([
            'first_name' => 'Report',
            'last_name' => 'Printer',
            'email' => 'report-print-signature@example.test',
            'phone' => '01700000002',
            'password' => bcrypt('password'),
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);
        $this->actingAs($admin, 'admin');

        $billing = Billing::query()->create([
            'invoice_number' => 'INV-PRINT-SIGNATURE',
            'bill_number' => 'BILL-PRINT-SIGNATURE',
            'case_number' => 'CASE-PRINT-SIGNATURE',
            'patient_mobile' => '01700000003',
            'gender' => 'Others',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'receiving_amt' => 100,
            'due_amount' => 0,
            'created_by' => $admin->id,
        ]);

        $billItem = BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_name' => 'Glucose',
            'category' => 'Pathology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'net_amount' => 100,
            'report_note' => 'Normal',
            'reported_at' => now(),
        ]);
        $billItem->refresh();
        $this->assertSame('Normal', $billItem->report_note);
        $this->assertNotNull($billItem->reported_at);

        WebSetting::query()->create([
            'company_name' => 'Report Center',
            'company_short_name' => 'RC',
            'phone' => '01700000004',
            'report_title' => 'Report Center',
            'status' => 'Active',
            'technologist_signature' => 'storage/report-signatures/technologist.png',
            'attendance_device_options' => [
                'reporting' => [
                    'signature' => ['margin_left' => 192],
                    'departments' => [
                        'pathology' => ['technologist_signature' => 'report-signatures/missing.png'],
                    ],
                ],
            ],
        ]);

        $response = $this->get(route('backend.reporting.print', [
            $billItem->id,
            'single_item' => 1,
        ]));

        $response->assertOk()
            ->assertSee('--report-left-margin: 192px', false)
            ->assertSee('margin-left: var(--report-left-margin, 96px)', false)
            ->assertSee('width: calc(100% - var(--report-left-margin, 96px))', false)
            ->assertSee('class="signature-image-slot"', false)
            ->assertSee('class="signature-image" src="data:image/png;base64,', false)
            ->assertSee('mix-blend-mode: multiply', false);

        if (extension_loaded('gd')) {
            preg_match('/class="signature-image" src="(data:image\/png;base64,[^"]+)"/', (string) $response->getContent(), $matches);
            $this->assertNotEmpty($matches[1] ?? null);
            $renderedImage = imagecreatefromstring(base64_decode(substr($matches[1], strpos($matches[1], ',') + 1)));
            $this->assertIsObject($renderedImage);
            $transparentBackground = imagecolorat($renderedImage, 0, 0);
            $opaqueSignature = imagecolorat($renderedImage, 2, 2);
            $this->assertSame(127, ($transparentBackground >> 24) & 0x7F);
            $this->assertSame(0, ($opaqueSignature >> 24) & 0x7F);
            imagedestroy($renderedImage);
        }
    }
}
