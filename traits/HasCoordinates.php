<?php

namespace Winter\Battlesnake\Traits;

trait HasCoordinates
{
    /**
     * Because the board is drawn from bottom to top but stored top to bottom, we need to inverse
     * all y coordinates
     * @see https://docs.battlesnake.com/api/objects/board
     */
    protected function normalizeCoordinates(array $coordinates): array
    {
        return [
            'x' => $coordinates['x'],
            'y' => ($this->height ?? $this->board->height) - $coordinates['y'] - 1,
        ];
    }
}