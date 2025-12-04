<?php

namespace Winter\Battlesnake\Classes\Snake;

use Winter\Battlesnake\Classes\CoordinateHelper;

/**
 * Move Evaluation & Scoring Bonuses
 *
 * Methods that calculate scoring bonuses for move selection,
 * including center preference and aggression targeting.
 */
trait HasMoveScoring
{
    /**
     * Add a center-seeking bonus to break ties when areas are similar.
     * This encourages snakes to spread out rather than clustering in corners.
     * Scaled by centerPreference strategy parameter (0=none, 1=strong).
     *
     * @param array $moves Move scores keyed by direction
     * @param array $neighbors Neighbor positions keyed by direction
     * @return array Updated move scores
     */
    protected function scoreWithCenterBonus(array $moves, array $neighbors): array
    {
        $centerX = $this->board->width / 2;
        $centerY = $this->board->height / 2;

        // Get our total accessible area to determine if we're space-constrained
        $grid = $this->board->toArray();
        $totalArea = $this->floodFill($this->you->head['x'], $this->you->head['y'], $grid);

        foreach ($moves as $direction => $area) {
            [$nx, $ny] = $neighbors[$direction];

            // Calculate distance from center (lower is better)
            $distFromCenter = abs($nx - $centerX) + abs($ny - $centerY);

            // Max possible distance is roughly width/2 + height/2
            $maxDist = $centerX + $centerY;

            // Increase center preference when space is tight
            // Triple the bonus when our area is less than 20 cells (getting cornered)
            $areaFactor = ($totalArea < 20) ? 3.0 : 1.0;

            // Add a bonus for being closer to center, scaled by centerPreference
            // With centerPreference=1 and areaFactor=1: bonus ranges from 0 to 5
            // With centerPreference=1 and areaFactor=3: bonus ranges from 0 to 15
            $centerBonus = ($maxDist - $distFromCenter) / $maxDist * 5 * $this->strategy['centerPreference'] * $areaFactor;

            $moves[$direction] = $area + $centerBonus;

            if (isset($this->debugTrace['moves'][$direction])) {
                $this->debugTrace['moves'][$direction]['centerBonus'] = $centerBonus;
                $this->debugTrace['moves'][$direction]['areaFactor'] = $areaFactor;
            }
        }

        return $moves;
    }

    /**
     * Find a smaller snake to hunt when in aggressive mode.
     * Returns the head position of the target snake, or null if no suitable target.
     *
     * Only targets snakes that are at least 2 units shorter than us to ensure
     * we would win a head-on collision.
     *
     * @return array|null Target snake's head position, or null
     */
    public function getAggressionTarget(): ?array
    {
        // Don't hunt if aggression is disabled
        if ($this->strategy['aggression'] < 0.3) {
            return null;
        }

        // Don't hunt when hungry - survival first
        if ($this->you->health < $this->strategy['healthThreshold']) {
            return null;
        }

        $bestTarget = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($this->board->snakes as $snake) {
            if ($snake->id === $this->you->id) {
                continue;
            }

            // Target snakes significantly smaller than us (at least 2 units shorter)
            // This ensures we would WIN a head-on collision
            if ($snake->length < $this->you->length - 1) {
                // Prioritize closest small snake
                $distance = CoordinateHelper::getDistanceSquared($this->you->head, $snake->head);

                // Weighted by how much smaller they are (smaller = more attractive target)
                $sizeDiff = $this->you->length - $snake->length;
                $effectiveDistance = $distance / ($sizeDiff * $this->strategy['aggression']);

                if ($effectiveDistance < $bestDistance) {
                    $bestDistance = $effectiveDistance;
                    $bestTarget = $snake->head;
                }
            }
        }

        return $bestTarget;
    }

    /**
     * Apply aggression bonus to move scores when hunting a target.
     * Moves that get closer to the target get a bonus.
     *
     * @param array $scores Move scores keyed by direction
     * @param array $neighbors Neighbor positions keyed by direction
     * @param array $target Target snake's head position
     * @return array Updated move scores
     */
    public function applyAggressionBonus(array $scores, array $neighbors, array $target): array
    {
        foreach ($scores as $direction => $score) {
            $neighbor = $neighbors[$direction];
            $distanceToTarget = CoordinateHelper::getDistanceSquared(['x' => $neighbor[0], 'y' => $neighbor[1]], $target);

            // Calculate aggression bonus (higher when closer to target)
            // Scale by aggression setting (0-1)
            $maxDist = ($this->board->width ** 2) + ($this->board->height ** 2);
            $proximityBonus = ($maxDist - $distanceToTarget) * $this->strategy['aggression'] * 0.5;

            $scores[$direction] = $score + $proximityBonus;
        }

        return $scores;
    }
}
