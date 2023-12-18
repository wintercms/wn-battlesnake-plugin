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
        Schema::create('winter_battlesnake_snakes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('squad')->nullable();
            $table->text('customizations')->nullable();
        });

        Schema::create('winter_battlesnake_snake_turns', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('turn_id')->unsigned();
            $table->string('snake_id');
            $table->string('move')->nullable();
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
        Schema::dropIfExists('winter_battlesnake_snakes');
    }
};
