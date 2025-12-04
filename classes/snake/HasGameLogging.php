<?php

namespace Winter\Battlesnake\Classes\Snake;

use Winter\Battlesnake\Models\GameParticipant;
use Winter\Battlesnake\Models\Turn;

/**
 * Turn & Result Logging
 *
 * Infrastructure for logging turns and game results.
 * Handles deferred execution to avoid blocking HTTP responses.
 */
trait HasGameLogging
{
    /**
     * Queue turn logging to execute after response is sent.
     *
     * @param array $out The response being sent
     */
    protected function logTurn(array $out): void
    {
        if (!$this->options['logTurns']) {
            return;
        }

        // Capture all data needed for logging (objects may change after response)
        $logData = [
            'game_id' => $this->game->id,
            'snake_id' => $this->you->id,
            'snake_template_id' => $this->templateId,
            'turn' => $this->turn,
            'board' => clone $this->board,
            'board_data' => $this->data['board'],
            'request' => $this->data,
            'response' => $out,
            'snakes' => $this->board->snakes,
        ];

        // Defer execution until after response is sent
        app()->terminating(function () use ($logData) {
            $this->executeLogTurn($logData);
        });
    }

    /**
     * Execute the actual logging (runs after HTTP response is sent).
     *
     * @param array $data Captured logging data
     */
    protected function executeLogTurn(array $data): void
    {
        // Build path with template ID for multi-snake support
        $snakePart = $data['snake_template_id'] ? '/snake-' . $data['snake_template_id'] : '';
        $basePath = storage_path('bs/' . $data['game_id'] . $snakePart);

        // Ensure directory exists (use @ to suppress race condition errors)
        if (!is_dir($basePath)) {
            @mkdir($basePath, 0755, true);
        }

        // Debug output
        $data['board']->draw($basePath . '/' . $data['turn'] . '.png');
        file_put_contents($basePath . '/' . $data['turn'] . '.txt', print_r($data['board']->toString(), true));

        Turn::create([
            'game_id' => $data['game_id'],
            'snake_id' => $data['snake_id'],
            'snake_template_id' => $data['snake_template_id'],
            'turn' => $data['turn'],
            'board' => $data['board_data'],
            'request' => $data['request'],
            'move' => $data['response']['move'],
        ]);

        // Track deaths of other snakes by checking who is no longer on the board
        $this->executeUpdateDeadSnakes($data);
    }

    /**
     * Update stats for snakes that have died (no longer on the board).
     * Uses captured data from deferred logging context.
     *
     * @param array $data Captured logging data
     */
    protected function executeUpdateDeadSnakes(array $data): void
    {
        $snakes = $data['snakes'];
        $gameId = $data['game_id'];
        $turn = $data['turn'];

        $aliveSnakeIds = array_map(fn($s) => $s->id, $snakes);

        // Build lookup of alive snakes' stats
        $aliveStats = [];
        foreach ($snakes as $snake) {
            $aliveStats[$snake->id] = [
                'length' => $snake->length,
                'health' => $snake->health,
            ];
        }

        // Find participants who are no longer alive and haven't been marked as dead yet
        $deadParticipants = GameParticipant::where('game_id', $gameId)
            ->whereNotIn('snake_id', $aliveSnakeIds)
            ->whereNull('result')
            ->get();

        foreach ($deadParticipants as $participant) {
            // Determine death cause from last known stats and position
            $deathCause = $this->determineDeathCauseForSnake($participant);

            $participant->update([
                'result' => 'loss',
                'death_cause' => $deathCause,
                'turns_survived' => $turn,
            ]);

            // Credit kill to the responsible snake
            $killerId = null;
            if ($deathCause === 'head_collision') {
                $killerId = $this->findKillerForHeadCollision($participant);
            } elseif ($deathCause === 'snake_collision') {
                $killerId = $this->findKillerForBodyCollision($participant);
            }

            if ($killerId) {
                GameParticipant::where('game_id', $gameId)
                    ->where('snake_id', $killerId)
                    ->increment('kills');
            }
        }

        // Also update alive snakes' current stats (so we have latest data)
        foreach ($aliveStats as $snakeId => $stats) {
            GameParticipant::where('game_id', $gameId)
                ->where('snake_id', $snakeId)
                ->whereNull('result')
                ->update([
                    'final_length' => $stats['length'],
                    'final_health' => $stats['health'],
                    'food_eaten' => max(0, $stats['length'] - 3),
                ]);
        }
    }

    /**
     * Determine and log the game result for our snake and determine winners.
     * Uses captured data from deferred logging context.
     *
     * @param array $data Captured game state data
     */
    protected function executeLogResult(array $data): void
    {
        $snakes = $data['snakes'];
        $gameId = $data['game_id'];
        $turn = $data['turn'];
        $you = $data['you'];

        $aliveSnakeIds = array_map(fn($s) => $s->id, $snakes);
        $weAreAlive = in_array($you['id'], $aliveSnakeIds);
        $snakeCount = count($snakes);

        // If there's exactly 1 snake left, they're the winner
        if ($snakeCount === 1) {
            $winner = $snakes[0];
            GameParticipant::where('game_id', $gameId)
                ->where('snake_id', $winner->id)
                ->whereNull('result')
                ->update([
                    'result' => 'win',
                    'turns_survived' => $turn,
                    'final_length' => $winner->length,
                    'final_health' => $winner->health,
                    'food_eaten' => max(0, $winner->length - 3),
                ]);
        }

        // Check if we were already marked as dead during the game
        $participant = GameParticipant::where('game_id', $gameId)
            ->where('snake_id', $you['id'])
            ->first();

        // If already has a result, death was tracked during game - don't overwrite our result
        if ($participant && $participant->result !== null) {
            return;
        }

        // Determine our result
        if ($weAreAlive && $snakeCount === 1) {
            $result = 'win';
            $deathCause = null;
        } elseif ($weAreAlive && $snakeCount > 1) {
            $result = 'draw';
            $deathCause = null;
        } else {
            $result = 'loss';
            $deathCause = $this->determineDeathCauseFromData($data);
        }

        // Calculate food eaten: final length minus starting length (3)
        $foodEaten = max(0, $you['length'] - 3);

        // Update our snake's record
        GameParticipant::where('game_id', $gameId)
            ->where('snake_id', $you['id'])
            ->update([
                'result' => $result,
                'death_cause' => $deathCause,
                'turns_survived' => $turn,
                'final_length' => $you['length'],
                'final_health' => $you['health'],
                'food_eaten' => $foodEaten,
            ]);
    }
}
