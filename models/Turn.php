<?php

namespace Winter\Battlesnake\Models;

use Cms\Classes\MediaLibrary;
use Winter\Storm\Database\Model;
use Winter\Battlesnake\Classes\Snake;
use Winter\Battlesnake\Objects\Board;

/**
 * Turn Model
 */
class Turn extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_battlesnake_turns';

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'game_id',
        'turn',
        'board',
        'request',
        'move',
    ];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = [
        'board',
        'request',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'game' => [
            GameLog::class,
            'key' => 'game_id',
        ],
    ];

    public function getBoardObjectAttribute(): Board
    {
        return new Board($this->board ?? []);
    }

    public function afterCreate()
    {
        $this->board_object->draw(storage_path('app/media/' . $this->board_image));
    }

    protected function getPaddedTurnAttribute(): string
    {
        return str_pad($this->turn, 4, 0, STR_PAD_LEFT);
    }

    public function getBoardImageAttribute(): string
    {
        return 'battlesnake/' . $this->game_id . '/turn-' . $this->padded_turn . '.png';
    }

    public function getBoardStringAttribute(): string
    {
        return $this->board_object->toString();
    }

    public function replay(): array
    {
        return SnakeTemplate::findByCredentials(
            'snake',
            'password',
        )->snake($this->request, ['logTurns' => false])->move();
    }
}
