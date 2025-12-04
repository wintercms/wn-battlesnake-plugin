<?php

namespace Winter\Battlesnake\Models;

use Winter\Storm\Database\Model;

/**
 * GameParticipant Model
 * Tracks a snake's participation in a game with stats
 */
class GameParticipant extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_battlesnake_game_participants';

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'game_id',
        'snake_id',
        'snake_name',
        'snake_template_id',
        'result',
        'death_cause',
        'turns_survived',
        'final_length',
        'final_health',
        'kills',
        'food_eaten',
    ];

    /**
     * Get display name - uses template name if available, otherwise snake_name
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->snakeTemplate) {
            return $this->snakeTemplate->name;
        }
        return $this->snake_name ?? 'Unknown';
    }

    /**
     * Check if this is our snake (has a template) vs external opponent
     */
    public function getIsOurSnakeAttribute(): bool
    {
        return !is_null($this->snake_template_id);
    }

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'game' => [
            GameLog::class,
            'key' => 'game_id',
            'otherKey' => 'game_id',
        ],
        'snakeTemplate' => [
            SnakeTemplate::class,
            'key' => 'snake_template_id',
        ],
    ];
}
