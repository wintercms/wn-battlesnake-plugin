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
        Schema::table('winter_battlesnake_turns', function (Blueprint $table) {
            $table->string('snake_id')->nullable()->after('game_id');
            $table->integer('snake_template_id')->unsigned()->nullable()->after('snake_id');
            $table->index('snake_id');
            $table->index('snake_template_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('winter_battlesnake_turns', function (Blueprint $table) {
            if (Schema::hasColumn('winter_battlesnake_turns', 'snake_id')) {
                $table->dropIndex(['snake_id']);
                $table->dropColumn('snake_id');
            }
            if (Schema::hasColumn('winter_battlesnake_turns', 'snake_template_id')) {
                $table->dropIndex(['snake_template_id']);
                $table->dropColumn('snake_template_id');
            }
        });
    }
};
