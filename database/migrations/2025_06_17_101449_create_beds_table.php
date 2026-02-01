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
            Schema::create('beds', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('bed_type_id');
                $table->foreign('bed_type_id')->on('bedtypes')->references('id')->onDelete('cascade');
                
                $table->unsignedBigInteger('bed_group_id');
                $table->foreign('bed_group_id')->on('bedgroups')->references('id')->onDelete('cascade');

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
            Schema::dropIfExists('beds');
        }
    };
