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
            Schema::create('pathologyparameters', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('referance_from');
                $table->string('referance_to');
                $table->unsignedBigInteger('pathology_unit_id');
                $table->foreign('pathology_unit_id')->references('id')->on('pathologyunits')->onDelete('cascade');
                $table->string('description')->nullable();

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
            Schema::dropIfExists('pathologyparameters');
        }
    };
