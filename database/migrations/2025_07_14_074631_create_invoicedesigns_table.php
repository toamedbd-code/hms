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
            Schema::create('invoicedesigns', function (Blueprint $table) {
                $table->id();
                $table->text('footer_content')->nullable();
                $table->string('header_photo_path')->nullable();
                $table->string('footer_photo_path')->nullable();
                $table->enum('module', ['opd', 'ipd', 'pathology', 'radiology', 'pharmacy', 'appointment', 'billing'])->nullable();

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
            Schema::dropIfExists('invoicedesigns');
        }
    };
