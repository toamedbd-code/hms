<?php

return [
    // Flat tax rate applied on gross (0.05 = 5%). Default 0 to preserve existing behaviour in tests.
    'tax_rate' => env('PAYROLL_TAX_RATE', 0.0),

    // Whether to apply absence-based prorated deductions. Default false to preserve tests.
    'absence_deduction' => env('PAYROLL_ABSENCE_DEDUCTION', false),
];
