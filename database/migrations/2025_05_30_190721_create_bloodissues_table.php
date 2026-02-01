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
            Schema::create('bloodissues', function (Blueprint $table) {
                $table->id();
                $table->string('case_id')->nullable();
                
                $table->unsignedBigInteger('patient_id');
                $table->foreign('patient_id')->on('patients')->references('id')->onDelete('cascade');
                
                $table->date('issue_date');
                
                $table->unsignedBigInteger('doctor_id');
                $table->foreign('doctor_id')->on('admins')->references('id')->onDelete('cascade');

                $table->string('reference_name');
                $table->string('technician')->nullable();

                $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
                $table->string('bag');

                $table->string('charge_category');
                $table->string('charge_name');
                $table->decimal('standard_charge', 10, 2)->default(0);
                $table->text('note')->nullable();

                $table->decimal('total', 10, 2)->default(0);
                $table->decimal('discount', 10, 2)->default(0);
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->decimal('tax_percent', 5, 2)->default(0);
                $table->decimal('net_amount', 10, 2)->default(0);

                $table->enum('payment_mode', ['Cash', 'Card', 'Cheque'])->default('Cash');
                $table->decimal('payment_amount', 10, 2)->default(0);
                $table->boolean('apply_tpa')->default(false);

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
            Schema::dropIfExists('bloodissues');
        }
    };
