<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('store_purchase_orders')) {
            Schema::create('store_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('purchase_order_no', 60)->unique();
                $table->foreignId('supplier_id')->constrained('medicinesuppliers')->restrictOnDelete();
                $table->date('order_date');
                $table->date('expected_delivery_date')->nullable();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->enum('status', ['Ordered', 'Partially Received', 'Received', 'Cancelled'])->default('Ordered');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['status', 'order_date']);
            });
        }

        if (!Schema::hasTable('store_purchase_order_items')) {
            Schema::create('store_purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_purchase_order_id')->constrained('store_purchase_orders')->cascadeOnDelete();
                $table->foreignId('store_item_id')->constrained('store_items')->restrictOnDelete();
                $table->decimal('quantity', 14, 2);
                $table->decimal('unit_cost', 14, 2);
                $table->decimal('line_total', 14, 2);
                $table->timestamps();
                $table->index(['store_purchase_order_id', 'store_item_id'], 'store_po_items_order_item_idx');
            });
        }

        if (Schema::hasTable('store_grns') && !Schema::hasColumn('store_grns', 'store_purchase_order_id')) {
            Schema::table('store_grns', function (Blueprint $table) {
                $table->foreignId('store_purchase_order_id')
                    ->nullable()
                    ->after('grn_no')
                    ->constrained('store_purchase_orders')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('store_grn_items') && !Schema::hasColumn('store_grn_items', 'store_purchase_order_item_id')) {
            Schema::table('store_grn_items', function (Blueprint $table) {
                $table->foreignId('store_purchase_order_item_id')
                    ->nullable()
                    ->after('store_grn_id')
                    ->constrained('store_purchase_order_items')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('store_purchase_payments')) {
            Schema::create('store_purchase_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_purchase_order_id')->constrained('store_purchase_orders')->restrictOnDelete();
                $table->foreignId('payment_account_id')->nullable()->constrained('accounts')->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->date('payment_date');
                $table->string('reference_no', 100)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['store_purchase_order_id', 'payment_date'], 'store_po_payments_order_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_purchase_payments');

        if (Schema::hasTable('store_grn_items') && Schema::hasColumn('store_grn_items', 'store_purchase_order_item_id')) {
            Schema::table('store_grn_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('store_purchase_order_item_id');
            });
        }

        if (Schema::hasTable('store_grns') && Schema::hasColumn('store_grns', 'store_purchase_order_id')) {
            Schema::table('store_grns', function (Blueprint $table) {
                $table->dropConstrainedForeignId('store_purchase_order_id');
            });
        }

        Schema::dropIfExists('store_purchase_order_items');
        Schema::dropIfExists('store_purchase_orders');
    }
};
