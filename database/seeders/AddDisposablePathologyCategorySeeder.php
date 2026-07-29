<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDisposablePathologyCategorySeeder extends Seeder
{
    public function run()
    {
        $name = 'Disposable';

        // If the pathologycategories table doesn't exist in this environment, skip.
        if (! Schema::hasTable('pathologycategories')) {
            return;
        }

        $exists = DB::table('pathologycategories')->where('name', $name)->exists();

        if (! $exists) {
            DB::table('pathologycategories')->insert([
                'name' => $name,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
