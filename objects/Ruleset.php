<?php

namespace Winter\Battlesnake\Objects;

/**
 * @see https://docs.battlesnake.com/api/objects/ruleset
 */
class Ruleset
{
    public string $name;
    public string $version;
    public RulesetSettings $settings;

    public function __construct(array $data = [])
    {
        $this->name = $data['name'] ?? '';
        $this->version = $data['version'] ?? '';
        $this->settings = new RulesetSettings($data['settings'] ?? []);
    }
}
