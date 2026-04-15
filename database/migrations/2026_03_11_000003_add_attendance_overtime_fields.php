<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('recorded_out')->nullable()->after('recorded_at');
            $table->integer('duration_minutes')->nullable()->after('recorded_out');
            $table->integer('late_minutes')->nullable()->after('duration_minutes');
            $table->integer('overtime_minutes')->nullable()->after('late_minutes');
            $table->decimal('deduction_amount', 10, 2)->nullable()->after('overtime_minutes');
            $table->decimal('overtime_amount', 10, 2)->nullable()->after('deduction_amount');
        });
    }

    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'recorded_out',
                'duration_minutes',
                'late_minutes',
                'overtime_minutes',
                'deduction_amount',
                'overtime_amount'
            ]);
        });
    }
};
