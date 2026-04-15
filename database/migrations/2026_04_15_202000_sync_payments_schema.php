<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('payments')) {
            // Create a consolidated payments table if it doesn't exist
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('billing_id')->nullable();
                $table->unsignedBigInteger('opd_patient_id')->nullable();
                $table->unsignedBigInteger('ipd_patient_id')->nullable();
                $table->string('provider')->default('bkash');
                $table->string('provider_payment_id')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('payment_method')->nullable();
                $table->string('transaction_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->string('payment_status')->default('Pending');
                $table->string('status')->default('initiated');
                $table->json('metadata')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });

            return;
        }

        // Table exists — add missing columns and adjust types safely.
        if (! Schema::hasColumn('payments', 'provider')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('provider')->default('bkash');
            });
        }

        if (! Schema::hasColumn('payments', 'provider_payment_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('provider_payment_id')->nullable();
            });
        }

        if (! Schema::hasColumn('payments', 'amount')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->decimal('amount', 10, 2)->default(0);
            });
        } else {
            DB::statement("ALTER TABLE `payments` MODIFY `amount` DECIMAL(10,2) NOT NULL DEFAULT 0;");
        }

        if (! Schema::hasColumn('payments', 'payment_method')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_method')->nullable();
            });
        } else {
            DB::statement("ALTER TABLE `payments` MODIFY `payment_method` VARCHAR(255) NULL;");
        }

        if (! Schema::hasColumn('payments', 'transaction_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('transaction_id')->nullable();
            });
        }

        if (! Schema::hasColumn('payments', 'notes')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->text('notes')->nullable();
            });
        }

        if (! Schema::hasColumn('payments', 'received_by')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('received_by')->nullable();
            });
        } else {
            DB::statement("ALTER TABLE `payments` MODIFY `received_by` BIGINT UNSIGNED NULL;");
        }

        if (! Schema::hasColumn('payments', 'payment_status')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_status')->default('Pending');
            });
        }

        if (! Schema::hasColumn('payments', 'status')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status')->default('initiated');
            });
        } else {
            DB::statement("ALTER TABLE `payments` CHANGE `status` `status` VARCHAR(255) NOT NULL DEFAULT 'initiated';");
        }

        if (! Schema::hasColumn('payments', 'metadata')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->json('metadata')->nullable();
            });
        }

        if (! Schema::hasColumn('payments', 'deleted_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down()
    {
        // Do not drop existing user data columns in down — keep migrations safe.
    }
};
