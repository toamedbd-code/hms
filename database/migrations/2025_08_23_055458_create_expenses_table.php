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
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('expense_header_id');
                $table->foreign('expense_header_id')->references('id')->on('expenseheads')->onDelete('cascade');

                $table->string('bill_number')->unique()->nullable();
                $table->string('case_id')->unique()->nullable();

                $table->string('name');
                $table->string('document')->nullable();
                $table->text('description')->nullable();
                $table->decimal('amount', 15, 2);

                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->on('admins')->references('id')->onDelete('cascade');

                $table->unsignedBigInteger('updated_by')->nullable();
                $table->foreign('updated_by')->on('admins')->references('id')->onDelete('cascade');


                $table->date('date')->default(now());
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
            Schema::dropIfExists('expenses');
        }
    };
