<?php

namespace Winter\Battlesnake\Classes;

/**
 * AsciiBoard - Convert ASCII board representations to Battlesnake game state
 *
 * Symbols:
 *   . = empty
 *   F = food
 *   H = hazard
 *   Y = your snake head
 *   y = your snake body (use multiple y's, parsed left-to-right, top-to-bottom)
 *   A = enemy snake A head, a = enemy snake A body
 *   B = enemy snake B head, b = enemy snake B body
 *   etc.
 *
 * Example:
 *   . . . . .
 *   . A a a .
 *   . . . . .
 *   . Y y y .
 *   . . F . .
 */
class AsciiBoard
{
    public const SYMBOL_EMPTY = '.';
    public const SYMBOL_FOOD = 'F';
    public const SYMBOL_HAZARD = 'H';
    public const SYMBOL_YOU_HEAD = 'Y';
    public const SYMBOL_YOU_BODY = 'y';

    /**
     * Parse an ASCII string into a game state array
     */
    public static function parse(string $ascii): array
    {
        $lines = array_filter(array_map('trim', explode("\n", trim($ascii))));
        $grid = [];
        foreach ($lines as $line) {
            $grid[] = preg_split('/\s+/', $line);
        }
        return (new static)->convertToGameState($grid);
    }

    /**
     * Convert a 2D array of symbols into a Battlesnake game state
     *
     * Note: Battlesnake uses Y=0 at BOTTOM (Cartesian), but ASCII boards
     * are read with Y=0 at TOP. We flip Y coordinates during parsing.
     */
    public function convertToGameState(array $input): array
    {
        $height = count($input);
        $width = count($input[0] ?? []);

        $gameState = [
            'game' => [
                'id' => 'test-game',
                'ruleset' => ['name' => 'standard'],
                'timeout' => 500,
                'source' => 'test',
            ],
            'turn' => 0,
            'board' => [
                'height' => $height,
                'width' => $width,
                'food' => [],
                'hazards' => [],
                'snakes' => [],
            ],
            'you' => [
                'id' => 'you',
                'name' => 'You',
                'health' => 100,
                'body' => [],
                'head' => null,
                'length' => 0,
                'latency' => '0',
                'shout' => '',
            ],
        ];

        $enemySnakes = [];

        // First pass: collect all positions
        // Flip Y coordinate: Battlesnake Y=0 is at bottom, ASCII Y=0 is at top
        foreach ($input as $asciiY => $row) {
            $y = ($height - 1) - $asciiY; // Flip Y coordinate
            foreach ($row as $x => $cell) {
                $cell = trim($cell);
                if (empty($cell)) {
                    continue;
                }

                switch ($cell) {
                    case static::SYMBOL_EMPTY:
                        break;

                    case static::SYMBOL_FOOD:
                        $gameState['board']['food'][] = ['x' => $x, 'y' => $y];
                        break;

                    case static::SYMBOL_HAZARD:
                        $gameState['board']['hazards'][] = ['x' => $x, 'y' => $y];
                        break;

                    case static::SYMBOL_YOU_HEAD:
                        $gameState['you']['head'] = ['x' => $x, 'y' => $y];
                        array_unshift($gameState['you']['body'], ['x' => $x, 'y' => $y]);
                        break;

                    case static::SYMBOL_YOU_BODY:
                        $gameState['you']['body'][] = ['x' => $x, 'y' => $y];
                        break;

                    default:
                        // Enemy snakes: uppercase = head, lowercase = body
                        if (ctype_upper($cell)) {
                            $key = strtolower($cell);
                            if (!isset($enemySnakes[$key])) {
                                $enemySnakes[$key] = ['head' => null, 'body' => []];
                            }
                            $enemySnakes[$key]['head'] = ['x' => $x, 'y' => $y];
                            array_unshift($enemySnakes[$key]['body'], ['x' => $x, 'y' => $y]);
                        } elseif (ctype_lower($cell)) {
                            $key = $cell;
                            if (!isset($enemySnakes[$key])) {
                                $enemySnakes[$key] = ['head' => null, 'body' => []];
                            }
                            $enemySnakes[$key]['body'][] = ['x' => $x, 'y' => $y];
                        }
                        break;
                }
            }
        }

        // Finalize your snake
        $gameState['you']['length'] = count($gameState['you']['body']);

        // Add your snake to the board
        $gameState['board']['snakes'][] = [
            'id' => 'you',
            'name' => 'You',
            'health' => 100,
            'body' => $gameState['you']['body'],
            'head' => $gameState['you']['head'],
            'length' => $gameState['you']['length'],
            'latency' => '0',
            'shout' => '',
        ];

        // Add enemy snakes
        foreach ($enemySnakes as $key => $snake) {
            $id = 'enemy-' . $key;
            $gameState['board']['snakes'][] = [
                'id' => $id,
                'name' => 'Enemy ' . strtoupper($key),
                'health' => 100,
                'body' => $snake['body'],
                'head' => $snake['head'],
                'length' => count($snake['body']),
                'latency' => '0',
                'shout' => '',
            ];
        }

        return $gameState;
    }
}