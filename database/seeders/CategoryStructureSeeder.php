<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryStructureSeeder extends Seeder
{
    public function run(): void
    {
        $categoryTypes = [
            'Ambulance',
            'Appointment',
            'Blood Bank',
            'Disposable',
            'IPD',
            'OPD',
            'Pathology',
            'Radiology',
        ];

        foreach ($categoryTypes as $name) {
            $this->ensureTestCategory($name);
        }

        $reportHeadings = [
            'HAEMATOLOGY',
            'Biochemistery',
            'ELECTROLYTE',
            'HORMONE ANALYSIS',
            'SEROLOGY',
            'MRI',
            'URINE EXAMINATION',
            'MICROBIOLOGY',
            'STOOL EXAMINATION',
            'Ultrasonogram',
            'Endoscopy',
            'Ultrasound',
            'X-RAY',
            'CT SCAN',
            'IPD',
            'SEMEN ANALYSIS',
            'Disposable',
            'OPD',
        ];

        foreach ($reportHeadings as $name) {
            $existing = DB::table('main_categories')
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->first();

            if ($existing) {
                DB::table('main_categories')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $existing->name,
                        'status' => 'Active',
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
                continue;
            }

            DB::table('main_categories')->insert([
                'name' => $name,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureTestCategory(string $name): void
    {
        $existingRows = DB::table('testcategories')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->orderBy('id')
            ->get();

        if ($existingRows->isNotEmpty()) {
            $existing = $existingRows->first();
            DB::table('testcategories')
                ->where('id', $existing->id)
                ->update([
                    'name' => $name,
                    'status' => 'Active',
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            foreach ($existingRows->skip(1) as $duplicate) {
                DB::table('tests')
                    ->where('test_category_id', $duplicate->id)
                    ->update(['test_category_id' => $existing->id, 'updated_at' => now()]);
                DB::table('testcategories')->where('id', $duplicate->id)->delete();
            }

            return;
        }

        DB::table('testcategories')->insert([
            'name' => $name,
            'parent_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
