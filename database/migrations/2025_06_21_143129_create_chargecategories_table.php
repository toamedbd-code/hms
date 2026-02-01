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
            Schema::create('chargecategories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('charge_type_id');
                $table->foreign('charge_type_id')->references('id')->on('chargetypes')->onDelete('cascade');

                $table->string('name');
                $table->string('description');

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
            Schema::dropIfExists('chargecategories');
        }
    };
