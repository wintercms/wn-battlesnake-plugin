<?php

namespace Winter\Battlesnake\Classes\Snake;

use Winter\Battlesnake\Classes\CoordinateHelper;
use Winter\Battlesnake\Objects\Board;

/**
 * Space Analysis Algorithms
 *
 * Pure algorithms for analyzing board space using flood fill techniques.
 * These methods have no side effects and can be used independently.
 */
trait HasFloodFill
{
    /**
     * Get the amount of free space available from a given coordinate
     * Uses BFS to count all accessible cells.
     *
     * @param int $x Starting X coordinate
     * @param int $y Starting Y coordinate
     * @param array $grid The game board grid
     * @return int Number of accessible cells
     */
    public function floodFill($x, $y, $grid): int
    {
        $visited = [];
        $queue = [[$x, $y]];
        $area = 0;

        while (!empty($queue)) {
            [$currentX, $currentY] = array_shift($queue);
            $key = $currentX . ',' . $currentY;

            if (isset($visited[$key])) {
                continue;
            }

            $visited[$key] = true;

            // Check if position is within bounds and not a wall or snake
            if (
                isset($grid[$currentY][$currentX])
                && !in_array($grid[$currentY][$currentX], Board::DANGER_CHARS)
            ) {
                $area++;

                // Add neighbors to queue
                foreach (CoordinateHelper::getNeighbors($currentX, $currentY) as [$nx, $ny]) {
                    $nkey = $nx . ',' . $ny;
                    if (!isset($visited[$nkey])) {
                        $queue[] = [$nx, $ny];
                    }
                }
            }
        }

        return $area;
    }

    /**
     * Get the amount of free space available within a maximum depth (number of moves).
     * This measures "immediate openness" - how much space is accessible in the near term.
     * A corridor will have low immediate area (can only go straight), while open space
     * will have high immediate area (can expand in all directions).
     *
     * @param int $x Starting X coordinate
     * @param int $y Starting Y coordinate
     * @param array $grid The game board grid
     * @param int $maxDepth Maximum number of moves to explore
     * @return int Number of cells reachable within maxDepth moves
     */
    public function floodFillWithDepth(int $x, int $y, array $grid, int $maxDepth): int
    {
        $visited = [];
        $queue = [[$x, $y, 0]]; // x, y, depth
        $area = 0;

        while (!empty($queue)) {
            [$currentX, $currentY, $depth] = array_shift($queue);

            // Don't explore beyond max depth
            if ($depth > $maxDepth) {
                continue;
            }

            $key = $currentX . ',' . $currentY;
            if (isset($visited[$key])) {
                continue;
            }
            $visited[$key] = true;

            // Check if position is within bounds and not a wall or snake
            if (
                isset($grid[$currentY][$currentX])
                && !in_array($grid[$currentY][$currentX], Board::DANGER_CHARS)
            ) {
                $area++;

                // Add neighbors to queue with incremented depth
                foreach (CoordinateHelper::getNeighbors($currentX, $currentY) as [$nx, $ny]) {
                    $nkey = $nx . ',' . $ny;
                    if (!isset($visited[$nkey])) {
                        $queue[] = [$nx, $ny, $depth + 1];
                    }
                }
            }
        }

        return $area;
    }

    /**
     * Count how many distinct escape routes lead from a position to significant open space.
     * A good position has 2-3 escape routes (flexibility), a trap has 0-1.
     * This helps detect situations where one direction leads to a dead end.
     *
     * @param int $x Starting X coordinate
     * @param int $y Starting Y coordinate
     * @param array $grid The game board grid
     * @return int Number of escape routes with significant area
     */
    public function countEscapeRoutes(int $x, int $y, array $grid): int
    {
        $routes = 0;

        foreach (CoordinateHelper::getNeighbors($x, $y) as [$nx, $ny]) {
            // Check if this direction is passable
            if (!isset($grid[$ny][$nx]) || in_array($grid[$ny][$nx], Board::DANGER_CHARS)) {
                continue;
            }

            // Check if this direction leads to significant open space
            // "Significant" = at least our body length (room to maneuver)
            $area = $this->floodFill($nx, $ny, $grid);
            if ($area >= $this->you->length) {
                $routes++;
            }
        }

        return $routes;
    }

    /**
     * Flood fill that predicts tail movement - counts area that will be accessible
     * after tails move. Snakes lose their tail tip each turn unless they just ate.
     *
     * This helps recognize when following our own tail creates space, or when
     * an apparently blocked path will open up in a few turns.
     *
     * @param int $x Starting X coordinate
     * @param int $y Starting Y coordinate
     * @param array $grid The game board grid
     * @param int $turnsAhead How many turns to predict (1-4)
     * @return int Number of cells accessible accounting for predicted tail movement
     */
    public function floodFillWithTailPrediction(int $x, int $y, array $grid, int $turnsAhead): int
    {
        $modifiedGrid = $grid;

        // For each snake, mark tail positions that will become empty
        // turnsAhead=1 means the current tail tip will be gone
        // turnsAhead=2 means the last 2 tail segments will be gone (unless snake ate)
        foreach ($this->board->snakes as $snake) {
            // Only predict tail removal if snake didn't just eat (health < 100)
            // When a snake eats, its tail doesn't move that turn
            if ($snake->health < 100) {
                $bodyLength = count($snake->body);
                $tailsToRemove = min($turnsAhead, $bodyLength - 1); // Keep at least the head

                for ($i = 0; $i < $tailsToRemove; $i++) {
                    $tailIndex = $bodyLength - 1 - $i;
                    if (isset($snake->body[$tailIndex])) {
                        $tailPos = $snake->body[$tailIndex];
                        // Use normalized coordinates
                        $ty = $this->board->height - $tailPos['y'] - 1;
                        $tx = $tailPos['x'];
                        if (isset($modifiedGrid[$ty][$tx])) {
                            $modifiedGrid[$ty][$tx] = '_'; // Mark as will-be-empty
                        }
                    }
                }
            }
        }

        return $this->floodFill($x, $y, $modifiedGrid);
    }

    /**
     * Calculate how much we reduce opponents' accessible area by moving to a position.
     * Returns a positive value when our move traps opponents (reduces their space).
     *
     * This enables offensive play by rewarding moves that cut off opponents' escape routes.
     *
     * @param int $x Target X coordinate
     * @param int $y Target Y coordinate
     * @param array $grid The current game board grid
     * @return float Total area reduction score (positive = we're trapping them)
     */
    public function getOpponentAreaReduction(int $x, int $y, array $grid): float
    {
        $totalReduction = 0.0;

        foreach ($this->board->snakes as $snake) {
            // Don't calculate for ourselves
            if ($snake->id === $this->you->id) {
                continue;
            }

            // Get opponent's head position (normalized)
            $opponentHeadX = $snake->head['x'];
            $opponentHeadY = $this->board->height - $snake->head['y'] - 1;

            // Calculate opponent's current accessible area
            $currentArea = $this->floodFill($opponentHeadX, $opponentHeadY, $grid);

            // Skip if opponent already has no accessible area (already trapped)
            if ($currentArea === 0) {
                continue;
            }

            // Create hypothetical grid with our snake at new position
            $hypotheticalGrid = $grid;
            $hypotheticalGrid[$y][$x] = 'h'; // Mark our new head position

            // Calculate opponent's area after our move
            $newArea = $this->floodFill($opponentHeadX, $opponentHeadY, $hypotheticalGrid);

            // Reduction in opponent's area (positive = we're trapping them)
            $reduction = $currentArea - $newArea;

            // Weight by reduction ratio (more impact if we significantly reduce their space)
            if ($reduction > 0) {
                $reductionRatio = $reduction / $currentArea;
                // Scale up for scoring - significant reductions (>50%) get big bonuses
                $totalReduction += $reductionRatio * 50;
            }
        }

        return $totalReduction;
    }
}
