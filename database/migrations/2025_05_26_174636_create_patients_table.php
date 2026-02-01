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
            Schema::create('patients', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guardian_name')->nullable();
                $table->enum('gender', ['Male', 'Female', 'Others'])->nullable();
                $table->date('dob')->nullable();
                $table->string('age')->nullable();
                $table->enum('blood_group', ['A+', 'B+', 'O+', 'AB+', 'AB-', 'A-', 'B-', 'O-'])->nullable();
                $table->enum('marital_status', ['Single', 'Married', 'Widowed', 'Separated', 'Not Specific'])->nullable();
                $table->string('photo')->nullable();
                $table->string('phone');
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->longText('remarks', 5000)->nullable();
                $table->longText('any_known_allergies', 5000)->nullable();
                $table->unsignedBigInteger('tpa_id')->nullable();
                $table->string('tpa_code')->nullable();
                $table->string('tpa_validity')->nullable();
                $table->string('tpa_nid')->nullable();

                $table->enum('status',['Active','Inactive','Deleted'])->default('Active');
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
            Schema::dropIfExists('patients');
        }
    };
