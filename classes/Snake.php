<?php

namespace Winter\Battlesnake\Classes;

use Illuminate\Foundation\Inspiring;
use Winter\Battlesnake\Classes\Snake\HasDeathAnalysis;
use Winter\Battlesnake\Classes\Snake\HasEnemyAnalysis;
use Winter\Battlesnake\Classes\Snake\HasFloodFill;
use Winter\Battlesnake\Classes\Snake\HasGameLogging;
use Winter\Battlesnake\Classes\Snake\HasMoveScoring;
use Winter\Battlesnake\Models\GameLog;
use Winter\Battlesnake\Models\GameParticipant;
use Winter\Battlesnake\Objects\Board;
use Winter\Battlesnake\Objects\Battlesnake;
use Winter\Battlesnake\Objects\Game;
use Winter\Storm\Support\Str;

/**
 * Battlesnake AI Brain
 *
 * Core decision-making class for Battlesnake games. Handles game state parsing,
 * move decisions, and API endpoint responses.
 *
 * Strategy and behavior can be customized via:
 * - The $strategy array (configurable parameters)
 * - Overriding methods in SnakeTemplate code
 *
 * @see HasFloodFill      Space analysis algorithms
 * @see HasEnemyAnalysis  Enemy threat assessment
 * @see HasMoveScoring    Move evaluation and scoring bonuses
 * @see HasDeathAnalysis  Death cause determination
 * @see HasGameLogging    Turn and result logging
 */
class Snake
{
    use HasFloodFill;
    use HasEnemyAnalysis;
    use HasMoveScoring;
    use HasDeathAnalysis;
    use HasGameLogging;

    public Game $game;
    public Board $board;
    public Battlesnake $you;
    public int $turn;
    public ?int $templateId = null;
    public array $options = [
        'logTurns' => true,
    ];
    public array $info = [];
    public array $data = [];

    /**
     * Default strategy parameters
     */
    protected static array $defaultStrategy = [
        'healthThreshold' => 50,      // Below this, seek food aggressively
        'aggression' => 0.0,          // 0=passive, 1=aggressive (hunt smaller snakes)
        'foodWeight' => 1.0,          // Multiplier for food-seeking behavior
        'spaceWeight' => 1.0,         // Multiplier for space-maximizing
        'escapeRouteWeight' => 2.0,   // Bonus for moves with multiple escape routes
        'opennessWeight' => 1.0,      // Bonus for immediate open space vs narrow corridors
        'avoidLargerSnakes' => true,  // Stay away from larger snake heads
        'centerPreference' => 0.5,    // Preference for center (0=none, 1=strong)
        'enemyPredictionWeight' => 0.3, // How much to trust enemy movement predictions (soft bonus)
        'trapAvoidance' => 1.0,       // Penalty for moves with 0-1 escape routes (0=ignore, 2=heavy)
        'lookaheadDepth' => 2,        // Turns ahead to predict tail movement (0-4)
        'growthPriority' => 0.5,      // Multiplier for size-advantage seeking (0-1)
        'targetLead' => 2,            // Target being X units bigger than largest opponent
        'trappingAggression' => 0.5,  // Bonus for moves that reduce opponent's area (0-1)
    ];

    /**
     * Strategy parameters - can be overridden via options['strategy'] for live tournament tuning
     */
    public array $strategy = [];

    /**
     * Get the default strategy values.
     */
    public static function getDefaultStrategy(): array
    {
        return static::$defaultStrategy;
    }

    /**
     * Debug trace for replay analysis - populated during move() decision
     */
    public array $debugTrace = [];

    public function __construct(array $state, array $options = [])
    {
        // Initialize strategy with defaults
        $this->strategy = static::getDefaultStrategy();

        $this->parseState($state);
        $this->options = array_merge($this->options, $options);
        $this->templateId = $this->options['templateId'] ?? null;

        // Merge strategy overrides for live tournament tuning
        if (!empty($this->options['strategy'])) {
            $this->strategy = array_merge($this->strategy, $this->options['strategy']);
        }
    }

    protected function parseState(array $data): void
    {
        if (empty($data)) {
            return;
        }

        $this->game = new Game($data['game'] ?? []);
        $this->board = new Board($data['board'] ?? []);
        $this->you = new Battlesnake($data['you'] ?? [], $this->board);
        $this->turn = $data['turn'] ?? 0;
        $this->data = $data;

        // Set youId on board for proper ASCII output
        $this->board->youId = $this->you->id ?? null;
    }

    /**
     * @see https://docs.battlesnake.com/api/requests/info
     */
    public function info(): array
    {
        return $this->info ?? [
            "apiversion" => "1",
            "author" => "luketowers",
            "color" => "#3498db",
            "head" => "snow-worm",
            "tail" => "flake",
            "version" => "0.0.1-beta",
        ];
    }

    /**
     * @see https://docs.battlesnake.com/api/requests/start
     */
    public function start(): void
    {
        // Use firstOrCreate for GameLog to support multiple snakes in same game
        GameLog::firstOrCreate(
            ['game_id' => $this->game->id],
            [
                'ruleset' => $this->data['game']['ruleset'],
                'map' => $this->data['game']['map'] ?? $this->game->id,
                'timeout' => $this->game->timeout,
                'source' => $this->game->source,
            ]
        );

        // Create GameParticipant records for ALL snakes in the game
        foreach ($this->board->snakes as $snake) {
            $isOurSnake = ($snake->id === $this->you->id);

            GameParticipant::firstOrCreate(
                [
                    'game_id' => $this->game->id,
                    'snake_id' => $snake->id,
                ],
                [
                    'snake_name' => $snake->name,
                    'snake_template_id' => $isOurSnake ? $this->templateId : null,
                    'turns_survived' => 0,
                    'final_length' => $snake->length ?? 3,
                    'final_health' => $snake->health ?? 100,
                    'kills' => 0,
                    'food_eaten' => 0,
                ]
            );
        }
    }

    /**
     * @see https://docs.battlesnake.com/api/requests/move
     */
    public function move(): array
    {
        // Initialize debug trace for replay analysis
        $neckPos = $this->you->body[1] ?? null;
        $this->debugTrace = [
            'strategy' => $this->strategy,
            'state' => [
                'health' => $this->you->health,
                'length' => $this->you->length,
                'turn' => $this->turn,
                'headPosition' => $this->you->head,
                'neckPosition' => $neckPos ? [
                    'x' => $neckPos['x'],
                    'y' => $this->board->height - $neckPos['y'] - 1,
                ] : null,
            ],
            'mode' => null,
            'foodTarget' => null,
            'aggressionTarget' => null,
            'moves' => [
                'up' => ['position' => null, 'valid' => false, 'rejectedReason' => null, 'area' => 0, 'escapeRoutes' => 0, 'collisionRisk' => 0, 'score' => 0],
                'down' => ['position' => null, 'valid' => false, 'rejectedReason' => null, 'area' => 0, 'escapeRoutes' => 0, 'collisionRisk' => 0, 'score' => 0],
                'left' => ['position' => null, 'valid' => false, 'rejectedReason' => null, 'area' => 0, 'escapeRoutes' => 0, 'collisionRisk' => 0, 'score' => 0],
                'right' => ['position' => null, 'valid' => false, 'rejectedReason' => null, 'area' => 0, 'escapeRoutes' => 0, 'collisionRisk' => 0, 'score' => 0],
            ],
            'categorization' => [
                'safeMoves' => [],
                'riskyMoves' => [],
                'validMoves' => [],
            ],
            'decision' => [
                'selectedMove' => null,
                'reason' => null,
            ],
        ];

        // Calculate effective health threshold with target lead adjustment
        // The snake tries to be targetLead units bigger than the largest opponent
        $effectiveThreshold = $this->strategy['healthThreshold'];

        if ($this->strategy['growthPriority'] > 0) {
            $maxOpponentLength = 0;
            foreach ($this->board->snakes as $snake) {
                if ($snake->id !== $this->you->id) {
                    $maxOpponentLength = max($maxOpponentLength, $snake->length);
                }
            }

            // How far from our target lead? (positive = need to grow)
            $targetLead = $this->strategy['targetLead'] ?? 2;
            $gapFromTarget = ($maxOpponentLength + $targetLead) - $this->you->length;
            $this->debugTrace['gapFromTarget'] = $gapFromTarget;
            $this->debugTrace['targetLead'] = $targetLead;
            $this->debugTrace['maxOpponentLength'] = $maxOpponentLength;

            if ($gapFromTarget > 0) {
                // Below target lead - seek food more aggressively
                $thresholdBoost = $gapFromTarget * $this->strategy['growthPriority'] * 8;
                $effectiveThreshold = min($effectiveThreshold + $thresholdBoost, 95); // Cap at 95
            }
        }
        $this->debugTrace['effectiveThreshold'] = $effectiveThreshold;

        // Decide whether to seek food or avoid hazards based on effective health threshold
        if ($this->you->health < $effectiveThreshold) {
            // Target food when hungry (or when smaller than opponents with growthPriority)
            $this->debugTrace['mode'] = 'food_seeking';
            $move = $this->getNextMove($this->you->head['x'], $this->you->head['y']);
        } else {
            // Avoid hazards and maximize open space when healthy
            $this->debugTrace['mode'] = 'space_maximizing';
            $move = $this->avoidHazards($this->you->head['x'], $this->you->head['y']);
        }

        // Record final decision
        $this->debugTrace['decision']['selectedMove'] = $move;

        $response = [
            'move' => $move,
            'shout' => Str::limit(Inspiring::quotes()->random(), 253),
        ];

        $this->logTurn($response);

        if ($response['move'] === 'death') {
            $response['move'] = 'up';
        }

        return $response;
    }

    /**
     * @see https://docs.battlesnake.com/api/requests/end
     */
    public function end(): void
    {
        if (!$this->options['logTurns']) {
            return;
        }

        // Capture all data needed for end-game logging
        $logData = [
            'game_id' => $this->game->id,
            'snake_id' => $this->you->id,
            'snake_template_id' => $this->templateId,
            'turn' => $this->turn,
            'board' => clone $this->board,
            'board_data' => $this->data['board'],
            'request' => $this->data,
            'response' => ['move' => 'end'],
            'snakes' => $this->board->snakes,
            'you' => [
                'id' => $this->you->id,
                'length' => $this->you->length,
                'health' => $this->you->health,
                'head' => $this->you->head,
                'body' => $this->you->body,
            ],
        ];

        // Defer execution until after response is sent
        app()->terminating(function () use ($logData) {
            $this->executeLogTurn($logData);
            $this->executeLogResult($logData);
        });
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Get the food on the board by distance
     * @return array [distance => index]
     */
    public function getFoodByDistance(): array
    {
        $foodDistance = [];
        $head = $this->you->head;
        foreach ($this->board->food as $index => $food) {
            $foodDistance[CoordinateHelper::getDistanceSquared($food, $head)] = $index;
        }

        // Sort by distance
        ksort($foodDistance);

        return $foodDistance;
    }

    // =========================================================================
    // Core Decision Methods
    // =========================================================================

    /**
     * Select the best move based on avoiding hazards and maximizing space.
     * Used when health is above threshold.
     */
    public function avoidHazards($x, $y): string
    {
        // Possible moves with their GRID coordinates (normalized coordinate system)
        // Note: The Board/Battlesnake objects normalize coordinates by flipping Y.
        // In the normalized system, y=0 is at the TOP of the grid.
        // So to move "up" in Battlesnake (toward higher Y in raw coords), we go y-1 in the grid.
        // To move "down" in Battlesnake (toward lower Y in raw coords), we go y+1 in the grid.
        $neighbors = CoordinateHelper::getNeighbors($x, $y);

        // Get the game grid
        $grid = $this->board->toArray();

        // Get neck position to prevent reversing into ourselves
        // body[1] is where the head was last turn (the "neck")
        // NOTE: $this->you->body is already normalized by Battlesnake object, don't normalize again!
        $neckPos = $this->you->body[1] ?? null;
        $neckX = $neckY = null;
        if ($neckPos) {
            $neckX = $neckPos['x'];
            $neckY = $neckPos['y']; // Already normalized, don't flip again!
        }

        // Categorize moves into tiers:
        // Tier 1: Safe moves (valid, no danger, no risky head-on)
        // Tier 2: Risky moves (valid, but potential head-on collision)
        // Tier 3: Any valid move (last resort - will die but try anyway)
        $safeMoves = [];
        $riskyMoves = [];
        $validMoves = [];

        foreach ($neighbors as $direction => $neighbor) {
            // Record position in debug trace
            $this->debugTrace['moves'][$direction]['position'] = ['x' => $neighbor[0], 'y' => $neighbor[1]];

            // CRITICAL: Never reverse into our own neck (instant death)
            if ($neckPos !== null && $neighbor[0] === $neckX && $neighbor[1] === $neckY) {
                $this->debugTrace['moves'][$direction]['rejectedReason'] = 'neck_collision';
                continue;
            }

            // Check if the move is within bounds and not into a wall or snake
            if (!isset($grid[$neighbor[1]][$neighbor[0]])) {
                $this->debugTrace['moves'][$direction]['rejectedReason'] = 'out_of_bounds';
                continue;
            }

            if (in_array($grid[$neighbor[1]][$neighbor[0]], Board::DANGER_CHARS)) {
                $cell = $grid[$neighbor[1]][$neighbor[0]];
                if (in_array($cell, ['Y', 'y'])) {
                    $this->debugTrace['moves'][$direction]['rejectedReason'] = 'own_body';
                } elseif ($cell === 'H') {
                    $this->debugTrace['moves'][$direction]['rejectedReason'] = 'hazard';
                } else {
                    $this->debugTrace['moves'][$direction]['rejectedReason'] = 'enemy_body';
                }
                continue;
            }

            // This is at least a valid move (not into wall or snake body)
            $this->debugTrace['moves'][$direction]['valid'] = true;

            $area = $this->floodFill($neighbor[0], $neighbor[1], $grid);
            $this->debugTrace['moves'][$direction]['area'] = $area;

            // Calculate immediate openness - how much space is accessible in the near term
            // This detects narrow corridors vs wide open areas with the same total area
            $lookAhead = min($this->you->length, 8);
            $immediateArea = $this->floodFillWithDepth($neighbor[0], $neighbor[1], $grid, $lookAhead);
            $this->debugTrace['moves'][$direction]['immediateArea'] = $immediateArea;

            // Count escape routes - positions with multiple exits are safer
            $escapeRoutes = $this->countEscapeRoutes($neighbor[0], $neighbor[1], $grid);
            $this->debugTrace['moves'][$direction]['escapeRoutes'] = $escapeRoutes;

            // Calculate openness ratio: immediate area / max theoretical for this depth
            // Max theoretical area for depth d is roughly 2*d^2 + 2*d + 1 (diamond shape)
            $maxTheoretical = 2 * $lookAhead * $lookAhead + 2 * $lookAhead + 1;
            $opennessRatio = $immediateArea / max(1, min($maxTheoretical, $area));

            // Combined score: area + escape route bonus + openness bonus
            // Escape routes are heavily weighted to avoid getting trapped
            // Openness bonus prefers wide open areas over narrow corridors
            $score = ($area * $this->strategy['spaceWeight'])
                + ($escapeRoutes * $this->strategy['escapeRouteWeight'] * 10)
                + ($opennessRatio * $this->strategy['opennessWeight'] * 10);

            // Apply enemy prediction penalty - penalize moves that enemies might reach
            if ($this->strategy['enemyPredictionWeight'] > 0) {
                $enemyLikelihood = $this->getEnemyLikelihoodAtPosition($neighbor[0], $neighbor[1]);
                $enemyPenalty = $enemyLikelihood * $this->strategy['enemyPredictionWeight'] * 20;
                $score -= $enemyPenalty;
                $this->debugTrace['moves'][$direction]['enemyPenalty'] = $enemyPenalty;
            }

            // Apply avoidLargerSnakes penalty - discourage moves adjacent to larger snake heads
            if ($this->strategy['avoidLargerSnakes']) {
                $largerSnakePenalty = $this->getLargerSnakePenalty($neighbor[0], $neighbor[1]);
                $score -= $largerSnakePenalty;
                $this->debugTrace['moves'][$direction]['largerSnakePenalty'] = $largerSnakePenalty;
            }

            // Apply trap avoidance penalty - heavily penalize moves with few escape routes
            // This is critical: moves with 0 escape routes lead to guaranteed death
            // Only apply in multi-snake games (solo snakes naturally fill the board)
            $opponentCount = count($this->board->snakes) - 1;
            if ($this->strategy['trapAvoidance'] > 0 && $opponentCount > 0) {
                $trapPenalty = 0;
                if ($escapeRoutes === 0) {
                    // No escape routes = potential death trap
                    // Use flat penalty so larger areas aren't penalized more heavily
                    $trapPenalty = 20 * $this->strategy['trapAvoidance'];
                } elseif ($escapeRoutes === 1) {
                    // Only one escape route = risky, half the penalty
                    $trapPenalty = 10 * $this->strategy['trapAvoidance'];
                }
                if ($trapPenalty > 0) {
                    $score -= $trapPenalty;
                    $this->debugTrace['moves'][$direction]['trapPenalty'] = $trapPenalty;
                }
            }

            // Apply tail lookahead bonus - if area is small now but will open up as tails move
            // This helps recognize when following our own tail creates space
            if ($this->strategy['lookaheadDepth'] > 0 && $area < $this->you->length * 2) {
                $predictedArea = $this->floodFillWithTailPrediction(
                    $neighbor[0],
                    $neighbor[1],
                    $grid,
                    $this->strategy['lookaheadDepth']
                );
                $this->debugTrace['moves'][$direction]['predictedArea'] = $predictedArea;

                // If predicted area is significantly larger, boost the score
                // This encourages following tails when currently constrained
                if ($predictedArea > $area) {
                    $lookaheadBonus = ($predictedArea - $area) * 0.5;
                    $score += $lookaheadBonus;
                    $this->debugTrace['moves'][$direction]['lookaheadBonus'] = $lookaheadBonus;
                }
            }

            // Apply trapping bonus - reward moves that reduce opponents' accessible area
            // Only do this when we're the largest snake (or equal) to avoid risky plays
            if ($this->strategy['trappingAggression'] > 0) {
                $maxOpponentLength = 0;
                foreach ($this->board->snakes as $snake) {
                    if ($snake->id !== $this->you->id) {
                        $maxOpponentLength = max($maxOpponentLength, $snake->length);
                    }
                }

                // Only try to trap when we're the largest (or equal)
                if ($this->you->length >= $maxOpponentLength) {
                    $trappingBonus = $this->getOpponentAreaReduction($neighbor[0], $neighbor[1], $grid)
                        * $this->strategy['trappingAggression'];
                    if ($trappingBonus > 0) {
                        $score += $trappingBonus;
                        $this->debugTrace['moves'][$direction]['trappingBonus'] = $trappingBonus;
                    }
                }
            }

            $this->debugTrace['moves'][$direction]['score'] = $score;

            $validMoves[$direction] = $score;

            // Check for potential head-on collisions
            $collisionRisk = $this->getCollisionRisk($neighbor[0], $neighbor[1]);
            $this->debugTrace['moves'][$direction]['collisionRisk'] = $collisionRisk;

            if ($collisionRisk <= 1) {
                // No collision risk (0) or we would win (1) - this is a safe move
                // Winning a head-on collision is advantageous, not risky
                $safeMoves[$direction] = $score;
            } else {
                // Collision risk 2 (tie) or 3 (lose) - track as risky
                $riskyMoves[$direction] = ['area' => $area, 'score' => $score, 'risk' => $collisionRisk];
            }
        }

        // Check for aggression target (smaller snake to hunt)
        $aggressionTarget = $this->getAggressionTarget();
        $this->debugTrace['aggressionTarget'] = $aggressionTarget;

        // Record categorization in debug trace
        $this->debugTrace['categorization']['safeMoves'] = $safeMoves;
        $this->debugTrace['categorization']['riskyMoves'] = array_map(fn($m) => $m['score'], $riskyMoves);
        $this->debugTrace['categorization']['validMoves'] = $validMoves;

        // Select best move from the best available tier
        if (!empty($safeMoves)) {
            // Tier 1: Pick the safe move with the largest area
            // When areas are similar, prefer moves toward the center
            $safeMoves = $this->scoreWithCenterBonus($safeMoves, $neighbors);

            // Apply aggression bonus if hunting a smaller snake
            if ($aggressionTarget !== null) {
                $safeMoves = $this->applyAggressionBonus($safeMoves, $neighbors, $aggressionTarget);
            }

            arsort($safeMoves);
            $selected = key($safeMoves);
            $this->debugTrace['decision']['reason'] = 'Highest scoring safe move (area + escape routes + center bonus)';
            return $selected;
        }

        if (!empty($riskyMoves)) {
            // Tier 2: Pick the risky move with best score/risk ratio
            // Only risk 2 (tie) and 3 (lose) moves are here - risk 1 (win) is in safeMoves
            uasort($riskyMoves, function ($a, $b) {
                // First prefer lower risk (tie over lose), then prefer higher score
                if ($a['risk'] !== $b['risk']) {
                    return $a['risk'] - $b['risk'];
                }
                return $b['score'] - $a['score'];
            });

            $bestRisky = reset($riskyMoves);
            $selected = key($riskyMoves);
            $riskLabels = [2 => 'tie', 3 => 'lose'];
            $this->debugTrace['decision']['reason'] = "Best risky move (risk={$bestRisky['risk']}={$riskLabels[$bestRisky['risk']]}, no safe moves)";
            return $selected;
        }

        if (!empty($validMoves)) {
            // Tier 3: All moves have collision risk, just pick the one with most space
            arsort($validMoves);
            $selected = key($validMoves);
            $this->debugTrace['decision']['reason'] = 'Fallback to valid move with largest area (avoiding high collision risk)';
            return $selected;
        }

        // No valid moves at all - we're completely trapped, pick any direction
        // that's at least in bounds (prefer not hitting a wall)
        foreach ($neighbors as $direction => $neighbor) {
            if (isset($grid[$neighbor[1]][$neighbor[0]])) {
                $this->debugTrace['decision']['reason'] = 'No valid moves - trapped, picking any in-bounds direction';
                return $direction; // At least won't hit a wall
            }
        }

        $this->debugTrace['decision']['reason'] = 'Completely trapped - absolute last resort (down)';
        return 'down'; // Absolute last resort
    }

    /**
     * Select the best move when seeking food.
     * Used when health is below threshold.
     */
    public function getNextMove($x, $y): string
    {
        // Target the closest food
        $food = $this->getFoodByDistance();
        $target = $this->board->food[array_shift($food)] ?? null;

        // Record food target in debug trace
        if ($target) {
            $this->debugTrace['foodTarget'] = ['x' => $target['x'], 'y' => $target['y']];
        }

        // Pick neighbour cells (using normalized/grid coordinate system)
        // In the normalized system, y=0 is at the TOP of the grid.
        // "up" in Battlesnake means y-1 in grid coords, "down" means y+1.
        $neighbors = CoordinateHelper::getNeighbors($x, $y);

        // Get the game grid
        $grid = $this->board->toArray();

        // Get neck position to prevent reversing into ourselves
        // NOTE: $this->you->body is already normalized by Battlesnake object, don't normalize again!
        $neckPos = $this->you->body[1] ?? null;
        $neckX = $neckY = null;
        if ($neckPos) {
            $neckX = $neckPos['x'];
            $neckY = $neckPos['y']; // Already normalized, don't flip again!
        }

        // Categorize moves into tiers (same as avoidHazards)
        $safeMoves = [];
        $riskyMoves = [];
        $validMoves = [];

        foreach ($neighbors as $direction => $neighbor) {
            // Record position in debug trace
            $this->debugTrace['moves'][$direction]['position'] = ['x' => $neighbor[0], 'y' => $neighbor[1]];

            // CRITICAL: Never reverse into our own neck (instant death)
            if ($neckPos !== null && $neighbor[0] === $neckX && $neighbor[1] === $neckY) {
                $this->debugTrace['moves'][$direction]['rejectedReason'] = 'neck_collision';
                continue;
            }

            // Filter invalid moves (out of bounds or into snake body)
            if (!isset($grid[$neighbor[1]][$neighbor[0]])) {
                $this->debugTrace['moves'][$direction]['rejectedReason'] = 'out_of_bounds';
                continue;
            }

            $cellContent = $grid[$neighbor[1]][$neighbor[0]];
            if (in_array($cellContent, Board::DANGER_CHARS)) {
                // Determine specific rejection reason
                if ($cellContent === 'h') {
                    $this->debugTrace['moves'][$direction]['rejectedReason'] = 'enemy_head';
                } elseif ($cellContent === 's') {
                    // Check if it's our body or enemy body
                    $isOwnBody = false;
                    foreach ($this->you->body as $segment) {
                        $segX = $segment['x'];
                        $segY = $this->board->height - $segment['y'] - 1;
                        if ($segX === $neighbor[0] && $segY === $neighbor[1]) {
                            $isOwnBody = true;
                            break;
                        }
                    }
                    $this->debugTrace['moves'][$direction]['rejectedReason'] = $isOwnBody ? 'own_body' : 'enemy_body';
                } elseif ($cellContent === 'x') {
                    $this->debugTrace['moves'][$direction]['rejectedReason'] = 'hazard';
                } else {
                    $this->debugTrace['moves'][$direction]['rejectedReason'] = 'danger_cell';
                }
                continue;
            }

            // Perform flood-fill from this neighbor position
            $area = $this->floodFill($neighbor[0], $neighbor[1], $grid);

            // Count escape routes - positions with multiple exits are safer
            $escapeRoutes = $this->countEscapeRoutes($neighbor[0], $neighbor[1], $grid);

            // Calculate score: accessible area + escape route bonus - distance to target
            // foodWeight amplifies the distance penalty, making food-seeking more urgent
            $distanceToTarget = $target
                ? CoordinateHelper::getDistanceSquared(['x' => $neighbor[0], 'y' => $neighbor[1]], $target)
                : 0;
            $escapeBonus = $escapeRoutes * $this->strategy['escapeRouteWeight'] * 10;
            $foodPenalty = $distanceToTarget * $this->strategy['foodWeight'];
            $score = $area + $escapeBonus - $foodPenalty;

            // Apply trap avoidance penalty - even when hungry, don't enter death traps
            // Only apply in multi-snake games (solo snakes naturally fill the board)
            $opponentCount = count($this->board->snakes) - 1;
            if ($this->strategy['trapAvoidance'] > 0 && $opponentCount > 0) {
                $trapPenalty = 0;
                if ($escapeRoutes === 0) {
                    // Use flat penalty so larger areas aren't penalized more heavily
                    $trapPenalty = 20 * $this->strategy['trapAvoidance'];
                } elseif ($escapeRoutes === 1) {
                    $trapPenalty = 10 * $this->strategy['trapAvoidance'];
                }
                if ($trapPenalty > 0) {
                    $score -= $trapPenalty;
                    $this->debugTrace['moves'][$direction]['trapPenalty'] = $trapPenalty;
                }
            }

            // Check for potential head-on collisions
            $collisionRisk = $this->getCollisionRisk($neighbor[0], $neighbor[1]);

            // Record valid move data in debug trace
            $this->debugTrace['moves'][$direction]['valid'] = true;
            $this->debugTrace['moves'][$direction]['area'] = $area;
            $this->debugTrace['moves'][$direction]['escapeRoutes'] = $escapeRoutes;
            $this->debugTrace['moves'][$direction]['distanceToFood'] = $distanceToTarget;
            $this->debugTrace['moves'][$direction]['score'] = $score;
            $this->debugTrace['moves'][$direction]['collisionRisk'] = $collisionRisk;

            $validMoves[$direction] = $score;

            if ($collisionRisk <= 1) {
                // No collision risk (0) or we would win (1) - safe move
                $safeMoves[$direction] = $score;
            } else {
                // Collision risk 2 (tie) or 3 (lose) - risky move
                $riskyMoves[$direction] = ['area' => $area, 'score' => $score, 'risk' => $collisionRisk];
            }
        }

        // Record categorization in debug trace
        $this->debugTrace['categorization']['safeMoves'] = $safeMoves;
        $this->debugTrace['categorization']['riskyMoves'] = array_map(fn($m) => $m['score'], $riskyMoves);
        $this->debugTrace['categorization']['validMoves'] = $validMoves;

        // Select best move from the best available tier
        if (!empty($safeMoves)) {
            arsort($safeMoves);
            $selectedMove = key($safeMoves);
            $this->debugTrace['decision']['reason'] = sprintf(
                'Highest scoring safe move (score: %d)',
                $safeMoves[$selectedMove]
            );
            return $selectedMove;
        }

        if (!empty($riskyMoves)) {
            // Prefer lower risk (2=tie is better than 3=lose), then higher score
            uasort($riskyMoves, function ($a, $b) {
                if ($a['risk'] !== $b['risk']) {
                    return $a['risk'] - $b['risk'];
                }
                return $b['score'] - $a['score'];
            });

            $bestRisky = reset($riskyMoves);
            $selectedMove = key($riskyMoves);
            $riskLabels = [2 => 'tie', 3 => 'lose'];
            $this->debugTrace['decision']['reason'] = sprintf(
                'Best risky move (risk: %d=%s, score: %d) - no safe moves available',
                $bestRisky['risk'],
                $riskLabels[$bestRisky['risk']] ?? 'unknown',
                $bestRisky['score']
            );
            return $selectedMove;
        }

        if (!empty($validMoves)) {
            arsort($validMoves);
            $selectedMove = key($validMoves);
            $this->debugTrace['decision']['reason'] = sprintf(
                'Best valid move avoiding high-risk collision (score: %d)',
                $validMoves[$selectedMove]
            );
            return $selectedMove;
        }

        // No valid moves - pick any in-bounds direction
        foreach ($neighbors as $direction => $neighbor) {
            if (isset($grid[$neighbor[1]][$neighbor[0]])) {
                $this->debugTrace['decision']['reason'] = sprintf(
                    'No valid moves - chose %s as last in-bounds option',
                    $direction
                );
                return $direction;
            }
        }

        $this->debugTrace['decision']['reason'] = 'No valid moves at all - absolute fallback to down';
        return 'down'; // Absolute last resort
    }
}
