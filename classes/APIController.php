<?php

namespace Winter\Battlesnake\Classes;

use Winter\Battlesnake\Models\SnakeTemplate;

class APIController
{
    protected function getSnake(string $snake, string $password)
    {
        $snake = SnakeTemplate::findByCredentials($snake, $password)->snake(request()->all());
        if (!$snake) {
            throw new \Exception('Invalid snake');
        }
        return $snake;
    }

    public function index(string $snake, string $password): array
    {
        return $this->getSnake($snake, $password)->info();
    }

    public function start(string $snake, string $password): void
    {
        $this->getSnake($snake, $password)->start();
    }

    public function move(string $snake, string $password): array
    {
        return $this->getSnake($snake, $password)->move();
    }

    public function end(string $snake, string $password): void
    {
        $this->getSnake($snake, $password)->end();
    }
}