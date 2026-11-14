<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_no', 40)->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->index(['name', 'phone'], 'lab_patients_name_phone_idx');
        });

        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('test_code', 40)->unique();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->string('specimen', 100)->nullable();
            $table->string('method', 150)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('reference_range', 150)->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('turnaround_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name'], 'lab_tests_active_name_idx');
        });

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->foreignId('lab_patient_id')->constrained('lab_patients')->restrictOnDelete();
            $table->string('status', 30)->default('Registered');
            $table->dateTime('ordered_at');
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'ordered_at'], 'lab_orders_status_date_idx');
        });

        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->restrictOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('price', 12, 2);
            $table->string('sample_code', 50)->unique();
            $table->string('sample_status', 24)->default('Pending');
            $table->dateTime('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->longText('result_text')->nullable();
            $table->string('result_status', 24)->default('Pending');
            $table->foreignId('entered_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
            $table->index(['lab_order_id', 'result_status'], 'lab_order_items_status_idx');
        });

        Schema::create('lab_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->string('payment_method', 40)->default('Cash');
            $table->string('reference_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['lab_order_id', 'payment_date'], 'lab_payments_order_date_idx');
        });

        Schema::create('lab_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->string('unit', 40)->default('pcs');
            $table->decimal('current_stock', 14, 2)->default(0);
            $table->decimal('reorder_level', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name'], 'lab_inventory_active_name_idx');
        });

        Schema::create('lab_test_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->foreignId('lab_inventory_item_id')->constrained('lab_inventory_items')->restrictOnDelete();
            $table->decimal('quantity_per_test', 12, 2);
            $table->timestamps();
            $table->unique(['lab_test_id', 'lab_inventory_item_id'], 'lab_test_inventory_unique');
        });

        Schema::create('lab_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_inventory_item_id')->constrained('lab_inventory_items')->restrictOnDelete();
            $table->string('movement_type', 20);
            $table->decimal('quantity', 14, 2);
            $table->string('reference_no', 100)->nullable();
            $table->text('reason')->nullable();
            $table->date('movement_date');
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['lab_inventory_item_id', 'movement_date'], 'lab_inventory_move_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_inventory_movements');
        Schema::dropIfExists('lab_test_inventory_items');
        Schema::dropIfExists('lab_inventory_items');
        Schema::dropIfExists('lab_payments');
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_tests');
        Schema::dropIfExists('lab_patients');
    }
};
