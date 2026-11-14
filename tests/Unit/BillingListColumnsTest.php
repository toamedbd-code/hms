<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\BillingController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class BillingListColumnsTest extends TestCase
{
    public function test_billing_list_amount_columns_match_their_headers(): void
    {
        $controller = (new ReflectionClass(BillingController::class))->newInstanceWithoutConstructor();
        $dataFieldsMethod = new ReflectionMethod(BillingController::class, 'dataFields');
        $headersMethod = new ReflectionMethod(BillingController::class, 'getTableHeaders');
        $dataFieldsMethod->setAccessible(true);
        $headersMethod->setAccessible(true);

        $dataFields = $dataFieldsMethod->invoke($controller);
        $headers = $headersMethod->invoke($controller);

        self::assertSame(
            ['patient_id', 'gross_amount', 'total', 'paid_amt', 'due_amount_display', 'payment_status', 'delivery_date', 'created_by', 'created_at'],
            array_column(array_slice($dataFields, 2), 'fieldName')
        );
        self::assertSame(
            ['Sl/No', 'Bill Number', 'Patient', 'Total Amount', 'Payable Amount', 'Paid Amount', 'Due Amount', 'Payment Status', 'Delivery Date', 'Created By', 'Created Date', 'Action'],
            $headers
        );
        self::assertCount(count($dataFields) + 1, $headers);
        self::assertSame('Action', end($headers));
    }
}
