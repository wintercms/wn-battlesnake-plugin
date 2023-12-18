<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('winter_battlesnake_turns', function (Blueprint $table) {
            $table->increments('id');
            $table->string('game_id')->index();
            $table->integer('turn')->unsigned();
            $table->text('board')->nullable();
            $table->text('request')->nullable();
            $table->string('move');
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
        Schema::dropIfExists('winter_battlesnake_turns');
    }
};
