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
            Schema::create('opdpatients', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('patient_id');
                $table->foreign('patient_id')->on('patients')->references('id')->onDelete('cascade');

                $table->string('symptom_type')->nullable();
                $table->string('symptom_title')->nullable();
                $table->text('symptom_description')->nullable();
                $table->text('note')->nullable();
                $table->text('allergies')->nullable();

                $table->date('appointment_date');
                $table->enum('case', ['new', 'followup', 'emergency'])->default('new');
                $table->enum('casualty', ['yes', 'no'])->default('no');
                $table->enum('old_patient', ['yes', 'no'])->default('no');
                $table->string('reference')->nullable();

                $table->unsignedBigInteger('consultant_doctor_id');
                $table->foreign('consultant_doctor_id')->on('admins')->references('id')->onDelete('cascade');

                $table->boolean('apply_tpa')->default(false);
                $table->string('tpa_details')->nullable();

                $table->string('charge_id')->nullable();
                $table->string('charge_type_id')->nullable();
                $table->decimal('applied_charge', 10, 2)->default(0);
                $table->decimal('standard_charge', 10, 2)->default(0);
                $table->decimal('tax', 5, 2)->default(0);
                $table->decimal('discount', 5, 2)->default(0);
                $table->enum('payment_mode', ['cash', 'card', 'online', 'insurance'])->default('cash');
                $table->decimal('amount', 10, 2)->default(0);
                $table->decimal('paid_amount', 10, 2)->default(0);
                $table->decimal('balance_amount', 10, 2)->default(0);

                $table->enum('live_consultation', ['yes', 'no'])->default('no');
                $table->string('consultation_type')->nullable(); 

                $table->enum('payment_status', ['Paid', 'Pending', 'Partial'])->default('Pending');

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
            Schema::dropIfExists('opdpatients');
        }
    };
