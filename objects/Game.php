<?php

namespace Winter\Battlesnake\Objects;

/**
 * @see https://docs.battlesnake.com/api/objects/game
 */
class Game
{
    public string $id;
    public Ruleset $ruleset;
    public string $map;
    public int $timeout;
    public string $source;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->ruleset = new Ruleset($data['ruleset'] ?? []);
        $this->map = $data['map'] ?? '';
        $this->timeout = $data['timeout'] ?? 500;
        $this->source = $data['source'] ?? '';
    }
}
