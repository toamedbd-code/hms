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
            Schema::create('medicineinventories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('medicine_category_id');
                $table->string('medicine_name');
                $table->decimal('medicine_unit_purchase_price', 11, 2);
                $table->decimal('medicine_unit_selling_price', 11, 2);
                $table->decimal('medicine_total_purchase_price', 11, 2);
                $table->decimal('medicine_total_selling_price', 11, 2);
                $table->integer('medicine_quantity');
                $table->text('remarks')->nullable();

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
            Schema::dropIfExists('medicineinventories');
        }
    };
