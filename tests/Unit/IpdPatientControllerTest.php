<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\IpdPatientController;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class IpdPatientControllerTest extends TestCase
{
    public function test_it_uses_the_discharged_list_route_when_patient_is_discharged(): void
    {
        $controller = new class extends IpdPatientController {
            public function __construct() {}

            public function resolveForTest(string $status): string
            {
                return $this->resolveStatusRedirectRoute($status);
            }
        };

        $this->assertSame('backend.ipdpatient.discharged', $controller->resolveForTest('Inactive'));
        $this->assertSame('backend.ipdpatient.index', $controller->resolveForTest('Active'));
    }

    public function test_it_filters_active_patients_for_the_ipd_list_page(): void
    {
        $controller = new class extends IpdPatientController {
            public function __construct() {}
        };

        $query = $this->getMockBuilder(Builder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $query->expects($this->once())
            ->method('where')
            ->with('status', 'Active')
            ->willReturnSelf();

        $method = new \ReflectionMethod($controller, 'applyStatusFilter');
        $method->setAccessible(true);
        $method->invoke($controller, $query, false);
    }

    public function test_it_filters_discharged_patients_for_the_discharged_list_page(): void
    {
        $controller = new class extends IpdPatientController {
            public function __construct() {}
        };

        $query = $this->getMockBuilder(Builder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $query->expects($this->once())
            ->method('where')
            ->with('status', 'Inactive')
            ->willReturnSelf();

        $method = new \ReflectionMethod($controller, 'applyStatusFilter');
        $method->setAccessible(true);
        $method->invoke($controller, $query, true);
    }

    public function test_it_gates_discharge_certificate_when_billing_has_due_amount(): void
    {
        $controller = new class extends IpdPatientController {
            public function __construct() {}
        };

        $billing = new \stdClass();
        $billing->due_amount = 125.50;

        $method = new \ReflectionMethod($controller, 'shouldGateDischargeCertificate');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, $billing));
    }

    public function test_it_does_not_gate_discharge_certificate_when_billing_has_no_due_amount(): void
    {
        $controller = new class extends IpdPatientController {
            public function __construct() {}
        };

        $billing = new \stdClass();
        $billing->due_amount = 0;

        $method = new \ReflectionMethod($controller, 'shouldGateDischargeCertificate');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, $billing));
    }
}
