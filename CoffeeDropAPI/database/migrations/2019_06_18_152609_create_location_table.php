<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLocationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->string('postcode', 8);
            $table->primary('postcode');
            $table->unique('postcode');
            $table->string('open_Monday', 5)->nullable();
            $table->string('open_Tuesday', 5)->nullable();
            $table->string('open_Wednesday', 5)->nullable();
            $table->string('open_Thursday', 5)->nullable();
            $table->string('open_Friday', 5)->nullable();
            $table->string('open_Saturday', 5)->nullable();
            $table->string('open_Sunday', 5)->nullable();
            $table->string('closed_Monday', 5)->nullable();
            $table->string('closed_Tuesday', 5)->nullable();
            $table->string('closed_Wednesday', 5)->nullable();
            $table->string('closed_Thursday', 5)->nullable();
            $table->string('closed_Friday', 5)->nullable();
            $table->string('closed_Saturday', 5)->nullable();
            $table->string('closed_Sunday', 5)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('locations');
    }
}
