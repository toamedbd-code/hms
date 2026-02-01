<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use App\Models\ChargeType;
use App\Models\ChargeCategory;
use App\Models\ChargeUnitType;
use App\Models\ChargeTaxCategory;
use App\Models\Charge;
use Inertia\Inertia;

class ChargeImportController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:charge-import-form')->only(['showImportForm']);
    }

    public function showImportForm()
    {
        return Inertia::render('Backend/BulkImport/Charge');
    }

    public function processImport(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('csv_file');
        
        // Read file content more safely
        $content = file_get_contents($file->getPathname());
        $csvData = array_map('str_getcsv', explode("\n", $content));
        
        // Remove empty rows
        $csvData = array_filter($csvData, function($row) {
            return !empty(array_filter($row));
        });
        
        if (empty($csvData)) {
            return back()->withErrors([
                'csv_file' => 'CSV file is empty or invalid.'
            ]);
        }

        $headers = array_shift($csvData);
        
        // Clean headers (remove BOM and trim whitespace)
        $headers = array_map(function($header) {
            return trim($header, " \t\n\r\0\x0B\xEF\xBB\xBF");
        }, $headers);

        $expectedHeaders = [
            'charge_type_name',
            'charge_type_modules',
            'charge_category_name',
            'charge_category_description',
            'charge_unit_type_name',
            'charge_tax_category_name',
            'tax_category_percentage',
            'charge_name',
            'charge_tax',
            'charge_standard_charge',
            'charge_description',
            'status'
        ];

        // Debug: Log headers for comparison
        Log::info('CSV Headers:', $headers);
        Log::info('Expected Headers:', $expectedHeaders);

        if ($headers !== $expectedHeaders) {
            return back()->withErrors([
                'csv_file' => 'Invalid CSV format. Headers received: ' . implode(', ', $headers) . '. Please download the sample CSV for the correct format.'
            ]);
        }

        $errors = [];
        $successCount = 0;

        DB::beginTransaction();

        try {
            foreach ($csvData as $index => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Ensure row has same number of columns as headers
                if (count($row) !== count($headers)) {
                    $errors[] = "Row " . ($index + 2) . ": Column count mismatch. Expected " . count($headers) . " columns, got " . count($row);
                    continue;
                }

                $rowData = array_combine($headers, $row);
                
                // Trim all values
                $rowData = array_map('trim', $rowData);

                // Log row data for debugging
                Log::info("Processing row " . ($index + 2), $rowData);

                $validator = Validator::make($rowData, [
                    'charge_type_name' => 'required|string',
                    'charge_type_modules' => 'required|string',
                    'charge_category_name' => 'required|string',
                    'charge_category_description' => 'required|string',
                    'charge_unit_type_name' => 'required|string',
                    'charge_tax_category_name' => 'required|string',
                    'tax_category_percentage' => 'required|numeric|min:0|max:100',
                    'charge_name' => 'required|string',
                    'charge_tax' => 'nullable|numeric|min:0',
                    'charge_standard_charge' => 'nullable|numeric|min:0',
                    'charge_description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive,Deleted'
                ]);

                if ($validator->fails()) {
                    $errors[] = "Row " . ($index + 2) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }

                try {
                    // Create/Find ChargeType
                    $chargeType = ChargeType::firstOrCreate(
                        ['name' => $rowData['charge_type_name']],
                        [
                            'modules' => $rowData['charge_type_modules'],
                            'status' => $rowData['status'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );

                    Log::info("ChargeType created/found:", ['id' => $chargeType->id, 'name' => $chargeType->name]);

                    // Create/Find ChargeCategory
                    $chargeCategory = ChargeCategory::firstOrCreate(
                        [
                            'name' => $rowData['charge_category_name'],
                            'charge_type_id' => $chargeType->id
                        ],
                        [
                            'description' => $rowData['charge_category_description'],
                            'status' => $rowData['status'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );

                    Log::info("ChargeCategory created/found:", ['id' => $chargeCategory->id, 'name' => $chargeCategory->name]);

                    // Create/Find UnitType
                    $unitType = ChargeUnitType::firstOrCreate(
                        ['name' => $rowData['charge_unit_type_name']],
                        [
                            'status' => $rowData['status'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );

                    Log::info("UnitType created/found:", ['id' => $unitType->id, 'name' => $unitType->name]);

                    // Create/Find TaxCategory
                    $taxCategory = ChargeTaxCategory::firstOrCreate(
                        ['name' => $rowData['charge_tax_category_name']],
                        [
                            'percentage' => (float)$rowData['tax_category_percentage'],
                            'status' => $rowData['status'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );

                    Log::info("TaxCategory created/found:", ['id' => $taxCategory->id, 'name' => $taxCategory->name]);

                    // Create/Find Charge
                    $charge = Charge::firstOrCreate(
                        ['name' => $rowData['charge_name']],
                        [
                            'charge_type_id' => $chargeType->id,
                            'charge_category_id' => $chargeCategory->id,
                            'unit_type_id' => $unitType->id,
                            'tax_category_id' => $taxCategory->id,
                            'tax' => !empty($rowData['charge_tax']) ? (float)$rowData['charge_tax'] : null,
                            'standard_charge' => !empty($rowData['charge_standard_charge']) ? (float)$rowData['charge_standard_charge'] : null,
                            'description' => !empty($rowData['charge_description']) ? $rowData['charge_description'] : null,
                            'status' => $rowData['status'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );

                    Log::info("Charge created/found:", ['id' => $charge->id, 'name' => $charge->name]);

                    $successCount++;

                } catch (\Exception $e) {
                    Log::error("Error processing row " . ($index + 2), [
                        'error' => $e->getMessage(),
                        'row_data' => $rowData
                    ]);
                    $errors[] = "Row " . ($index + 2) . ": Failed to create charge - " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "Import completed successfully. $successCount records imported.";

            if (!empty($errors)) {
                return back()->with([
                    'success' => $message,
                    'importErrors' => $errors
                ]);
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Import failed:", ['error' => $e->getMessage()]);
            
            return back()->withErrors([
                'csv_file' => 'Import failed: ' . $e->getMessage()
            ]);
        }
    }

    public function downloadSampleCsv()
    {
        $filename = "charge_import_sample.csv";
        
        $sampleData = [
            [
                'Consultation',
                'OPD,Appointment',
                'Consultation Fees',
                'Fees for doctor consultations',
                'Per Visit',
                'Standard Tax',
                '15.00',
                'General Consultation',
                '15.00',
                '500.00',
                'General doctor consultation fee',
                'Active'
            ],
            [
                'Lab Test',
                'Diagnostics,Lab',
                'Blood Tests',
                'Various blood tests',
                'Per Test',
                'Medical Tax',
                '10.00',
                'CBC Test',
                '10.00',
                '300.00',
                'Complete Blood Count test',
                'Active'
            ]
        ];

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () use ($sampleData) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Add CSV headers
            fputcsv($file, [
                'charge_type_name',
                'charge_type_modules',
                'charge_category_name',
                'charge_category_description',
                'charge_unit_type_name',
                'charge_tax_category_name',
                'tax_category_percentage',
                'charge_name',
                'charge_tax',
                'charge_standard_charge',
                'charge_description',
                'status'
            ]);

            // Add sample data
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}