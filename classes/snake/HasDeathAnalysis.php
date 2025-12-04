<?php

namespace Winter\Battlesnake\Classes\Snake;

use Winter\Battlesnake\Classes\CoordinateHelper;
use Winter\Battlesnake\Models\GameParticipant;
use Winter\Battlesnake\Models\Turn;

/**
 * Death Cause Determination
 *
 * Methods for analyzing how snakes died, used primarily for logging
 * and game statistics.
 */
trait HasDeathAnalysis
{
    /**
     * Attempt to determine the cause of death for our own snake.
     *
     * @return string Death cause identifier
     */
    public function determineDeathCause(): string
    {
        // Check for starvation
        if ($this->you->health <= 0) {
            return 'starvation';
        }

        // Check for wall collision
        $head = $this->you->head;
        if ($head['x'] < 0 || $head['y'] < 0 ||
            $head['x'] >= $this->board->width || $head['y'] >= $this->board->height) {
            return 'wall_collision';
        }

        // Check for self-collision
        $body = $this->you->body;
        foreach (array_slice($body, 1) as $segment) {
            if ($segment['x'] === $head['x'] && $segment['y'] === $head['y']) {
                return 'self_collision';
            }
        }

        // Check for collision with other snakes
        foreach ($this->board->snakes as $snake) {
            if ($snake->id === $this->you->id) {
                continue;
            }

            // Head-on collision
            if ($snake->head['x'] === $head['x'] && $snake->head['y'] === $head['y']) {
                return 'head_collision';
            }

            // Body collision
            foreach ($snake->body as $segment) {
                if ($segment['x'] === $head['x'] && $segment['y'] === $head['y']) {
                    return 'snake_collision';
                }
            }
        }

        return 'unknown';
    }

    /**
     * Determine death cause for a snake that died (using last turn's board data).
     *
     * @param GameParticipant $participant The snake that died
     * @return string Death cause identifier
     */
    protected function determineDeathCauseForSnake(GameParticipant $participant): string
    {
        // Check for starvation first
        if ($participant->final_health <= 0) {
            return 'starvation';
        }

        // Get the last actual move for this snake (exclude 'end' turns)
        $lastTurn = Turn::where('game_id', $this->game->id)
            ->where('snake_id', $participant->snake_id)
            ->where('move', '!=', 'end')
            ->orderBy('turn', 'desc')
            ->first();

        if (!$lastTurn || !isset($lastTurn->request['you'])) {
            return 'unknown';
        }

        $snakeData = $lastTurn->request['you'];
        $head = $snakeData['head'] ?? null;
        $body = $snakeData['body'] ?? [];
        $boardWidth = $lastTurn->request['board']['width'] ?? 11;
        $boardHeight = $lastTurn->request['board']['height'] ?? 11;

        if (!$head) {
            return 'unknown';
        }

        // Determine likely next position based on their last move (raw API coordinates)
        [$nextHeadX, $nextHeadY] = CoordinateHelper::applyDirectionRaw($head['x'], $head['y'], $lastTurn->move);
        $nextHead = ['x' => $nextHeadX, 'y' => $nextHeadY];

        // Check for wall collision (out of bounds)
        if ($nextHead['x'] < 0 || $nextHead['y'] < 0 ||
            $nextHead['x'] >= $boardWidth || $nextHead['y'] >= $boardHeight) {
            return 'wall_collision';
        }

        // Check for self-collision
        foreach ($body as $segment) {
            if ($segment['x'] === $nextHead['x'] && $segment['y'] === $nextHead['y']) {
                return 'self_collision';
            }
        }

        // Check for collision with other snakes (from the board state)
        $otherSnakes = $lastTurn->request['board']['snakes'] ?? [];
        foreach ($otherSnakes as $snake) {
            if ($snake['id'] === $participant->snake_id) {
                continue;
            }

            // Check body collision (opponent's body was at this position)
            foreach ($snake['body'] as $segment) {
                if ($segment['x'] === $nextHead['x'] && $segment['y'] === $nextHead['y']) {
                    return 'snake_collision';
                }
            }

            // Check head-on collision - opponent's head could have moved to the same spot
            $opponentHead = $snake['head'];
            foreach (CoordinateHelper::getNeighborsRaw($opponentHead['x'], $opponentHead['y']) as $move) {
                if ($move[0] === $nextHead['x'] && $move[1] === $nextHead['y']) {
                    return 'head_collision';
                }
            }
        }

        return 'collision';
    }

    /**
     * Determine death cause using captured data (for deferred execution).
     *
     * @param array $data Captured game state data
     * @return string Death cause identifier
     */
    protected function determineDeathCauseFromData(array $data): string
    {
        $you = $data['you'];
        $snakes = $data['snakes'];
        $board = $data['board'];

        // Check for starvation
        if ($you['health'] <= 0) {
            return 'starvation';
        }

        // Check for wall collision
        $head = $you['head'];
        if ($head['x'] < 0 || $head['y'] < 0 ||
            $head['x'] >= $board->width || $head['y'] >= $board->height) {
            return 'wall_collision';
        }

        // Check for self-collision
        $body = $you['body'];
        foreach (array_slice($body, 1) as $segment) {
            if ($segment['x'] === $head['x'] && $segment['y'] === $head['y']) {
                return 'self_collision';
            }
        }

        // Check for collision with other snakes
        foreach ($snakes as $snake) {
            if ($snake->id === $you['id']) {
                continue;
            }

            // Head-on collision
            if ($snake->head['x'] === $head['x'] && $snake->head['y'] === $head['y']) {
                return 'head_collision';
            }

            // Body collision
            foreach ($snake->body as $segment) {
                if ($segment['x'] === $head['x'] && $segment['y'] === $head['y']) {
                    return 'snake_collision';
                }
            }
        }

        return 'unknown';
    }

    /**
     * Find the snake that killed another in a head-on collision.
     * Returns the killer's snake_id or null if not found.
     *
     * @param GameParticipant $victim The snake that was killed
     * @return string|null Killer's snake ID
     */
    protected function findKillerForHeadCollision(GameParticipant $victim): ?string
    {
        // Get victim's last actual move (exclude 'end' turns)
        $lastTurn = Turn::where('game_id', $this->game->id)
            ->where('snake_id', $victim->snake_id)
            ->where('move', '!=', 'end')
            ->orderBy('turn', 'desc')
            ->first();

        if (!$lastTurn || !isset($lastTurn->request['you'])) {
            return null;
        }

        $snakeData = $lastTurn->request['you'];
        $head = $snakeData['head'] ?? null;
        $victimLength = $snakeData['length'] ?? 0;

        if (!$head) {
            return null;
        }

        // Calculate where victim moved to (raw Battlesnake coordinates)
        [$nextHeadX, $nextHeadY] = CoordinateHelper::applyDirectionRaw($head['x'], $head['y'], $lastTurn->move);
        $nextHead = ['x' => $nextHeadX, 'y' => $nextHeadY];

        // Find which snake's head collided at that position
        $otherSnakes = $lastTurn->request['board']['snakes'] ?? [];
        foreach ($otherSnakes as $snake) {
            if ($snake['id'] === $victim->snake_id) {
                continue;
            }

            // Check if this snake's head could reach the collision point
            $opponentHead = $snake['head'];
            foreach (CoordinateHelper::getNeighborsRaw($opponentHead['x'], $opponentHead['y']) as $move) {
                if ($move[0] === $nextHead['x'] && $move[1] === $nextHead['y']) {
                    // Only credit kill if killer was longer (ties = both die, no kill credit)
                    if ($snake['length'] > $victimLength) {
                        return $snake['id'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find the snake whose body was collided with.
     * Returns the killer's snake_id or null if not found.
     *
     * @param GameParticipant $victim The snake that was killed
     * @return string|null Killer's snake ID
     */
    protected function findKillerForBodyCollision(GameParticipant $victim): ?string
    {
        // Get victim's last actual move (exclude 'end' turns)
        $lastTurn = Turn::where('game_id', $this->game->id)
            ->where('snake_id', $victim->snake_id)
            ->where('move', '!=', 'end')
            ->orderBy('turn', 'desc')
            ->first();

        if (!$lastTurn || !isset($lastTurn->request['you'])) {
            return null;
        }

        $head = $lastTurn->request['you']['head'] ?? null;
        if (!$head) {
            return null;
        }

        // Calculate where victim moved to (raw Battlesnake coordinates)
        [$nextHeadX, $nextHeadY] = CoordinateHelper::applyDirectionRaw($head['x'], $head['y'], $lastTurn->move);
        $nextHead = ['x' => $nextHeadX, 'y' => $nextHeadY];

        // Find which snake's body was at that position
        $otherSnakes = $lastTurn->request['board']['snakes'] ?? [];
        foreach ($otherSnakes as $snake) {
            if ($snake['id'] === $victim->snake_id) {
                continue;
            }

            foreach ($snake['body'] as $segment) {
                if ($segment['x'] === $nextHead['x'] && $segment['y'] === $nextHead['y']) {
                    return $snake['id'];
                }
            }
        }

        return null;
    }
}
