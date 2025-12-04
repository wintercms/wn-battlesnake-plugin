<?php

namespace Winter\Battlesnake\Models;

use Winter\Storm\Database\Model;

/**
 * GameLog Model
 */
class GameLog extends Model
{
    public $incrementing = false;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_battlesnake_games';

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'game_id',
        'ruleset',
        'map',
        'timeout',
        'source',
    ];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = [
        'ruleset',
    ];

    /**
     * @var array Relations
     */
    public $hasMany = [
        'turns' => [
            Turn::class,
            'key' => 'game_id',
            'otherKey' => 'game_id',
        ],
        'participants' => [
            GameParticipant::class,
            'key' => 'game_id',
            'otherKey' => 'game_id',
        ],
    ];
}
