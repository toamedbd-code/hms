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
            Schema::create('tpas', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code');
                $table->string('contact_number');
                $table->string('address')->nullable();
                $table->string('contact_person_name')->nullable();
                $table->string('contact_person_phone')->nullable();

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
            Schema::dropIfExists('tpas');
        }
    };
