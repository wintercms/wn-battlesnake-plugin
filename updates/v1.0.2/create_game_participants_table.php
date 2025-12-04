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
        Schema::create('winter_battlesnake_game_participants', function (Blueprint $table) {
            $table->increments('id');
            $table->string('game_id')->index();
            $table->string('snake_id')->index();
            $table->string('snake_name');
            $table->integer('snake_template_id')->unsigned()->nullable()->index();
            $table->string('result')->nullable();
            $table->string('death_cause')->nullable();
            $table->integer('turns_survived')->unsigned()->nullable();
            $table->integer('final_length')->unsigned()->nullable();
            $table->integer('final_health')->unsigned()->nullable();
            $table->integer('kills')->unsigned()->default(0);
            $table->integer('food_eaten')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'snake_id'], 'game_snake_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('winter_battlesnake_game_participants');
    }
};
