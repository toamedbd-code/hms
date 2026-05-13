<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrgStructureSeeder extends Seeder
{
    public function run()
    {
        $now = now();

        $companies = DB::table('companies')->select('id')->get();

        if ($companies->isEmpty()) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'Default Company',
                'short_name' => 'Default',
                'phone' => null,
                'email' => 'default-company@example.com',
                'logo' => null,
                'favicon' => null,
                'address' => null,
                'sorting' => 1,
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $companies = collect([(object)['id' => $companyId]]);
        }

        foreach ($companies as $company) {
            $branchId = DB::table('branches')
                ->where('company_id', $company->id)
                ->where('code', 'MAIN')
                ->value('id');

            if (!$branchId) {
                $branchId = DB::table('branches')->insertGetId([
                    'company_id' => $company->id,
                    'code' => 'MAIN',
                    'name' => 'Main Branch',
                    'timezone' => 'Asia/Dhaka',
                    'currency_code' => 'BDT',
                    'status' => 'Active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('cost_centers')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'code' => 'GENERAL',
                ],
                [
                    'branch_id' => $branchId,
                    'name' => 'General Cost Center',
                    'status' => 'Active',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            DB::table('profit_centers')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'code' => 'REVENUE',
                ],
                [
                    'branch_id' => $branchId,
                    'name' => 'General Profit Center',
                    'status' => 'Active',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
