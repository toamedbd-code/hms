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
            Schema::create('pathologies', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('patient_id')->nullable();
                $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');

                $table->string('pathology_no')->unique();
                $table->string('bill_no')->unique();
                $table->string('case_id');
                
                $table->boolean('apply_tpa')->default(false);
                $table->date('date');

                $table->unsignedBigInteger('doctor_id')->nullable();
                // $table->foreign('doctor_id')->references('id')->on('admins')->onDelete('cascade');
                $table->string('doctor_name')->nullable();

                $table->unsignedBigInteger('payee_id')->nullable(); 
                $table->foreign('payee_id')->references('id')->on('referralpeople')->onDelete('cascade');

                $table->decimal('commission_percentage', 5, 2)->nullable();
                $table->decimal('commission_amount', 10, 2)->nullable();

                $table->json('tests');

                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('discount_percentage', 5, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->decimal('vat_percentage', 5, 2)->default(0);
                $table->decimal('vat_amount', 10, 2)->default(0);
                $table->decimal('tax_percentage', 5, 2)->default(0);
                $table->decimal('tax_amount', 10, 2)->default(0);
                $table->decimal('extra_vat_percentage', 5, 2)->default(0);
                $table->decimal('extra_vat_amount', 10, 2)->default(0);
                $table->decimal('extra_discount', 10, 2)->default(0);
                $table->decimal('net_amount', 10, 2)->default(0);

                $table->enum('payment_mode', ['Cash', 'Card', 'Bank Transfer'])->default('Cash');
                $table->decimal('payment_amount', 10, 2)->default(0);

                $table->unsignedBigInteger('created_by');
                $table->foreign('created_by')->on('admins')->references('id')->onDelete('cascade');

                $table->unsignedBigInteger('updated_by')->nullable();
                $table->foreign('updated_by')->on('admins')->references('id')->onDelete('cascade');

                $table->text('note')->nullable();

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
            Schema::dropIfExists('pathologies');
        }
    };
