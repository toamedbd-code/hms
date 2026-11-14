<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name'], 'lab_suppliers_active_name_idx');
        });

        Schema::create('lab_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 60)->unique();
            $table->foreignId('lab_supplier_id')->constrained('lab_suppliers')->restrictOnDelete();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('Ordered');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'order_date'], 'lab_purchase_orders_status_date_idx');
        });

        Schema::create('lab_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_purchase_order_id')->constrained('lab_purchase_orders')->cascadeOnDelete();
            $table->foreignId('lab_inventory_item_id')->constrained('lab_inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
            $table->unique(['lab_purchase_order_id', 'lab_inventory_item_id'], 'lab_po_item_unique');
        });

        Schema::create('lab_purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 60)->unique();
            $table->foreignId('lab_purchase_order_id')->constrained('lab_purchase_orders')->restrictOnDelete();
            $table->date('received_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['lab_purchase_order_id', 'received_date'], 'lab_purchase_receipt_order_date_idx');
        });

        Schema::create('lab_purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_purchase_receipt_id')->constrained('lab_purchase_receipts')->cascadeOnDelete();
            $table->foreignId('lab_purchase_order_item_id')->constrained('lab_purchase_order_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_purchase_order_id')->constrained('lab_purchase_orders')->restrictOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('reference_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['lab_purchase_order_id', 'payment_date'], 'lab_purchase_payment_order_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_purchase_payments');
        Schema::dropIfExists('lab_purchase_receipt_items');
        Schema::dropIfExists('lab_purchase_receipts');
        Schema::dropIfExists('lab_purchase_order_items');
        Schema::dropIfExists('lab_purchase_orders');
        Schema::dropIfExists('lab_suppliers');
    }
};
