<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // add group column
        Schema::table('referralcategories', function (Blueprint $table) {
            if (!Schema::hasColumn('referralcategories', 'group')) {
                $table->string('group')->nullable()->after('name');
            }
        });

        // insert ECG and Ultrasound categories under 'Diagnostics' group
        DB::table('referralcategories')->updateOrInsert(
            ['name' => 'ECG'],
            ['group' => 'Diagnostics', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('referralcategories')->updateOrInsert(
            ['name' => 'Ultrasound'],
            ['group' => 'Diagnostics', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down()
    {
        // remove inserted categories if they exist
        DB::table('referralcategories')->whereIn('name', ['ECG', 'Ultrasound'])->delete();

        Schema::table('referralcategories', function (Blueprint $table) {
            if (Schema::hasColumn('referralcategories', 'group')) {
                $table->dropColumn('group');
            }
        });
    }
};
