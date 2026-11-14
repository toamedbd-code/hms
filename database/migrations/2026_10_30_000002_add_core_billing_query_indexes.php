<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'billings' => [
            'billings_created_at_idx' => ['created_at'],
            'billings_doctor_created_at_idx' => ['doctor_id', 'created_at'],
            'billings_patient_created_at_idx' => ['patient_id', 'created_at'],
            'billings_referrer_created_at_idx' => ['referrer_id', 'created_at'],
        ],
        'bill_items' => [
            'bill_items_billing_active_idx' => ['billing_id', 'status', 'deleted_at'],
            'bill_items_category_active_idx' => ['category', 'status', 'deleted_at', 'billing_id'],
            'bill_items_reporter_active_idx' => ['reported_by', 'status', 'deleted_at'],
            'bill_items_collector_active_idx' => ['sample_collected_by', 'status', 'deleted_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $indexName => $columns) {
                if (
                    Schema::hasIndex($tableName, $indexName)
                    || collect($columns)->contains(
                        fn (string $column) => !Schema::hasColumn($tableName, $column)
                    )
                ) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                    $table->index($columns, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            foreach (array_keys($indexes) as $indexName) {
                if (Schema::hasIndex($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }
    }
};
