<?php

namespace Winter\Battlesnake\Classes;

class APIController
{
    protected function getSnake(string $snake, string $password)
    {
        $snake = Snake::getSnake($snake, $password);
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
        $this->getSnake($snake, $password)->start(request()->all());
    }

    public function move(string $snake, string $password): array
    {
        return $this->getSnake($snake, $password)->move(request()->all());
    }

    public function end(string $snake, string $password): void
    {
        $this->getSnake($snake, $password)->end(request()->all());
    }
}