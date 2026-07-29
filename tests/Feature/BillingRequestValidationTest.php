<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\BillingRequest;

class BillingRequestValidationTest extends TestCase
{
    /**
     * Test that room_no field validates correctly
     */
    public function test_items_room_no_field_passes_validation()
    {
        // Mock request data with room_no field
        $data = [
            'patient_id' => 1,
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Aspirin',
                    'category' => 'Medicine',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'total_amount' => 100,
                    'net_amount' => 100,
                    'room_no' => '101', // This was causing 422 error
                ]
            ],
            'total' => 100,
            'discount_type' => 'percentage',
            'payable_amount' => 100,
            'paid_amt' => 100,
        ];

        // Create a FormRequest instance and set the data
        $request = new BillingRequest();
        $request->merge($data);

        // Get the validation rules
        $rules = $request->rules();

        // Run validator
        $validator = Validator::make($data, $rules);

        // Assert that validation passes
        $this->assertTrue($validator->passes(), 'room_no field should pass validation: ' . json_encode($validator->errors()));
    }

    /**
     * Test that print_token field validates correctly
     */
    public function test_print_token_field_passes_validation()
    {
        $data = [
            'patient_id' => 1,
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Test Item',
                    'category' => 'Medicine',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'total_amount' => 100,
                    'net_amount' => 100,
                ]
            ],
            'total' => 100,
            'discount_type' => 'percentage',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'print_token' => 'pt_test_token_12345', // This was causing 422 error
        ];

        $request = new BillingRequest();
        $request->merge($data);
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes(), 'print_token should pass validation: ' . json_encode($validator->errors()));
    }

    /**
     * Test that backend_invoice field validates correctly
     */
    public function test_backend_invoice_field_passes_validation()
    {
        $data = [
            'patient_id' => 1,
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Test Item',
                    'category' => 'Medicine',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'total_amount' => 100,
                    'net_amount' => 100,
                ]
            ],
            'total' => 100,
            'discount_type' => 'percentage',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'backend_invoice' => true, // This was causing 422 error
        ];

        $request = new BillingRequest();
        $request->merge($data);
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes(), 'backend_invoice should pass validation: ' . json_encode($validator->errors()));
    }

    /**
     * Test that delivery_time field validates correctly
     */
    public function test_delivery_time_field_passes_validation()
    {
        $data = [
            'patient_id' => 1,
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Test Item',
                    'category' => 'Medicine',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'total_amount' => 100,
                    'net_amount' => 100,
                ]
            ],
            'total' => 100,
            'discount_type' => 'percentage',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'delivery_date' => '2026-06-27',
            'delivery_time' => '19:00', // This was causing 422 error
        ];

        $request = new BillingRequest();
        $request->merge($data);
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes(), 'delivery_time should pass validation: ' . json_encode($validator->errors()));
    }

    /**
     * Test complete bill submission with all new fields
     */
    public function test_complete_bill_submission_with_all_fields()
    {
        $data = [
            'patient_id' => 1,
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Aspirin',
                    'category' => 'Medicine',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'total_amount' => 100,
                    'discount' => 0,
                    'net_amount' => 100,
                    'room_no' => '101',
                ]
            ],
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'percentage',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'due_amount' => 0,
            'change_amt' => 0,
            'receiving_amt' => 0,
            'return_amt' => 0,
            'delivery_date' => '2026-06-27',
            'delivery_time' => '19:00',
            'billing_date' => '2026-06-27',
            'billing_time' => '14:30:00',
            'print_token' => 'pt_abc123def456',
            'backend_invoice' => false,
            'remarks' => 'Test bill',
            'commission_total' => 0,
        ];

        $request = new BillingRequest();
        $request->merge($data);
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes(), 'Complete submission should pass validation: ' . json_encode($validator->errors()));
    }
}
