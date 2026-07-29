<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('testcategories')->updateOrInsert(
            ['name' => 'ECHO & ECG'],
            ['status' => 'Active', 'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('testcategories')->updateOrInsert(
            ['name' => 'Ultrasound'],
            ['status' => 'Active', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down()
    {
        DB::table('testcategories')->whereIn('name', ['ECHO & ECG', 'Ultrasound'])->delete();
    }
};
