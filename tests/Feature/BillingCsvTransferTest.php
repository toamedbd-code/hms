<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingCsvTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_export_and_restore_a_bill_with_its_items_and_payments(): void
    {
        $developer = $this->createAdminWithRole('developer', 'billing-csv-developer@example.test');
        $this->actingAs($developer, 'admin');

        $billing = Billing::query()->create([
            'invoice_number' => 'INV-CSV-000001',
            'bill_number' => 'BILL-CSV-000001',
            'case_number' => 'CASE-CSV-000001',
            'patient_mobile' => '01700000001',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'percentage',
            'payable_amount' => 100,
            'paid_amt' => 40,
            'invoice_amount' => 40,
            'change_amt' => 0,
            'receiving_amt' => 40,
            'due_amount' => 60,
            'created_by' => $developer->id,
            'payment_status' => 'Partial',
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => 1,
            'item_name' => 'CSV Test Item',
            'category' => 'Pathology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 0,
            'rugound' => 0,
            'net_amount' => 100,
            'status' => 'Active',
        ]);
        Payment::query()->create([
            'billing_id' => $billing->id,
            'amount' => 40,
            'payment_method' => 'Cash',
            'received_by' => $developer->id,
            'payment_status' => 'Partial',
            'status' => 'Active',
        ]);

        $export = $this->get(route('backend.billing.export-all-csv'));
        $export->assertOk();
        $csvContent = $export->streamedContent();
        $this->assertStringContainsString('Billing Data JSON', $csvContent);

        DB::table('payments')->where('billing_id', $billing->id)->delete();
        DB::table('bill_items')->where('billing_id', $billing->id)->delete();
        DB::table('billings')->where('id', $billing->id)->delete();
        $csvContent = $this->replaceExportedCsv($csvContent, static function (array $row): array {
            $billingData = json_decode($row[6], true, 512, JSON_THROW_ON_ERROR);
            $billingData['created_by'] = 999999;
            $billingData['updated_by'] = 999999;
            $row[6] = json_encode($billingData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            $items = json_decode($row[7], true, 512, JSON_THROW_ON_ERROR);
            foreach ($items as &$item) {
                foreach (['sample_collected_by', 'reported_by', 'delivered_by', 'department_id'] as $field) {
                    if (array_key_exists($field, $item)) {
                        $item[$field] = 999999;
                    }
                }
            }
            unset($item);
            $row[7] = json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            $payments = json_decode($row[8], true, 512, JSON_THROW_ON_ERROR);
            foreach ($payments as &$payment) {
                if (array_key_exists('received_by', $payment)) {
                    $payment['received_by'] = 999999;
                }
            }
            unset($payment);
            $row[8] = json_encode($payments, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            $ledger = json_decode($row[9], true, 512, JSON_THROW_ON_ERROR);
            foreach ($ledger as &$entry) {
                if (isset($entry['transaction']['created_by'])) {
                    $entry['transaction']['created_by'] = 999999;
                }
            }
            unset($entry);
            $row[9] = json_encode($ledger, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            return $row;
        });

        $import = $this->post(route('backend.billing.import-csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('billing-list.csv', $csvContent),
        ]);

        $import->assertRedirect(route('backend.billing.list'));
        $import->assertSessionHas('successMessage', 'Imported 1 bills; skipped 0 bills already present.');
        $this->assertDatabaseHas('billings', [
            'bill_number' => 'BILL-CSV-000001',
            'total' => 100,
            'paid_amt' => 40,
            'due_amount' => 60,
            'created_by' => $developer->id,
        ]);
        $restoredBillingId = (int) DB::table('billings')->where('bill_number', 'BILL-CSV-000001')->value('id');
        $this->assertDatabaseHas('bill_items', [
            'billing_id' => $restoredBillingId,
            'item_name' => 'CSV Test Item',
            'net_amount' => 100,
            'sample_collected_by' => null,
            'reported_by' => null,
            'delivered_by' => null,
            'department_id' => null,
        ]);
        $this->assertDatabaseHas('payments', [
            'billing_id' => $restoredBillingId,
            'amount' => 40,
            'received_by' => $developer->id,
        ]);

        $this->post(route('backend.billing.import-csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('billing-list.csv', $csvContent),
        ])->assertSessionHas('successMessage', 'Imported 0 bills; skipped 1 bills already present.');
        $this->assertDatabaseCount('billings', 1);
    }

    public function test_non_developer_cannot_download_or_upload_billing_csv(): void
    {
        $admin = $this->createAdminWithRole('billing-csv-admin', 'billing-csv-admin@example.test');
        $this->actingAs($admin, 'admin');

        $this->get(route('backend.billing.export-all-csv'))->assertForbidden();
        $this->post(route('backend.billing.import-csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('billing-list.csv', "invalid\n"),
        ])->assertForbidden();
    }

    public function test_developer_can_restore_a_legacy_bill_without_bill_items(): void
    {
        $developer = $this->createAdminWithRole('developer', 'billing-csv-legacy@example.test');
        $this->actingAs($developer, 'admin');

        $billing = Billing::query()->create([
            'invoice_number' => 'INV-CSV-LEGACY-000001',
            'bill_number' => 'BILL-CSV-LEGACY-000001',
            'case_number' => 'CASE-CSV-LEGACY-000001',
            'patient_mobile' => '01700000002',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 75,
            'discount' => 0,
            'discount_type' => 'percentage',
            'payable_amount' => 75,
            'paid_amt' => 0,
            'invoice_amount' => 0,
            'change_amt' => 0,
            'receiving_amt' => 0,
            'due_amount' => 75,
            'created_by' => $developer->id,
            'payment_status' => 'Pending',
            'status' => 'Active',
        ]);

        $export = $this->get(route('backend.billing.export-all-csv'));
        $export->assertOk();
        $csvContent = $export->streamedContent();

        DB::table('billings')->where('id', $billing->id)->delete();

        $this->post(route('backend.billing.import-csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('billing-list.csv', $csvContent),
        ])
            ->assertRedirect(route('backend.billing.list'))
            ->assertSessionHas('successMessage', 'Imported 1 bills; skipped 0 bills already present.');

        $this->assertDatabaseHas('billings', [
            'bill_number' => 'BILL-CSV-LEGACY-000001',
            'total' => 75,
            'due_amount' => 75,
        ]);
        $this->assertDatabaseCount('bill_items', 0);
    }

    public function test_invalid_csv_redirects_to_billing_list_with_an_error(): void
    {
        $developer = $this->createAdminWithRole('developer', 'billing-csv-invalid@example.test');
        $this->actingAs($developer, 'admin');

        $this->post(route('backend.billing.import-csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('invalid.csv', "not a billing export\n"),
        ])
            ->assertRedirect(route('backend.billing.list'))
            ->assertSessionHasErrors('csv_file');
    }

    private function createAdminWithRole(string $roleName, string $email): Admin
    {
        $role = Role::query()->create([
            'name' => $roleName,
            'guard_name' => 'admin',
        ]);

        $admin = Admin::query()->create([
            'first_name' => 'Billing CSV',
            'last_name' => 'Test',
            'email' => $email,
            'phone' => '01700000001',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'Active',
        ]);
        $admin->assignRole($role);

        return $admin;
    }

    private function replaceExportedCsv(string $csvContent, callable $transform): string
    {
        $input = fopen('php://temp', 'r+');
        fwrite($input, $csvContent);
        rewind($input);

        $rows = [];
        while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($input);

        $rows[1] = $transform($rows[1]);
        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row, ',', '"', '');
        }
        rewind($output);
        $result = stream_get_contents($output);
        fclose($output);

        return $result;
    }
}
