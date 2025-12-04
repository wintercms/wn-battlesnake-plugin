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
        $request = request()->all();
        $gameId = $request['game']['id'] ?? null;

        // Start live game tracking
        if ($gameId) {
            $gameInfo = LiveGame::buildGameInfoFromRequest($request);
            LiveGame::start($gameId, $gameInfo);

            // Also add the initial frame (turn 0)
            $frame = LiveGame::buildFrameFromRequest($request);
            LiveGame::addFrame($gameId, $frame['turn'], $frame);
        }

        $this->getSnake($snake, $password)->start();
    }

    public function move(string $snake, string $password): array
    {
        $request = request()->all();
        $gameId = $request['game']['id'] ?? null;

        // Add frame to live game
        if ($gameId) {
            $frame = LiveGame::buildFrameFromRequest($request);
            LiveGame::addFrame($gameId, $frame['turn'], $frame);
        }

        return $this->getSnake($snake, $password)->move();
    }

    public function end(string $snake, string $password): void
    {
        $request = request()->all();
        $gameId = $request['game']['id'] ?? null;

        // Mark live game as ended
        if ($gameId) {
            // Add the final frame first
            $frame = LiveGame::buildFrameFromRequest($request);
            $frame['isFinalFrame'] = true;
            LiveGame::addFrame($gameId, $frame['turn'], $frame);
            LiveGame::end($gameId);
        }

        $this->getSnake($snake, $password)->end();
    }
}