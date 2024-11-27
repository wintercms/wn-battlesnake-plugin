<?php

namespace Winter\Battlesnake\Classes;

use Illuminate\Foundation\Inspiring;
use Winter\Battlesnake\Models\GameLog;
use Winter\Battlesnake\Models\SnakeTemplate;
use Winter\Battlesnake\Models\Turn;
use Winter\Battlesnake\Objects\Board;
use Winter\Battlesnake\Objects\Battlesnake;
use Winter\Battlesnake\Objects\Game;
use Winter\Storm\Support\Str;

class Snake
{
    public Game $game;
    public Board $board;
    public Battlesnake $you;
    public int $turn;
    public array $options = [
        'logTurns' => true,
    ];
    public array $info = [];
    public array $data = [];

    public function __construct(array $state, array $options = [])
    {
        $this->parseState($state);
        $this->options = array_merge($this->options, $options);
    }

    protected function parseState(array $data): void
    {
        if (empty($data)) {
            return;
        }

        $this->game = new Game($data['game'] ?? []);
        $this->board = new Board($data['board'] ?? []);
        $this->you = new Battlesnake($data['you'] ?? [], $this->board);
        $this->turn = $data['turn'] ?? 0;
        $this->data = $data;
    }

    protected function logTurn(array $out): void
    {
        if (!$this->options['logTurns']) {
            return;
        }

        // Debug output
        $this->board->draw(storage_path('bs/' . $this->game->id . '/' . $this->turn . '.png'));
        file_put_contents(storage_path('bs/' . $this->game->id . '/' . $this->turn . '.txt'), print_r($this->board->toString(), true));

        Turn::create([
            'game_id' => $this->game->id,
            'turn' => $this->turn,
            'board' => $this->data['board'],
            'request' => $this->data,
            'move' => $out['move'],
        ]);
    }

    /**
     * @see https://docs.battlesnake.com/api/requests/info
     */
    public function info(): array
    {
        return $this->info ?? [
            "apiversion" => "1",
            "author" => "luketowers",
            "color" => "#3498db",
            "head" => "snow-worm",
            "tail" => "flake",
            "version" => "0.0.1-beta",
        ];
    }

    /**
     * @see https://docs.battlesnake.com/api/requests/start
     */
    public function start(): void
    {
        $now = now()->toDateTimeString();
        GameLog::insert([
            'game_id' => $this->game->id,
            'ruleset' => json_encode($data['game']['ruleset']),
            'map' => $this->game->id,
            'timeout' => $this->game->timeout,
            'source' => $this->game->source,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function getXY(): array
    {
        return $this->you->head;
    }

    /**
     * Get the food on the board by distance
     * @TODO: How does this handle food of equal distance from the current location
     * @return array [distance => index]
     */
    public function getFoodByDistance(): array
    {
        $foodDistance = [];
        foreach ($this->board->food as $index => $food) {
            $foodDistance[$this->getDistance($food, ['x' => $this->getXY()['x'], 'y' => $this->getXY()['y']])] = $index;
        }

        // Sort by distance
        ksort($foodDistance);

        return $foodDistance;
    }

    /**
     * @see https://docs.battlesnake.com/api/requests/move
     */
    public function move(): array
    {
        // Find where we are
        $x = $this->you->head['x'];
        $y = $this->you->head['y'];
        $move = $this->getNextMove($x, $y);

        $response = [
            'move' => $move,
            'shout' => Str::limit(Inspiring::quotes()->random(), 253),
        ];

        $this->logTurn($response);

        if ($response['move'] === 'death') {
            $response['move'] = 'up';
        }

        return $response;
    }

    public function getNextMove($x, $y): string
    {
        // Target the closest food
        $food = $this->getFoodByDistance();
        $target = $this->board->food[array_shift($food)];

        // Pick neighbour cells
        $neighbors = [
                                'up' => [$x, $y - 1],
            'left' => [$x - 1, $y], /*  [$x, $y], */  'right' => [$x + 1, $y],
                                'down' => [$x, $y + 1]
        ];

        // Get the game grid
        $grid = $this->board->toArray();

        // Validate next move to 1 level
        $moves = [];
        foreach ($neighbors as $index => $neighbor) {
            // Filter invalid moves
            if (
                !isset($grid[$neighbor[1]][$neighbor[0]])
                || in_array($grid[$neighbor[1]][$neighbor[0]], Board::DANGER_CHARS)
            ) {
                continue;
            }

            // Index next move by distance to target
            $moves[$this->getDistance(['x' => $neighbor[0], 'y' => $neighbor[1]], $target)] = $index;
        }

        // Selected the shortest distance
        ksort($moves);
        $move = array_shift($moves);

        if (is_null($move)) {
            $move = 'death';
        }

        return $move;
    }

    /**
     * Probably move this, creates an int representation of distance between 2 vectors
     *
     * @param array $target
     * @param array $position
     * @return int
     */
    protected function getDistance(array $target, array $position): int
    {
        $diffX = $target['x'] - $position['x'];
        $diffY = $target['y'] - $position['y'];

        return $diffX * $diffX + $diffY * $diffY;
    }

    /**
     * @see https://docs.battlesnake.com/api/requests/end
     */
    public function end(): void
    {
        $this->logTurn(['move' => 'end']);
    }
}
