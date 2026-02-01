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
            Schema::create('tests', function (Blueprint $table) {
                $table->id();
                $table->string('category_type');
                $table->string('test_name');
                $table->string('test_short_name')->nullable();
                $table->string('test_type')->nullable();
                $table->unsignedBigInteger('test_category_id');
                $table->string('test_sub_category_id')->nullable();
                $table->string('method')->nullable();
                $table->integer('report_days')->nullable();
                $table->unsignedBigInteger('charge_category_id')->nullable();
                $table->string('charge_name')->nullable();
                $table->string('tax')->nullable();
                $table->decimal('standard_charge', 11, 2)->nullable();
                $table->decimal('amount', 11, 2)->nullable();
                $table->json('test_parameters')->nullable();

                $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
                $table->softDeletes();
                $table->timestamps();

                // $table->foreign('pathology_category_id')->references('id')->on('pathology_categories');
                // $table->foreign('charge_category_id')->references('id')->on('charge_categories');
            });
        }

        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            Schema::dropIfExists('tests');
        }
    };
