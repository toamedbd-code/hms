<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('store_suppliers')) {
            Schema::create('store_suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('contact_person_name')->nullable();
                $table->string('contact_person_phone')->nullable();
                $table->string('address')->nullable();
                $table->text('notes')->nullable();
                $table->enum('status', ['Active', 'Inactive'])->default('Active');
                $table->timestamps();
                $table->index(['status', 'name'], 'store_suppliers_status_name_idx');
            });
        }

        if (Schema::hasTable('store_purchase_orders')
            && !Schema::hasColumn('store_purchase_orders', 'store_supplier_id')) {
            Schema::table('store_purchase_orders', function (Blueprint $table) {
                $table->foreignId('store_supplier_id')
                    ->nullable()
                    ->after('purchase_order_no')
                    ->constrained('store_suppliers')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('store_purchase_orders') && Schema::hasColumn('store_purchase_orders', 'supplier_id')) {
            $legacySuppliers = DB::table('store_purchase_orders')
                ->join('medicinesuppliers', 'medicinesuppliers.id', '=', 'store_purchase_orders.supplier_id')
                ->select([
                    'medicinesuppliers.id as legacy_id',
                    'medicinesuppliers.name',
                    'medicinesuppliers.phone',
                    'medicinesuppliers.contact_person_name',
                    'medicinesuppliers.contact_person_phone',
                    'medicinesuppliers.address',
                    'medicinesuppliers.status',
                ])
                ->distinct()
                ->get();

            foreach ($legacySuppliers as $legacySupplier) {
                $storeSupplierId = DB::table('store_suppliers')->insertGetId([
                    'name' => $legacySupplier->name,
                    'phone' => $legacySupplier->phone,
                    'contact_person_name' => $legacySupplier->contact_person_name,
                    'contact_person_phone' => $legacySupplier->contact_person_phone,
                    'address' => $legacySupplier->address,
                    'status' => in_array($legacySupplier->status, ['Active', 'Inactive'], true)
                        ? $legacySupplier->status
                        : 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('store_purchase_orders')
                    ->where('supplier_id', $legacySupplier->legacy_id)
                    ->update(['store_supplier_id' => $storeSupplierId]);
            }

            Schema::table('store_purchase_orders', function (Blueprint $table) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_purchase_orders')
            && Schema::hasColumn('store_purchase_orders', 'store_supplier_id')
            && !Schema::hasColumn('store_purchase_orders', 'supplier_id')) {
            Schema::table('store_purchase_orders', function (Blueprint $table) {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('purchase_order_no')
                    ->constrained('medicinesuppliers')
                    ->nullOnDelete();
            });

            Schema::table('store_purchase_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('store_supplier_id');
            });
        }

        Schema::dropIfExists('store_suppliers');
    }
};
