<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove legacy ECG/Ultrasound referral categories (if still present)
        DB::table('referralcategories')
            ->whereIn('name', ['ECG', 'Ultrasound'])
            ->delete();

        // Ensure default PC and Doctor categories exist
        foreach (['PC', 'Doctor'] as $name) {
            $exists = DB::table('referralcategories')
                ->where('name', $name)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('referralcategories')->insert([
                    'name'       => $name,
                    'group'      => null,
                    'status'     => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('referralcategories')
            ->whereIn('name', ['PC', 'Doctor'])
            ->delete();
    }
};
