<?php

namespace Winter\Battlesnake\Classes;

use Winter\Battlesnake\Models\GameLog;
use Winter\Battlesnake\Models\Turn;

/**
 * Transforms Winter Battlesnake data to the format expected by the board viewer.
 *
 * @see https://github.com/BattlesnakeOfficial/board
 */
class BoardDataTransformer
{
    /**
     * Convert all turns for a game into the Frame format expected by the board.
     */
    public static function gameToFrames(GameLog $game): array
    {
        $turns = $game->turns()
            ->orderBy('turn', 'asc')
            ->get()
            ->unique('turn'); // One frame per turn number

        $frames = $turns->map(fn($turn) => self::turnToFrame($turn))->values()->toArray();

        if (!empty($frames)) {
            $frames[count($frames) - 1]['isFinalFrame'] = true;
        }

        return $frames;
    }

    /**
     * Convert a single Turn to the Frame format.
     */
    protected static function turnToFrame(Turn $turn): array
    {
        $request = $turn->request;
        $board = $request['board'] ?? [];

        return [
            'turn' => $turn->turn,
            'width' => $board['width'] ?? 11,
            'height' => $board['height'] ?? 11,
            'food' => $board['food'] ?? [],
            'hazards' => $board['hazards'] ?? [],
            'snakes' => self::transformSnakes($board['snakes'] ?? []),
            'isFinalFrame' => false,
        ];
    }

    /**
     * Transform snake data to the format expected by the board.
     */
    protected static function transformSnakes(array $snakes): array
    {
        return array_map(fn($snake) => [
            'id' => $snake['id'],
            'name' => $snake['name'],
            'color' => $snake['customizations']['color'] ?? '#888888',
            'head' => $snake['customizations']['head'] ?? 'default',
            'tail' => $snake['customizations']['tail'] ?? 'default',
            'health' => (int) $snake['health'],
            'body' => $snake['body'],
            'length' => $snake['length'],
            'latency' => (string) ($snake['latency'] ?? '0'),
            'isEliminated' => false,
            'elimination' => null,
        ], $snakes);
    }

    /**
     * Get game metadata in the format expected by the board.
     */
    public static function getGameMetadata(GameLog $game): array
    {
        return [
            'ID' => $game->game_id,
            'Ruleset' => $game->ruleset ?? ['name' => 'standard'],
            'Map' => $game->map ?? 'standard',
            'Timeout' => $game->timeout ?? 500,
            'Source' => 'local',
        ];
    }
}
