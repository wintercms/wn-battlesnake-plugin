<?php

namespace Winter\Battlesnake\Classes;

use Illuminate\Support\Facades\Cache;

/**
 * Manages live game state for real-time board viewing via SSE.
 */
class LiveGame
{
    protected const CACHE_PREFIX = 'battlesnake:live:';
    protected const GAME_TTL = 3600; // 1 hour
    protected const FRAME_TTL = 300; // 5 minutes

    /**
     * Start tracking a new live game.
     */
    public static function start(string $gameId, array $gameInfo): void
    {
        $key = self::CACHE_PREFIX . $gameId;

        Cache::put($key, [
            'game_id' => $gameId,
            'info' => $gameInfo,
            'frame_count' => 0,
            'status' => 'playing',
            'started_at' => now()->timestamp,
        ], self::GAME_TTL);
    }

    /**
     * Add a frame to the live game.
     */
    public static function addFrame(string $gameId, int $turn, array $frameData): void
    {
        $gameKey = self::CACHE_PREFIX . $gameId;
        $frameKey = self::CACHE_PREFIX . $gameId . ':frame:' . $turn;

        // Store the frame
        Cache::put($frameKey, $frameData, self::FRAME_TTL);

        // Update game metadata
        $game = Cache::get($gameKey);
        if ($game) {
            $game['frame_count'] = max($game['frame_count'], $turn + 1);
            $game['last_turn'] = $turn;
            $game['updated_at'] = now()->timestamp;
            Cache::put($gameKey, $game, self::GAME_TTL);
        }
    }

    /**
     * Mark a game as ended.
     */
    public static function end(string $gameId): void
    {
        $key = self::CACHE_PREFIX . $gameId;
        $game = Cache::get($key);

        if ($game) {
            $game['status'] = 'ended';
            $game['ended_at'] = now()->timestamp;
            Cache::put($key, $game, self::FRAME_TTL); // Shorter TTL after game ends
        }
    }

    /**
     * Get game metadata.
     */
    public static function getGame(string $gameId): ?array
    {
        return Cache::get(self::CACHE_PREFIX . $gameId);
    }

    /**
     * Get a specific frame.
     */
    public static function getFrame(string $gameId, int $turn): ?array
    {
        return Cache::get(self::CACHE_PREFIX . $gameId . ':frame:' . $turn);
    }

    /**
     * Get all frames for a game.
     */
    public static function getAllFrames(string $gameId): array
    {
        $game = self::getGame($gameId);
        if (!$game) {
            return [];
        }

        $frames = [];
        for ($i = 0; $i < $game['frame_count']; $i++) {
            $frame = self::getFrame($gameId, $i);
            if ($frame) {
                $frames[] = $frame;
            }
        }

        return $frames;
    }

    /**
     * Get frames starting from a specific turn (for SSE polling).
     */
    public static function getFramesFrom(string $gameId, int $fromTurn): array
    {
        $game = self::getGame($gameId);
        if (!$game) {
            return ['frames' => [], 'status' => null];
        }

        $frames = [];
        for ($i = $fromTurn; $i < $game['frame_count']; $i++) {
            $frame = self::getFrame($gameId, $i);
            if ($frame) {
                $frames[] = $frame;
            }
        }

        return [
            'frames' => $frames,
            'status' => $game['status'],
            'frame_count' => $game['frame_count'],
        ];
    }

    /**
     * Check if a game is currently live.
     */
    public static function isLive(string $gameId): bool
    {
        $game = self::getGame($gameId);
        return $game && $game['status'] === 'playing';
    }

    /**
     * Build frame data from a move request (the format sent to snakes).
     */
    public static function buildFrameFromRequest(array $request): array
    {
        $board = $request['board'] ?? [];

        return [
            'turn' => $request['turn'] ?? 0,
            'width' => $board['width'] ?? 11,
            'height' => $board['height'] ?? 11,
            'food' => $board['food'] ?? [],
            'hazards' => $board['hazards'] ?? [],
            'snakes' => array_map(function ($snake) {
                return [
                    'id' => $snake['id'],
                    'name' => $snake['name'],
                    'health' => $snake['health'],
                    'body' => $snake['body'],
                    'head' => $snake['head'] ?? ($snake['body'][0] ?? ['x' => 0, 'y' => 0]),
                    'length' => $snake['length'] ?? count($snake['body']),
                    'latency' => (string) ($snake['latency'] ?? '0'),
                    'color' => $snake['customizations']['color'] ?? '#888888',
                    'headType' => $snake['customizations']['head'] ?? 'default',
                    'tailType' => $snake['customizations']['tail'] ?? 'default',
                    'isEliminated' => false,
                    'elimination' => null,
                ];
            }, $board['snakes'] ?? []),
            'isFinalFrame' => false,
        ];
    }

    /**
     * Build game info from a start request.
     */
    public static function buildGameInfoFromRequest(array $request): array
    {
        $game = $request['game'] ?? [];
        $board = $request['board'] ?? [];

        return [
            'ID' => $game['id'] ?? '',
            'Width' => $board['width'] ?? 11,
            'Height' => $board['height'] ?? 11,
            'Ruleset' => $game['ruleset'] ?? ['name' => 'standard'],
            'Map' => $game['map'] ?? 'standard',
            'Timeout' => $game['timeout'] ?? 500,
            'Source' => $game['source'] ?? 'local',
        ];
    }
}
