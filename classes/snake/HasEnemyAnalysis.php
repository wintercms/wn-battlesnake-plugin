<?php

namespace Winter\Battlesnake\Classes\Snake;

use Winter\Battlesnake\Classes\CoordinateHelper;
use Winter\Battlesnake\Objects\Board;
use Winter\Battlesnake\Objects\Battlesnake;

/**
 * Enemy Threat Assessment
 *
 * Methods for analyzing opponent snakes, collision risks, and movement predictions.
 */
trait HasEnemyAnalysis
{
    /**
     * Get the collision risk level for moving to the given coordinates.
     *
     * Returns:
     *   0 = no risk
     *   1 = risk but we would win (we're longer)
     *   2 = risk and it's a tie (equal length - both die)
     *   3 = risk and we would lose (they're longer)
     *
     * @param int $x Target X coordinate
     * @param int $y Target Y coordinate
     * @return int Risk level 0-3
     */
    public function getCollisionRisk($x, $y): int
    {
        $maxRisk = 0;

        foreach ($this->board->snakes as $snake) {
            if ($snake->id === $this->you->id) {
                continue;
            }

            $opponentHead = $snake->head;
            // Check all positions the opponent could move to (in normalized coords)
            foreach (CoordinateHelper::getNeighbors($opponentHead['x'], $opponentHead['y']) as $move) {
                if ($move[0] == $x && $move[1] == $y) {
                    // Potential collision at this position
                    if ($snake->length > $this->you->length) {
                        $risk = 3; // We would lose
                    } elseif ($snake->length == $this->you->length) {
                        $risk = 2; // Tie - both die
                    } else {
                        $risk = 1; // We would win
                    }
                    $maxRisk = max($maxRisk, $risk);
                }
            }
        }

        return $maxRisk;
    }

    /**
     * Check if moving to the provided coordinates is dangerous.
     * A move is dangerous if an equal or longer snake could also move there.
     *
     * @param int $x Target X coordinate
     * @param int $y Target Y coordinate
     * @return bool True if dangerous
     */
    public function isDangerousMove($x, $y): bool
    {
        foreach ($this->board->snakes as $snake) {
            if ($snake->id === $this->you->id) {
                continue;
            }

            $opponentHead = $snake->head;
            foreach (CoordinateHelper::getNeighbors($opponentHead['x'], $opponentHead['y']) as $move) {
                if ($move[0] == $x && $move[1] == $y && $snake->length >= $this->you->length) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if enemy snake's tail will likely move (safe to occupy next turn).
     * Returns true if tail will probably move, false if it might stay.
     *
     * IMPORTANT: Snake tails stay put when the snake just ate (health = 100).
     * This is the only case where we can be certain. Other cases are soft predictions.
     *
     * @param Battlesnake $snake The snake to check
     * @return bool True if tail will likely move
     */
    public function willTailLikelyMove(Battlesnake $snake): bool
    {
        // If snake just ate (health = 100), tail stays put FOR SURE
        if ($snake->health >= 100) {
            return false;
        }

        // Otherwise tail will move - but snake could eat food this turn
        // so this is a soft prediction, not a guarantee
        return true;
    }

    /**
     * Get soft prediction of where enemy is likely to move.
     * Returns array of [direction => likelihood] where likelihood is 0.0-1.0.
     *
     * NOTE: Use as TIE-BREAKER only, not hard filter. Enemy snakes can behave
     * erratically due to connectivity issues, bugs, or unexpected strategies.
     *
     * @param Battlesnake $snake The enemy snake to predict
     * @return array Direction likelihoods
     */
    public function predictEnemyMoveLikelihood(Battlesnake $snake): array
    {
        $head = $snake->head; // Already normalized
        $likelihoods = ['up' => 0.25, 'down' => 0.25, 'left' => 0.25, 'right' => 0.25];

        // Can't reverse into neck (this IS certain)
        $neck = $snake->body[1] ?? null;
        if ($neck) {
            // Neck is in API coords, need to normalize
            $neckNorm = [
                'x' => $neck['x'],
                'y' => $this->board->height - $neck['y'] - 1,
            ];
            $reverseDir = CoordinateHelper::getDirectionBetween($head, $neckNorm);
            if ($reverseDir) {
                $likelihoods[$reverseDir] = 0.0;
                // Redistribute to other directions
                $remaining = array_filter($likelihoods, fn($v) => $v > 0);
                $perDir = 1.0 / count($remaining);
                foreach ($likelihoods as $dir => $val) {
                    if ($val > 0) {
                        $likelihoods[$dir] = $perDir;
                    }
                }
            }
        }

        // Filter out moves that would hit walls
        $grid = $this->board->toArray();
        $moves = CoordinateHelper::getNeighbors($head['x'], $head['y']);

        foreach ($moves as $dir => [$nx, $ny]) {
            if (!isset($grid[$ny][$nx]) || in_array($grid[$ny][$nx], Board::DANGER_CHARS)) {
                $likelihoods[$dir] = 0.0;
            }
        }

        // Normalize remaining probabilities
        $total = array_sum($likelihoods);
        if ($total > 0) {
            foreach ($likelihoods as $dir => $val) {
                $likelihoods[$dir] = $val / $total;
            }
        }

        return $likelihoods;
    }

    /**
     * Get total likelihood that any enemy snake will move to the given position.
     * Returns a value between 0.0 and 1.0 (can exceed 1 if multiple enemies converge).
     *
     * @param int $x Target X coordinate
     * @param int $y Target Y coordinate
     * @return float Total likelihood
     */
    public function getEnemyLikelihoodAtPosition(int $x, int $y): float
    {
        $totalLikelihood = 0.0;

        foreach ($this->board->snakes as $snake) {
            if ($snake->id === $this->you->id) {
                continue;
            }

            $head = $snake->head;
            $moves = CoordinateHelper::getNeighbors($head['x'], $head['y']);
            $likelihoods = $this->predictEnemyMoveLikelihood($snake);

            foreach ($moves as $dir => [$nx, $ny]) {
                if ($nx === $x && $ny === $y) {
                    $totalLikelihood += $likelihoods[$dir];
                }
            }
        }

        return $totalLikelihood;
    }

    /**
     * Calculate penalty for moving adjacent to a larger snake's head.
     * Returns a penalty value - higher means more dangerous.
     *
     * @param int $x Target X coordinate
     * @param int $y Target Y coordinate
     * @return float Penalty value
     */
    public function getLargerSnakePenalty(int $x, int $y): float
    {
        $penalty = 0.0;

        foreach ($this->board->snakes as $snake) {
            if ($snake->id === $this->you->id) {
                continue;
            }

            // Only penalize if enemy is larger or equal
            if ($snake->length < $this->you->length) {
                continue;
            }

            $head = $snake->head;

            // Check if the position is adjacent to this snake's head
            foreach (CoordinateHelper::getNeighbors($head['x'], $head['y']) as [$ax, $ay]) {
                if ($ax === $x && $ay === $y) {
                    // Apply penalty based on how much larger the enemy is
                    // Equal length = moderate penalty, larger = bigger penalty
                    $sizeDiff = $snake->length - $this->you->length;
                    $penalty += 15 + ($sizeDiff * 5);
                    break;
                }
            }
        }

        return $penalty;
    }
}
