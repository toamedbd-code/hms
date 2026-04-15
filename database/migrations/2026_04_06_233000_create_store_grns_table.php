<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('store_grns')) {
            return;
        }

        Schema::create('store_grns', function (Blueprint $table) {
            $table->id();
            $table->string('grn_no', 60)->unique();
            $table->string('supplier_name', 150)->nullable();
            $table->string('invoice_no', 100)->nullable();
            $table->date('receive_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();

            $table->index(['receive_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_grns');
    }
};
