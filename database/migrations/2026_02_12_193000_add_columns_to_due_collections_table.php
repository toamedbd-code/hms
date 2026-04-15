<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('due_collections', function (Blueprint $table) {
            if (!Schema::hasColumn('due_collections', 'billing_id')) {
                $table->unsignedBigInteger('billing_id')->nullable()->after('id');
                $table->index('billing_id');
            }

            if (!Schema::hasColumn('due_collections', 'collected_amount')) {
                $table->decimal('collected_amount', 12, 2)->default(0)->after('billing_id');
            }

            if (!Schema::hasColumn('due_collections', 'collected_at')) {
                $table->dateTime('collected_at')->nullable()->after('collected_amount');
                $table->index('collected_at');
            }

            if (!Schema::hasColumn('due_collections', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('collected_at');
            }

            if (!Schema::hasColumn('due_collections', 'note')) {
                $table->text('note')->nullable()->after('payment_method');
            }

            if (!Schema::hasColumn('due_collections', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('note');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('due_collections', function (Blueprint $table) {
            if (Schema::hasColumn('due_collections', 'billing_id')) {
                $table->dropIndex(['billing_id']);
                $table->dropColumn('billing_id');
            }

            if (Schema::hasColumn('due_collections', 'collected_amount')) {
                $table->dropColumn('collected_amount');
            }

            if (Schema::hasColumn('due_collections', 'collected_at')) {
                $table->dropIndex(['collected_at']);
                $table->dropColumn('collected_at');
            }

            if (Schema::hasColumn('due_collections', 'payment_method')) {
                $table->dropColumn('payment_method');
            }

            if (Schema::hasColumn('due_collections', 'note')) {
                $table->dropColumn('note');
            }

            if (Schema::hasColumn('due_collections', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
