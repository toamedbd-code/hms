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
            Schema::create('billings', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->unique(); // INV-2025-000001
                $table->string('bill_number')->unique();   // BILL-2025-000001
                $table->string('case_number')->unique();   // CASE-2025-000001

                // Patient Details
                $table->unsignedBigInteger('patient_id')->nullable();
                // $table->string('patient_name');
                $table->string('patient_mobile', 20);
                $table->enum('gender', ['Male', 'Female', 'Others']);

                // Doctor Details
                $table->unsignedBigInteger('doctor_id')->nullable();
                $table->enum('doctor_type', ['admin', 'billing'])->nullable();
                $table->string('doctor_name')->nullable();

                // PC Details
                $table->unsignedBigInteger('referrer_id')->nullable();

                // Payment Details
                $table->string('card_type', 50); // Cash, Card, etc.
                $table->string('pay_mode', 50);  // Cash, Card, Mobile Banking
                $table->string('card_number')->nullable(); // Card/Account number

                // Financial Summary
                $table->decimal('total', 10, 2);
                $table->decimal('discount', 10, 2)->default(0);
                $table->decimal('extra_flat_discount', 10, 2)->default(0);
                $table->enum('discount_type', ['percentage', 'flat'])->default('percentage');
                $table->decimal('payable_amount', 10, 2);
                $table->decimal('paid_amt', 10, 2);
                $table->decimal('change_amt', 10, 2)->default(0);
                $table->decimal('receiving_amt', 10, 2);
                $table->decimal('due_amount', 10, 2);

                // Delivery and Notes
                $table->dateTime('delivery_date')->nullable();
                $table->time('delivery_time')->nullable();
                $table->text('remarks')->nullable();

                // Commission Details
                $table->decimal('commission_total', 10, 2)->default(0);
                $table->decimal('physyst_amt', 10, 2)->default(0);
                $table->integer('commission_slider')->default(0); // Percentage 0-100

                // System Fields
                $table->unsignedBigInteger('created_by');
                $table->foreign('created_by')->on('admins')->references('id')->onDelete('cascade');

                $table->unsignedBigInteger('updated_by')->nullable();
                $table->foreign('updated_by')->on('admins')->references('id')->onDelete('cascade');

                $table->enum('payment_status', ['Pending', 'Partial', 'Paid'])->default('Pending');
                $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');

                $table->softDeletes();
                $table->timestamps();

                // Indexes
                // $table->index(['patient_id', 'created_at']);
                // $table->index(['doctor_id', 'created_at']);
                // $table->index(['invoice_number']);
                // $table->index(['bill_number']);
                // $table->index(['case_number']);
            });
        }

        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            Schema::dropIfExists('billings');
        }
    };
