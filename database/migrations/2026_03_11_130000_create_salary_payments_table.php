<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            $table->string('month', 7)->index(); // YYYY-MM
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('status')->default('Paid');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('salary_payments');
    }
};
