<?php

namespace Winter\Battlesnake\Objects;

/**
 * @see https://docs.battlesnake.com/api/objects/ruleset-settings
 */
class RulesetSettings
{
    public int $foodSpawnChance;
    public int $minimumFood;
    public int $hazardDamagePerTurn;
    public array $royale;
    public array $squad;

    public function __construct(array $data = [])
    {
        $this->foodSpawnChance = $data['foodSpawnChance'] ?? 15;
        $this->minimumFood = $data['minimumFood'] ?? 1;
        $this->hazardDamagePerTurn = $data['hazardDamagePerTurn'] ?? 1;
        $this->royale = $data['royale'] ?? [];
        $this->squad = $data['squad'] ?? [];
    }
}
