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
            Schema::create('charges', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('charge_type_id');
                $table->foreign('charge_type_id')->references('id')->on('chargetypes')->onDelete('cascade');

                $table->unsignedBigInteger('charge_category_id');
                $table->foreign('charge_category_id')->references('id')->on('chargecategories')->onDelete('cascade');

                $table->unsignedBigInteger('unit_type_id');
                $table->foreign('unit_type_id')->references('id')->on('chargeunittypes')->onDelete('cascade');
                
                $table->unsignedBigInteger('tax_category_id');
                $table->foreign('tax_category_id')->references('id')->on('chargetaxcategories')->onDelete('cascade');

                $table->decimal('tax', 8, 2)->nullable()->comment('Tax percentage');
                $table->decimal('standard_charge', 10, 2)->nullable()->comment('Standard charge in Tk');
                $table->text('description')->nullable();

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
            Schema::dropIfExists('charges');
        }
    };
