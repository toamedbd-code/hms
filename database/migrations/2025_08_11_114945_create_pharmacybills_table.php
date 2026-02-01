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
            Schema::create('pharmacybills', function (Blueprint $table) {
                $table->id();
                $table->string('pharmacy_no')->unique();
                $table->string('bill_no')->unique();
                $table->string('case_id')->nullable();
                $table->date('date');

                // Patient Information
                $table->unsignedBigInteger('patient_id')->nullable();
                $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');

                // Doctor Information
                $table->unsignedBigInteger('doctor_id')->nullable();
                $table->string('doctor_name')->nullable();

                $table->json('products');

                // Financial Calculations
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('discount_percentage', 5, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->decimal('vat_percentage', 5, 2)->default(0);
                $table->decimal('vat_amount', 10, 2)->default(0);
                $table->decimal('extra_discount', 10, 2)->default(0);
                $table->decimal('net_amount', 10, 2)->default(0);

                // Payment Information
                $table->enum('payment_mode', ['Cash', 'Card', 'Bank Transfer'])->default('Cash');
                $table->decimal('payment_amount', 10, 2)->default(0);
                $table->text('note')->nullable();

                // System Fields
                $table->unsignedBigInteger('created_by');
                $table->foreign('created_by')->on('admins')->references('id')->onDelete('cascade');
                
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->foreign('updated_by')->on('admins')->references('id')->onDelete('cascade');

                $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');

                $table->softDeletes();
                $table->timestamps();
            });
        }

        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            Schema::dropIfExists('pharmacybills');
        }
    };
