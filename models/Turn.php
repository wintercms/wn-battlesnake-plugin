<?php

namespace Winter\Battlesnake\Models;

use Cms\Classes\MediaLibrary;
use Model;
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
        $this->board_object->draw(storage_path('app/media/battlesnake/' . $this->game_id . '/turn-' . $this->turn . '.png'));
    }

    public function getBoardImageAttribute(): string
    {
        return 'battlesnake/' . $this->game_id . '/turn-' . $this->turn . '.png';
    }

    public function getBoardStringAttribute(): string
    {
        return $this->board_object->toString();
    }

    public function replay(): array
    {
        return Snake::getSnake('snake', 'pass', false)->move($this->request);
    }
}
