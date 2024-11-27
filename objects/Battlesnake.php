<?php

namespace Winter\Battlesnake\Objects;

/**
 * @see https://docs.battlesnake.com/api/objects/battlesnake
 */
class Battlesnake
{
    use \Winter\Battlesnake\Traits\HasCoordinates;

    public string $id;
    public string $name;
    public string $health;
    public array $body;
    public int $latency;
    public array $head;
    public int $length;
    public string $shout;
    public string $squad;
    public array $customizations;
    public Board $board;

    public function __construct(array $data, Board $board)
    {
        $this->board = $board;
        $this->id = $data['id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->health = $data['health'] ?? '';
        $this->body = array_map([$this, 'normalizeCoordinates'], $data['body'] ?? []);
        $this->latency = $data['latency'] ?? 0;
        $this->head = $this->normalizeCoordinates($data['head'] ?? []);
        $this->length = $data['length'] ?? 0;
        $this->shout = $data['shout'] ?? '';
        $this->squad = $data['squad'] ?? '';
        $this->customizations = $data['customizations'] ?? [];
    }
}