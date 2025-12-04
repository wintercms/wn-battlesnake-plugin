<?php

namespace Winter\Battlesnake\Classes;

/**
 * Static helper class for coordinate calculations.
 *
 * All methods use normalized coordinates (Y=0 at top, grid indexing).
 * In this system: "up" = y-1, "down" = y+1
 */
class CoordinateHelper
{
    /**
     * Get adjacent cells for a position.
     *
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return array ['up' => [x, y], 'down' => [x, y], 'left' => [x, y], 'right' => [x, y]]
     */
    public static function getNeighbors(int $x, int $y): array
    {
        return [
            'up' => [$x, $y - 1],
            'left' => [$x - 1, $y],
            'right' => [$x + 1, $y],
            'down' => [$x, $y + 1],
        ];
    }

    /**
     * Apply a direction to get new coordinates.
     *
     * @param int $x Starting X coordinate
     * @param int $y Starting Y coordinate
     * @param string $direction One of: up, down, left, right
     * @return array [x, y] - returns original position if direction is invalid
     */
    public static function applyDirection(int $x, int $y, string $direction): array
    {
        return self::getNeighbors($x, $y)[$direction] ?? [$x, $y];
    }

    /**
     * Get the direction between two adjacent positions.
     *
     * @param array $from Position with 'x' and 'y' keys
     * @param array $to Position with 'x' and 'y' keys
     * @return string|null Direction name, or null if not adjacent
     */
    public static function getDirectionBetween(array $from, array $to): ?string
    {
        $dx = $to['x'] - $from['x'];
        $dy = $to['y'] - $from['y'];

        return match (true) {
            $dx === 0 && $dy === -1 => 'up',
            $dx === 0 && $dy === 1 => 'down',
            $dx === -1 && $dy === 0 => 'left',
            $dx === 1 && $dy === 0 => 'right',
            default => null,
        };
    }

    /**
     * Calculate squared Euclidean distance between two positions.
     * Uses squared distance to avoid sqrt() for performance.
     *
     * @param array $a Position with 'x' and 'y' keys
     * @param array $b Position with 'x' and 'y' keys
     * @return int Squared distance
     */
    public static function getDistanceSquared(array $a, array $b): int
    {
        $dx = $a['x'] - $b['x'];
        $dy = $a['y'] - $b['y'];
        return $dx * $dx + $dy * $dy;
    }

    /**
     * Normalize a Y coordinate from Battlesnake API format to grid format.
     * API: Y=0 at bottom (Cartesian)
     * Grid: Y=0 at top (array indexing)
     *
     * @param int $y Raw API Y coordinate
     * @param int $boardHeight Board height
     * @return int Normalized Y coordinate
     */
    public static function normalizeY(int $y, int $boardHeight): int
    {
        return $boardHeight - $y - 1;
    }

    /**
     * Get adjacent cells in raw Battlesnake API coordinates (Y=0 at bottom).
     * Only use this when working with raw API request data.
     *
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return array ['up' => [x, y], 'down' => [x, y], 'left' => [x, y], 'right' => [x, y]]
     */
    public static function getNeighborsRaw(int $x, int $y): array
    {
        return [
            'up' => [$x, $y + 1],
            'left' => [$x - 1, $y],
            'right' => [$x + 1, $y],
            'down' => [$x, $y - 1],
        ];
    }

    /**
     * Apply a direction to get new coordinates in raw API format (Y=0 at bottom).
     * Only use this when working with raw API request data.
     *
     * @param int $x Starting X coordinate
     * @param int $y Starting Y coordinate
     * @param string $direction One of: up, down, left, right
     * @return array [x, y] - returns original position if direction is invalid
     */
    public static function applyDirectionRaw(int $x, int $y, string $direction): array
    {
        return self::getNeighborsRaw($x, $y)[$direction] ?? [$x, $y];
    }
}
