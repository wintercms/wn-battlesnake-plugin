<?php

namespace Winter\Battlesnake\Classes;

class AsciiBoard
{
    public const SYMBOL_EMPTY = '.';
    public const SYMBOL_FOOD = 'F';
    public const SYMBOL_YOU_HEAD = 'Y';
    public const SYMBOL_YOU_BODY = '*';
    public const SYMBOL_HAZARD = 'H';

    public function convertToGameState(array $input): array
    {
        $gameState = [
            'board' => [
                'height' => count($input),
                'width' => count($input[0]),
                'food' => [],
                'hazards' => [],
                'snakes' => [],
            ],
            'you' => [
                'body' => [],
            ],
        ];

        $enemySnakes = [];
        foreach ($input as $y => $row) {
            foreach ($row as $x => $cell) {
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
                        array_unshift($gameState['you']['body'], $gameState['you']['head']);
                        break;
                    case static::SYMBOL_YOU_BODY:
                        $gameState['you']['body'][] = ['x' => $x, 'y' => $y];
                        break;
                    default:
                        // Enemy snake = A111111a
                        if (ctype_upper($cell)) {
                            // Enemy snake head
                            $index = ord($cell) - ord('A') + 1; // Convert letter to number
                            if (isset($enemySnakes[$index])) {
                                $head = ['x' => $x, 'y' => $y];
                                array_unshift($enemySnakes['body'][$index], $head);
                                $enemySnakes[$index]['head'] = $head;
                            } else {
                                $enemySnakes[$index]['body'] = [['x' => $x, 'y' => $y]];
                            }
                        } elseif (ctype_lower($cell)) {
                            // Enemy snake tail
                            $index = ord($cell) - ord('a') + 1; // Convert lowercase letter to number
                            if (!isset($enemySnakes[$index])) {
                                $enemySnakes[$index] = [];
                            }
                            $enemySnakes[$index][] = ['x' => $x, 'y' => $y];
                        } elseif (is_numeric($cell)) {
                            // Enemy snake body
                            $index = $cell;
                            if (!isset($enemySnakes[$index])) {
                                $enemySnakes[$index]['body'] = [];
                            }
                            $enemySnakes[$index]['body'][] = ['x' => $x, 'y' => $y];
                        }
                        break;
                }
            }
        }

        $gameState['board']['snakes'][] = [
            'id' => 'you',
            'body' => $gameState['you']['body'],
        ];

        foreach ($enemySnakes as $index => $body) {
            $gameState['board']['snakes'][] = [
                'id' => $index,
                'body' => $body,
            ];
        }

        return $gameState;
    }
}