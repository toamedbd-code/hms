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
            Schema::create('ipdpatients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('patient_id');
                $table->foreign('patient_id')->on('patients')->references('id')->onDelete('cascade');

                $table->unsignedBigInteger('consultant_doctor_id');
                $table->foreign('consultant_doctor_id')->on('admins')->references('id')->onDelete('cascade');

                // Symptoms information
                $table->string('symptom_type')->nullable();
                $table->string('symptom_title')->nullable();
                $table->text('symptom_description')->nullable();
                $table->text('note')->nullable();

                // Admission details
                $table->dateTime('admission_date');
                $table->string('case')->nullable();
                $table->string('tpa')->nullable();
                $table->enum('casualty', ['yes', 'no'])->default('no');
                $table->enum('old_patient', ['yes', 'no'])->default('no');
                $table->decimal('credit_limit', 10, 2)->nullable();
                $table->string('reference')->nullable();

                // Bed information
                $table->unsignedBigInteger('bed_group_id');
                
                $table->unsignedBigInteger('bed_id');
                $table->foreign('bed_id')->on('beds')->references('id')->onDelete('cascade');

                $table->enum('live_consultation', ['yes', 'no'])->default('no');
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
            Schema::dropIfExists('ipdpatients');
        }
    };
