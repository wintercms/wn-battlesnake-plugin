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
        Schema::create('winter_battlesnake_games', function (Blueprint $table) {
            $table->string('game_id')->primary();
            $table->text('ruleset')->nullable();
            $table->string('map');
            $table->integer('timeout')->unsigned();
            $table->string('source');
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
        Schema::dropIfExists('winter_battlesnake_games');
    }
};
