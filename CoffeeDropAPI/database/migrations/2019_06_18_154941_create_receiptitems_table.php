<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReceiptitemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('receiptitems', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('coffeePodType', 15);
            $table->integer('tier1Total');
            $table->integer('tier2Total');
            $table->integer('tier3Total');
            $table->integer('tier1Count');
            $table->integer('tier2Count');
            $table->integer('tier3Count');
            $table->integer('total');
            $table->integer('count');
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
        Schema::dropIfExists('receiptitems');
    }
}
