<?php

namespace Winter\Battlesnake\Models;

use Winter\Storm\Database\Model;
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
        'snake_id',
        'snake_template_id',
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
        'snakeTemplate' => [
            SnakeTemplate::class,
            'key' => 'snake_template_id',
        ],
    ];

    /**
     * Get the participant record for this turn's snake
     */
    public function getParticipantAttribute(): ?GameParticipant
    {
        if (!$this->snake_id) {
            return null;
        }

        return GameParticipant::where('game_id', $this->game_id)
            ->where('snake_id', $this->snake_id)
            ->first();
    }

    /**
     * Get the snake name for display
     */
    public function getSnakeNameAttribute(): string
    {
        return $this->participant?->snake_name ?? 'Unknown';
    }

    public function getBoardObjectAttribute(): Board
    {
        $board = new Board($this->board ?? []);
        $board->youId = $this->snake_id;
        return $board;
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
        $snakePart = $this->snake_template_id ? '/snake-' . $this->snake_template_id : '';
        return 'battlesnake/' . $this->game_id . $snakePart . '/turn-' . $this->padded_turn . '.png';
    }

    public function getBoardStringAttribute(): string
    {
        return $this->board_object->toString();
    }

    /**
     * Replay this turn with full debug trace
     *
     * @param array $strategyOverrides Optional strategy value overrides for testing
     * @return array Contains move, shout, originalMove, debug trace, and board string
     */
    public function replay(array $strategyOverrides = []): array
    {
        // Use the snake template that was actually used for this turn
        $template = $this->snakeTemplate;

        if (!$template) {
            // Fallback - try to find by credentials if no template linked
            $template = SnakeTemplate::findByCredentials('snake', 'password');
        }

        if (!$template) {
            return [
                'error' => 'No snake template found for replay',
                'originalMove' => $this->move,
                'board' => $this->board_string,
            ];
        }

        $snake = $template->snake($this->request, ['logTurns' => false]);

        // Apply strategy overrides if provided
        if (!empty($strategyOverrides)) {
            foreach ($strategyOverrides as $key => $value) {
                if (array_key_exists($key, $snake->strategy)) {
                    // Handle type conversion
                    if (is_bool($snake->strategy[$key])) {
                        $snake->strategy[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    } elseif (is_int($snake->strategy[$key])) {
                        $snake->strategy[$key] = (int) $value;
                    } elseif (is_float($snake->strategy[$key])) {
                        $snake->strategy[$key] = (float) $value;
                    } else {
                        $snake->strategy[$key] = $value;
                    }
                }
            }
        }

        $result = $snake->move();

        return [
            'move' => $result['move'],
            'shout' => $result['shout'] ?? '',
            'originalMove' => $this->move,
            'debug' => $snake->debugTrace,
            'board' => $this->board_string,
            'turn' => $this->turn,
            'snakeName' => $this->snake_name,
        ];
    }
}
