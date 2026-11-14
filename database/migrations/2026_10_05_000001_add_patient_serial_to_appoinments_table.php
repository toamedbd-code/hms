<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('appoinments', 'patient_serial')) {
            Schema::table('appoinments', function (Blueprint $table) {
                $table->unsignedInteger('patient_serial')->nullable()->after('slot');
            });
        }

        $serials = [];
        DB::table('appoinments')
            ->whereNull('deleted_at')
            ->orderBy('doctor_id')
            ->orderBy('appoinment_date')
            ->orderBy('id')
            ->select(['id', 'doctor_id', 'appoinment_date'])
            ->chunk(500, function ($appointments) use (&$serials) {
                foreach ($appointments as $appointment) {
                    $date = substr((string) $appointment->appoinment_date, 0, 10);
                    $key = $appointment->doctor_id . ':' . $date;
                    $serials[$key] = ($serials[$key] ?? 0) + 1;

                    DB::table('appoinments')
                        ->where('id', $appointment->id)
                        ->update(['patient_serial' => $serials[$key]]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('appoinments', 'patient_serial')) {
            Schema::table('appoinments', function (Blueprint $table) {
                $table->dropColumn('patient_serial');
            });
        }
    }
};