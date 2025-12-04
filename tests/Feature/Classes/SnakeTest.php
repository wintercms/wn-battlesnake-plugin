<?php

use Winter\Battlesnake\Classes\AsciiBoard;
use Winter\Battlesnake\Classes\CoordinateHelper;
use Winter\Battlesnake\Classes\Snake;

/**
 * Helper to create a Snake instance from ASCII board
 */
function createSnake(string $ascii, array $options = []): Snake
{
    $state = AsciiBoard::parse($ascii);
    return new Snake($state, array_merge(['logTurns' => false], $options));
}

// ===========================================
// Move Selection Tests
// ===========================================

it('avoids walls', function () {
    // Snake in top-left corner, can only go right or down
    $snake = createSnake('
        Y y . . .
        . . . . .
        . . . . .
    ');

    $move = $snake->move();

    expect($move['move'])->toBeIn(['right', 'down']);
});

it('avoids its own body', function () {
    // Snake curled up, only safe move is down
    $snake = createSnake('
        . . . . .
        . y y . .
        . Y y . .
        . . . . .
    ');

    $move = $snake->move();

    // Can go left, right, or down - but not up (into body)
    expect($move['move'])->not->toBe('up');
});

it('avoids enemy snake bodies', function () {
    // Enemy snake body directly to the right (adjacent)
    $snake = createSnake('
        . . . . .
        . Y a . .
        . . a . .
        . . A . .
    ');

    $move = $snake->move();

    // Should not move right into enemy body
    expect($move['move'])->not->toBe('right');
});

it('seeks food when hungry', function () {
    // Food is to the right, snake should prefer that direction
    $snake = createSnake('
        . . . . .
        . Y . F .
        . y . . .
        . y . . .
    ');

    // Set health low to trigger food-seeking
    $snake->you->health = 30;

    $move = $snake->move();

    // Should move toward food (right)
    expect($move['move'])->toBe('right');
});

it('prefers larger open areas', function () {
    // Enemy snake on left blocking that direction, open space on right
    $snake = createSnake('
        . . . . .
        . . . . .
        a Y . . .
        a . . . .
        A . . . .
    ');

    $move = $snake->move();

    // Should prefer right (more space) over left (blocked by snake)
    expect($move['move'])->toBeIn(['right', 'up', 'down']); // Any direction except left
    expect($move['move'])->not->toBe('left');
});

it('returns first valid direction when trapped', function () {
    // Completely surrounded by enemy snake - no safe move exists
    $snake = createSnake('
        a a a
        a Y a
        a a a
    ');

    $move = $snake->move();

    // When trapped but all directions are in bounds, returns 'up' (first in neighbors array)
    // The 'down' fallback only happens when all positions are out of bounds
    expect($move['move'])->toBe('up');
});

// ===========================================
// Collision Risk Tests
// ===========================================

it('detects safe moves with no nearby enemies', function () {
    $snake = createSnake('
        . . . . .
        . . . . .
        . . Y y .
        . . . . .
        . . . . .
    ');

    // Check directions around the head (2, 2)
    $riskUp = $snake->getCollisionRisk(2, 1);    // up in grid = y-1
    $riskDown = $snake->getCollisionRisk(2, 3);  // down in grid = y+1
    $riskLeft = $snake->getCollisionRisk(1, 2);

    expect($riskUp)->toBe(0);
    expect($riskDown)->toBe(0);
    expect($riskLeft)->toBe(0);
});

it('detects potential head-on collision risk with equal length', function () {
    // Enemy head one move away - could collide if both move toward each other
    // Both snakes have equal length (2)
    $snake = createSnake('
        . . . . .
        . . A . .
        . . a . .
        . . Y . .
        . . y . .
    ');

    // After Board normalization: enemy head at grid y=1, our head at grid y=3
    // If we move up (grid y=2) and enemy moves down (grid y=2), collision at (2, 2)
    $risk = $snake->getCollisionRisk(2, 2);

    // Both snakes have length 2, so equal length = risk level 2 (tie)
    expect($risk)->toBe(2);
});

it('returns lower risk when we are longer', function () {
    // Our snake is longer than enemy
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . y . .
        . . y . .
    ');

    // Position where heads could collide
    $risk = $snake->getCollisionRisk(2, 2);

    // We are longer (3 vs 1), so risk level 1 (we would win)
    expect($risk)->toBe(1);
});

it('returns higher risk when enemy is longer', function () {
    // Enemy snake is longer (3) than us (1)
    // Snakes close enough that moves can intersect
    $snake = createSnake('
        . . A . .
        . . a . .
        . . a . .
        . . . . .
        . . Y . .
    ');

    // After normalization (height=5): enemy head at grid y=0, our head at grid y=4
    // Enemy possible moves include grid (2, 1)
    // Our move up would be to grid (2, 3), not intersecting
    // But if we check position where enemy CAN reach...
    // Position (2, 1) is reachable by enemy moving down
    $risk = $snake->getCollisionRisk(2, 1);

    // Enemy is longer (3 vs 1), so risk level 3 (we would lose)
    expect($risk)->toBe(3);
});

// ===========================================
// Flood Fill Tests
// ===========================================

it('counts accessible area correctly', function () {
    $snake = createSnake('
        . . . . .
        . . . . .
        . . Y y .
        . . . . .
        . . . . .
    ');

    $grid = $snake->board->toArray();

    // From position (1, 2), count accessible cells
    $area = $snake->floodFill(1, 2, $grid);

    // Should be able to reach most of the board (minus snake body)
    expect($area)->toBeGreaterThan(15);
});

it('returns small area when boxed in', function () {
    // Snake in a corner with enemy blocking escape
    $snake = createSnake('
        Y . a a a
        . . a . .
        a a a . .
        . . . . .
        . . . . .
    ');

    $grid = $snake->board->toArray();

    // From position (1, 4) in normalized coords (top-left area), we're boxed in
    $area = $snake->floodFill(1, 0, $grid);

    // Very limited area accessible
    expect($area)->toBeLessThan(5);
});

it('correctly compares areas in different directions', function () {
    // Snake with clearly different areas left vs right
    // Left has 2 cells, right has much more open space
    $snake = createSnake('
        . . a . . . . .
        . . a . . . . .
        . . a Y . . . .
        . . a . . . . .
        . . a . . . . .
    ');

    $grid = $snake->board->toArray();

    // Snake head is at x=3, after normalization
    // Check area to the left (x=2) - should be blocked by wall of 'a'
    // Check area to the right (x=4) - should be open
    $areaLeft = $snake->floodFill(2, 2, $grid);
    $areaRight = $snake->floodFill(4, 2, $grid);

    // Right should have significantly more area than left
    expect($areaRight)->toBeGreaterThan($areaLeft);
    expect($areaRight)->toBeGreaterThan(10); // Lots of open space
    expect($areaLeft)->toBe(0); // Left is blocked by snake body
});

it('picks direction with more open space', function () {
    // Snake with wall blocking left, much more open space to right
    // This tests that flood fill correctly identifies area differences
    $snake = createSnake('
        a . . . . . . .
        a . . . . . . .
        a Y . . . . . .
        a y . . . . . .
        a a . . . . . .
    ');

    $move = $snake->move();

    // Left is blocked by wall ('a' characters)
    // Up and right have large open areas
    // Down is blocked by body and wall
    expect($move['move'])->toBeIn(['up', 'right']);
});

it('prefers center when areas are equal', function () {
    // Snake in corner with equal areas in multiple directions
    // Center-seeking should prefer moving toward board center
    $snake = createSnake('
        Y . . . . . . .
        . . . . . . . .
        . . . . . . . .
        . . . . . . . .
        . . . . . . . .
        . . . . . . . .
        . . . . . . . .
        . . . . . . . .
    ');

    $move = $snake->move();

    // From top-left corner, right and down both move toward center
    // (up and left would go out of bounds)
    expect($move['move'])->toBeIn(['right', 'down']);
});

it('prefers open space over narrow corridor with same total area', function () {
    // Both left and right have the same total reachable area (connected via bottom)
    // But left is only accessible through a 1-cell corridor, while right is wide open
    // The snake should prefer right due to higher "immediate openness"
    $snake = createSnake('
        . # . . . . .
        . # . . . . .
        . # Y . . . .
        . # y . . . .
        . . . . . . .
        . . . . . . .
        . . . . . . .
    ');

    $move = $snake->move();

    // Right should be preferred because it has more immediate open space
    // Left requires going through a narrow 1-cell corridor
    expect($move['move'])->toBe('right');

    // Verify the immediateArea values in debug trace
    expect($snake->debugTrace['moves']['right']['immediateArea'])
        ->toBeGreaterThan($snake->debugTrace['moves']['left']['immediateArea']);
});

it('detects corridor constriction even when total areas are equal', function () {
    // A more complex scenario: snake can go left (corridor) or right (open)
    // Both ultimately access the same total area, but immediate openness differs
    $snake = createSnake('
        . . . . . . . . .
        . # # # # # # . .
        . # . . . . # . .
        . # . Y . . # . .
        . # . y . . # . .
        . # . . . . # . .
        . # # # . # # . .
        . . . . . . . . .
        . . . . . . . . .
    ');

    $move = $snake->move();

    // Snake is in the middle of a chamber with exits on both sides
    // The right side of the chamber is more open
    expect($snake->debugTrace['moves']['right']['immediateArea'])
        ->toBeGreaterThanOrEqual($snake->debugTrace['moves']['left']['immediateArea']);
});

// ===========================================
// Death Cause Determination Tests
// ===========================================

it('detects starvation when health is zero', function () {
    $snake = createSnake('
        . . . . .
        . . . . .
        . . Y y .
        . . . . .
        . . . . .
    ');

    // Set health to 0 to simulate starvation
    $snake->you->health = 0;

    $cause = $snake->determineDeathCause();

    expect($cause)->toBe('starvation');
});

it('detects wall collision when head is out of bounds left', function () {
    // Create a snake where the head position is out of bounds
    // We'll manually manipulate the head position after creation
    $snake = createSnake('
        . . . . .
        . . . . .
        Y y . . .
        . . . . .
        . . . . .
    ');

    // Simulate snake moved left out of bounds
    $snake->you->head = ['x' => -1, 'y' => 2];

    $cause = $snake->determineDeathCause();

    expect($cause)->toBe('wall_collision');
});

it('detects wall collision when head is out of bounds right', function () {
    $snake = createSnake('
        . . . . .
        . . . . .
        . . . y Y
        . . . . .
        . . . . .
    ');

    // Simulate snake moved right out of bounds
    $snake->you->head = ['x' => 5, 'y' => 2];

    $cause = $snake->determineDeathCause();

    expect($cause)->toBe('wall_collision');
});

it('detects wall collision when head is out of bounds top', function () {
    $snake = createSnake('
        . . Y . .
        . . y . .
        . . . . .
        . . . . .
        . . . . .
    ');

    // Simulate snake moved up out of bounds (in normalized coords, y=-1)
    $snake->you->head = ['x' => 2, 'y' => -1];

    $cause = $snake->determineDeathCause();

    expect($cause)->toBe('wall_collision');
});

it('detects wall collision when head is out of bounds bottom', function () {
    $snake = createSnake('
        . . . . .
        . . . . .
        . . . . .
        . . y . .
        . . Y . .
    ');

    // Simulate snake moved down out of bounds
    $snake->you->head = ['x' => 2, 'y' => 5];

    $cause = $snake->determineDeathCause();

    expect($cause)->toBe('wall_collision');
});

it('detects self collision', function () {
    // Snake that has collided with itself (head occupies same position as body)
    $snake = createSnake('
        . . . . .
        . y y . .
        . y Y . .
        . . . . .
        . . . . .
    ');

    // Move head to position occupied by body
    $snake->you->head = ['x' => 1, 'y' => 1];

    $cause = $snake->determineDeathCause();

    expect($cause)->toBe('self_collision');
});

it('detects snake collision with enemy body', function () {
    // Snake whose head is at the same position as an enemy body segment
    $snake = createSnake('
        . . . . .
        . . A . .
        . . a . .
        . Y a . .
        . . . . .
    ');

    // Move our head to position occupied by enemy body
    // Enemy snake 'a' body is at x=2, y=2 and y=3 in normalized coords
    $snake->you->head = ['x' => 2, 'y' => 2];

    $cause = $snake->determineDeathCause();

    expect($cause)->toBe('snake_collision');
});

it('detects head-on collision', function () {
    // Two snakes whose heads occupy the same position
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . . . .
    ');

    // Move both heads to same position
    $snake->you->head = ['x' => 2, 'y' => 2];

    // Update enemy snake head to same position
    foreach ($snake->board->snakes as $boardSnake) {
        if ($boardSnake->id !== $snake->you->id) {
            $boardSnake->head = ['x' => 2, 'y' => 2];
            break;
        }
    }

    $cause = $snake->determineDeathCause();

    expect($cause)->toBe('head_collision');
});

it('returns unknown when no cause can be determined', function () {
    // Snake in valid position with healthy stats
    $snake = createSnake('
        . . . . .
        . . . . .
        . . Y y .
        . . . . .
        . . . . .
    ');

    $cause = $snake->determineDeathCause();

    expect($cause)->toBe('unknown');
});

// ===========================================
// Dangerous Move Detection Tests
// ===========================================

it('detects dangerous move when enemy can reach same cell', function () {
    // Enemy head is adjacent, could move to same cell as us
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . . . .
    ');

    // Position (2, 2) can be reached by enemy (at 2, 1) moving down
    $isDangerous = $snake->isDangerousMove(2, 2);

    expect($isDangerous)->toBeTrue();
});

it('reports safe when no enemy can reach the cell', function () {
    // Enemy is far away
    $snake = createSnake('
        A . . . .
        . . . . .
        . . . . .
        . . . . .
        . . . . Y
    ');

    // Position (3, 4) cannot be reached by enemy (at 0, 0)
    $isDangerous = $snake->isDangerousMove(3, 4);

    expect($isDangerous)->toBeFalse();
});

it('reports safe when we are longer than nearby enemy', function () {
    // We are longer (3 segments) than enemy (1 segment)
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . y . .
        . . y . .
    ');

    // Position where enemy could move, but we're longer so we'd win
    $isDangerous = $snake->isDangerousMove(2, 2);

    expect($isDangerous)->toBeFalse();
});

// ===========================================
// Self-Collision Prevention Tests (Neck Check)
// ===========================================

it('never reverses into own neck when moving right', function () {
    // Snake moving right, should not reverse to left
    $snake = createSnake('
        . . . . .
        . . . . .
        . y Y . .
        . . . . .
        . . . . .
    ');

    $move = $snake->move();

    // Should never go left (into neck)
    expect($move['move'])->not->toBe('left');
});

it('never reverses into own neck when moving left', function () {
    // Snake moving left, should not reverse to right
    $snake = createSnake('
        . . . . .
        . . . . .
        . . Y y .
        . . . . .
        . . . . .
    ');

    $move = $snake->move();

    // Should never go right (into neck)
    expect($move['move'])->not->toBe('right');
});

it('never reverses into own neck when moving down', function () {
    // Snake moving down (head below neck in grid)
    $snake = createSnake('
        . . . . .
        . . y . .
        . . Y . .
        . . . . .
        . . . . .
    ');

    $move = $snake->move();

    // Should never go up (into neck)
    expect($move['move'])->not->toBe('up');
});

it('never reverses into own neck when moving up', function () {
    // Snake moving up (head above neck in grid)
    $snake = createSnake('
        . . . . .
        . . Y . .
        . . y . .
        . . . . .
        . . . . .
    ');

    $move = $snake->move();

    // Should never go down (into neck)
    expect($move['move'])->not->toBe('down');
});

// ===========================================
// Escape Route Scoring Tests
// ===========================================

it('counts escape routes correctly for open position', function () {
    $snake = createSnake('
        . . . . .
        . . . . .
        . . Y y .
        . . . . .
        . . . . .
    ');

    $grid = $snake->board->toArray();

    // From position (1, 2) - should have 3 escape routes (up, down, left)
    // Right is blocked by body
    $routes = $snake->countEscapeRoutes(1, 2, $grid);

    expect($routes)->toBe(3);
});

it('counts zero escape routes when boxed in', function () {
    // Snake in a tiny corner, boxed in by enemy
    $snake = createSnake('
        Y a . . .
        a a . . .
        . . . . .
        . . . . .
        . . . . .
    ');

    $grid = $snake->board->toArray();

    // From position (0, 0), all routes blocked by enemy snake
    $routes = $snake->countEscapeRoutes(0, 0, $grid);

    expect($routes)->toBe(0);
});

it('chooses escape route over dead end', function () {
    // Simulating the Turn 258 problem: down leads to dead end, up to open space
    $snake = createSnake('
        . . . . . . . . . . F
        . a a a a a . . . . .
        . a . . . a . . . . .
        . a . . . . . . . . F
        . a . . . . . . . . .
        . a a a a A . F y Y .
        y y a a . . . . y . .
        y y y . . . . . y . .
        y y y . . . . . y . .
        . y y y . . . . y . .
        . F . y y y y y y . .
    ');

    $move = $snake->move();

    // Should move up (has escape routes to top-right quadrant)
    // NOT down (leads into our own tail, a trap)
    expect($move['move'])->toBe('up');
});

// ===========================================
// Head-Collision Avoidance Tests
// ===========================================

it('avoids risky head collision when safe option exists', function () {
    // Enemy snake directly above - if we move up, we could collide
    // Left and right are safe moves with no collision risk
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . y . .
    ');

    $move = $snake->move();

    // Up leads to position (2, 2) which enemy can reach
    // Left (1, 3) and right (3, 3) are safer - no collision risk
    // Down is into our body
    // Snake should avoid 'up' and 'down', prefer 'left' or 'right'
    expect($move['move'])->toBeIn(['left', 'right']);
});

it('prefers winning collision over losing collision', function () {
    // Test that we correctly identify risk levels based on snake lengths
    // Our snake (length 3) vs Enemy A (length 1) - we'd win
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . y . .
        . . y . .
    ');

    // Position (2, 2) can be reached by enemy A (at 2, 1) moving down
    // We have length 3, enemy has length 1 - we'd win
    $riskWin = $snake->getCollisionRisk(2, 2);
    expect($riskWin)->toBe(1); // We'd win against smaller snake

    // Now test against a larger snake - position must be reachable!
    // Enemy B head at (0, 1) can move to (0, 0), (0, 2), (1, 1)
    $snake2 = createSnake('
        . . . . .
        B b b b .
        . . . . .
        . . Y . .
        . . y . .
    ');

    // Position (1, 1) can be reached by enemy B (at 0, 1) moving right
    // We have length 2, enemy has length 4 - we'd lose
    $riskLose = $snake2->getCollisionRisk(1, 1);
    expect($riskLose)->toBe(3); // We'd lose against larger snake
});

it('treats winning collision as safe move not risky move', function () {
    // Verify that collision risk 1 (we would WIN) is categorized as safe, not risky
    // This is the key fix: before, winning collisions were avoided in favor of
    // lower-scoring "safe" moves, causing snakes to miss advantageous plays
    //
    // Board layout:
    // - Our snake (Y) length 5 in top-left
    // - Enemy snake (A) length 2 below us - we'd win a collision going down
    $snake = createSnake('
        . . . . . . . . . . .
        . Y y y y . . . . . .
        . . . . y . . . . . .
        . A a . . . . . . . .
        . . . . . . . . . . .
        . . . . . . . . . . .
        . . . . . . . . . . .
        . . . . . . . . . . .
        . . . . . . . . . . .
        . . . . . . . . . . .
        . . . . . . . . . . .
    ');

    $snake->move();

    // Down (1,2) can be reached by enemy A at (1,3) moving up
    // We have length 5, enemy has length 2 - collision risk should be 1 (we'd WIN)
    expect($snake->debugTrace['moves']['down']['collisionRisk'])->toBe(1);

    // KEY FIX: Winning collision (risk 1) should be in safeMoves, NOT riskyMoves
    // Before fix: risk 1 was treated as risky and avoided
    // After fix: risk 1 is treated as safe (winning is advantageous)
    expect($snake->debugTrace['categorization']['safeMoves'])->toHaveKey('down');
    expect($snake->debugTrace['categorization']['riskyMoves'])->not->toHaveKey('down');
});

// ===========================================
// Configurable Strategy Tests
// ===========================================

it('respects custom health threshold for food seeking', function () {
    // Food available, test different health thresholds
    $snake = createSnake('
        . . . . .
        . F . . .
        . . Y y .
        . . . . .
        . . . . .
    ', ['strategy' => ['healthThreshold' => 80]]);

    // With health at 70 (below 80), should seek food
    $snake->you->health = 70;
    $moveHungry = $snake->move();

    // With health at 90 (above 80), should maximize space
    $snake2 = createSnake('
        . . . . .
        . F . . .
        . . Y y .
        . . . . .
        . . . . .
    ', ['strategy' => ['healthThreshold' => 80]]);
    $snake2->you->health = 90;
    $moveHealthy = $snake2->move();

    // Both are valid moves, but they use different strategies
    expect($moveHungry['move'])->toBeIn(['up', 'left', 'down']);
    expect($moveHealthy['move'])->toBeIn(['up', 'left', 'down']);
});

it('uses default strategy values when no overrides provided', function () {
    $snake = createSnake('
        . . . . .
        . . . . .
        . . Y y .
        . . . . .
        . . . . .
    ');

    expect($snake->strategy['healthThreshold'])->toBe(50);
    expect($snake->strategy['aggression'])->toBe(0.0);
    expect($snake->strategy['escapeRouteWeight'])->toBe(2.0);
});

it('merges strategy overrides correctly', function () {
    $snake = createSnake('
        . . . . .
        . . . . .
        . . Y y .
        . . . . .
        . . . . .
    ', ['strategy' => [
        'healthThreshold' => 75,
        'aggression' => 0.8,
    ]]);

    expect($snake->strategy['healthThreshold'])->toBe(75);
    expect($snake->strategy['aggression'])->toBe(0.8);
    // Unmodified values should remain default
    expect($snake->strategy['escapeRouteWeight'])->toBe(2.0);
});

// ===========================================
// Enemy Movement Prediction Tests
// ===========================================

it('predicts enemy cannot reverse into neck', function () {
    // Enemy moving right
    $snake = createSnake('
        . . . . .
        . a A . .
        . . . . .
        . . Y y .
        . . . . .
    ');

    $enemy = null;
    foreach ($snake->board->snakes as $s) {
        if ($s->id !== $snake->you->id) {
            $enemy = $s;
            break;
        }
    }

    $likelihoods = $snake->predictEnemyMoveLikelihood($enemy);

    // Enemy cannot reverse left (into neck)
    expect($likelihoods['left'])->toBe(0.0);
    // Other directions should have non-zero probability
    expect($likelihoods['up'] + $likelihoods['down'] + $likelihoods['right'])->toBeGreaterThan(0.9);
});

it('returns enemy likelihood at position', function () {
    // Enemy head adjacent to test position
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y y .
        . . . . .
    ');

    // Position directly below enemy head - enemy can move there
    $likelihood = $snake->getEnemyLikelihoodAtPosition(2, 2);

    // Should have some probability (enemy could move down)
    expect($likelihood)->toBeGreaterThan(0.0);
});

it('returns zero likelihood for unreachable position', function () {
    // Enemy head far from test position
    $snake = createSnake('
        A . . . .
        . . . . .
        . . . . .
        . . . . .
        . . . . Y
    ');

    // Position (4, 4) - far from enemy at (0, 0)
    $likelihood = $snake->getEnemyLikelihoodAtPosition(4, 4);

    expect($likelihood)->toBe(0.0);
});

// ===========================================
// Aggression Mode Tests
// ===========================================

it('finds aggression target when enabled and snake is larger', function () {
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . y . .
        . . y . .
    ', ['strategy' => ['aggression' => 0.8]]);

    // We have length 3, enemy has length 1 (at least 2 shorter)
    $target = $snake->getAggressionTarget();

    // Should find the smaller enemy as a target
    expect($target)->not->toBeNull();
    expect($target['x'])->toBe(2);
    expect($target['y'])->toBe(1); // Normalized grid coord
});

it('does not hunt when aggression is low', function () {
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . y . .
        . . y . .
    ', ['strategy' => ['aggression' => 0.2]]); // Below 0.3 threshold

    $target = $snake->getAggressionTarget();

    expect($target)->toBeNull();
});

it('does not hunt when hungry', function () {
    $snake = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . y . .
        . . y . .
    ', ['strategy' => ['aggression' => 0.8, 'healthThreshold' => 50]]);

    // Make snake hungry
    $snake->you->health = 30;

    $target = $snake->getAggressionTarget();

    // Should not hunt when hungry - survival first
    expect($target)->toBeNull();
});

it('does not target snakes that are not significantly smaller', function () {
    // Both snakes have same length
    $snake = createSnake('
        . . . . .
        . . A . .
        . . a . .
        . . Y . .
        . . y . .
    ', ['strategy' => ['aggression' => 0.8]]);

    // Both have length 2, not significantly smaller
    $target = $snake->getAggressionTarget();

    expect($target)->toBeNull();
});

// ===========================================
// Tail Movement Prediction Tests
// ===========================================

it('predicts tail stays when snake just ate', function () {
    $snake = createSnake('
        . . . . .
        . . A a .
        . . . . .
        . . Y y .
        . . . . .
    ');

    $enemy = null;
    foreach ($snake->board->snakes as $s) {
        if ($s->id !== $snake->you->id) {
            $enemy = $s;
            break;
        }
    }

    // Set health to 100 (just ate)
    $enemy->health = 100;

    $willMove = $snake->willTailLikelyMove($enemy);

    // Tail should NOT move when snake just ate
    expect($willMove)->toBeFalse();
});

it('predicts tail moves when snake is hungry', function () {
    $snake = createSnake('
        . . . . .
        . . A a .
        . . . . .
        . . Y y .
        . . . . .
    ');

    $enemy = null;
    foreach ($snake->board->snakes as $s) {
        if ($s->id !== $snake->you->id) {
            $enemy = $s;
            break;
        }
    }

    // Set health below 100 (hasn't just eaten)
    $enemy->health = 80;

    $willMove = $snake->willTailLikelyMove($enemy);

    // Tail should move when snake hasn't just eaten
    expect($willMove)->toBeTrue();
});

// ===========================================
// Direction Helper Tests
// ===========================================

it('gets direction between adjacent positions', function () {
    // Test all four directions using CoordinateHelper
    expect(CoordinateHelper::getDirectionBetween(['x' => 2, 'y' => 2], ['x' => 2, 'y' => 1]))->toBe('up');
    expect(CoordinateHelper::getDirectionBetween(['x' => 2, 'y' => 2], ['x' => 2, 'y' => 3]))->toBe('down');
    expect(CoordinateHelper::getDirectionBetween(['x' => 2, 'y' => 2], ['x' => 1, 'y' => 2]))->toBe('left');
    expect(CoordinateHelper::getDirectionBetween(['x' => 2, 'y' => 2], ['x' => 3, 'y' => 2]))->toBe('right');
});

it('returns null for non-adjacent positions', function () {
    // Diagonal positions are not adjacent
    expect(CoordinateHelper::getDirectionBetween(['x' => 2, 'y' => 2], ['x' => 3, 'y' => 3]))->toBeNull();

    // Far away positions
    expect(CoordinateHelper::getDirectionBetween(['x' => 0, 'y' => 0], ['x' => 4, 'y' => 4]))->toBeNull();
});

// ===========================================
// Regression Tests for Coordinate Normalization
// ===========================================

it('does not double-normalize neck coordinates', function () {
    $snake = createSnake('
        . . . .
        y y . .
        Y y . .
        . . . .
    ');

    $move = $snake->move();

    expect($snake->debugTrace['moves']['up']['rejectedReason'])->toBe('neck_collision');
    expect($snake->debugTrace['moves']['left']['rejectedReason'])->toBe('out_of_bounds');
    expect($snake->debugTrace['moves']['right']['rejectedReason'])->toBe('own_body');
    expect($snake->debugTrace['moves']['down']['valid'])->toBeTrue();
    expect($snake->debugTrace['moves']['down']['rejectedReason'])->toBeNull();

    // Snake should choose 'down' as the only valid move
    expect($move['move'])->toBe('down');
});

// ===========================================
// Strategy Parameter Behavioral Tests
// ===========================================

it('applies spaceWeight to area scoring', function () {
    // Same scenario, different spaceWeight values
    $snake1 = createSnake('
        . . . . . . .
        . # . . . . .
        . # Y . . . .
        . # y . . . .
        . . . . . . .
    ', ['strategy' => ['spaceWeight' => 0.5]]);

    $snake2 = createSnake('
        . . . . . . .
        . # . . . . .
        . # Y . . . .
        . # y . . . .
        . . . . . . .
    ', ['strategy' => ['spaceWeight' => 2.0]]);

    $move1 = $snake1->move();
    $move2 = $snake2->move();

    // Higher spaceWeight should give higher absolute scores (area is multiplied by weight)
    // Compare scores for the same valid direction
    $score1 = $snake1->debugTrace['moves']['right']['score'];
    $score2 = $snake2->debugTrace['moves']['right']['score'];

    // With double the weight, area contribution should be higher
    expect($score2)->toBeGreaterThan($score1);
});

it('applies foodWeight to food-seeking urgency', function () {
    // Snake hungry with food nearby - different foodWeight values
    $snake1 = createSnake('
        . . . . . . .
        . . . . . . .
        . . Y . . . .
        . . y . . . .
        . F . . . . .
    ', ['strategy' => ['foodWeight' => 0.5]]);
    $snake1->you->health = 30;

    $snake2 = createSnake('
        . . . . . . .
        . . . . . . .
        . . Y . . . .
        . . y . . . .
        . F . . . . .
    ', ['strategy' => ['foodWeight' => 2.0]]);
    $snake2->you->health = 30;

    $move1 = $snake1->move();
    $move2 = $snake2->move();

    // Higher foodWeight should make the distance-to-food penalty more significant
    // The direction toward food should be more favored with higher weight
    // (or the direction away from food should be more penalized)
    $leftScore1 = $snake1->debugTrace['moves']['left']['score'] ?? 0;
    $rightScore1 = $snake1->debugTrace['moves']['right']['score'] ?? 0;

    $leftScore2 = $snake2->debugTrace['moves']['left']['score'] ?? 0;
    $rightScore2 = $snake2->debugTrace['moves']['right']['score'] ?? 0;

    // The difference between left (toward food) and right (away from food) scores
    // should be larger with higher foodWeight
    $diff1 = $leftScore1 - $rightScore1;
    $diff2 = $leftScore2 - $rightScore2;

    expect($diff2)->toBeGreaterThanOrEqual($diff1);
});

it('applies escapeRouteWeight to escape route scoring', function () {
    // Position where one direction has more escape routes
    $snake1 = createSnake('
        . . . . . . .
        . . . . . . .
        . . Y . . . .
        . . y # . . .
        . . . # . . .
    ', ['strategy' => ['escapeRouteWeight' => 0.5]]);

    $snake2 = createSnake('
        . . . . . . .
        . . . . . . .
        . . Y . . . .
        . . y # . . .
        . . . # . . .
    ', ['strategy' => ['escapeRouteWeight' => 5.0]]);

    $move1 = $snake1->move();
    $move2 = $snake2->move();

    // Higher escapeRouteWeight should give higher scores to directions with more exits
    $scoreUp1 = $snake1->debugTrace['moves']['up']['score'];
    $scoreUp2 = $snake2->debugTrace['moves']['up']['score'];

    expect($scoreUp2)->toBeGreaterThan($scoreUp1);
});

it('applies opennessWeight to open space preference', function () {
    // Narrow corridor vs open space - different opennessWeight values
    $snake1 = createSnake('
        . # . . . . .
        . # . . . . .
        . # Y . . . .
        . # y . . . .
        . . . . . . .
    ', ['strategy' => ['opennessWeight' => 0.0]]);

    $snake2 = createSnake('
        . # . . . . .
        . # . . . . .
        . # Y . . . .
        . # y . . . .
        . . . . . . .
    ', ['strategy' => ['opennessWeight' => 2.0]]);

    $move1 = $snake1->move();
    $move2 = $snake2->move();

    // With opennessWeight=0, the immediate openness has no effect
    // With opennessWeight=2, open directions should score higher
    $rightScore1 = $snake1->debugTrace['moves']['right']['score'];
    $rightScore2 = $snake2->debugTrace['moves']['right']['score'];

    // Right is more open, so with higher opennessWeight it should score better
    expect($rightScore2)->toBeGreaterThan($rightScore1);
});

it('applies centerPreference to center-seeking behavior', function () {
    // Snake in corner - different centerPreference values
    $snake1 = createSnake('
        Y . . . . . .
        . . . . . . .
        . . . . . . .
        . . . . . . .
        . . . . . . .
    ', ['strategy' => ['centerPreference' => 0.0]]);

    $snake2 = createSnake('
        Y . . . . . .
        . . . . . . .
        . . . . . . .
        . . . . . . .
        . . . . . . .
    ', ['strategy' => ['centerPreference' => 1.0]]);

    $move1 = $snake1->move();
    $move2 = $snake2->move();

    // With centerPreference=0, center bonus should be 0
    // With centerPreference=1, moves toward center (right, down) should score higher
    // Since snake is at top-left corner, right and down go toward center

    // Get the scores after center bonus is applied (these are in categorization)
    $rightScore1 = $snake1->debugTrace['categorization']['safeMoves']['right'] ?? 0;
    $rightScore2 = $snake2->debugTrace['categorization']['safeMoves']['right'] ?? 0;

    // With higher centerPreference, center-bound moves should score higher
    expect($rightScore2)->toBeGreaterThanOrEqual($rightScore1);
});

it('applies avoidLargerSnakes penalty near larger snake heads', function () {
    // Larger enemy snake nearby
    $snake1 = createSnake('
        . . . . .
        . A a a .
        . . . . .
        . Y . . .
        . y . . .
    ', ['strategy' => ['avoidLargerSnakes' => false]]);

    $snake2 = createSnake('
        . . . . .
        . A a a .
        . . . . .
        . Y . . .
        . y . . .
    ', ['strategy' => ['avoidLargerSnakes' => true]]);

    $move1 = $snake1->move();
    $move2 = $snake2->move();

    // With avoidLargerSnakes=true, moving up (toward larger snake's head) should be penalized
    $upScore1 = $snake1->debugTrace['moves']['up']['score'];
    $upScore2 = $snake2->debugTrace['moves']['up']['score'];

    // The snake2 should have a penalty applied for 'up' direction
    expect($upScore2)->toBeLessThan($upScore1);

    // Check that the penalty was actually recorded
    expect($snake2->debugTrace['moves']['up']['largerSnakePenalty'] ?? 0)->toBeGreaterThan(0);
});

it('applies enemyPredictionWeight penalty for enemy reachable positions', function () {
    // Enemy can move to a position we might move to
    $snake1 = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . y . .
    ', ['strategy' => ['enemyPredictionWeight' => 0.0]]);

    $snake2 = createSnake('
        . . . . .
        . . A . .
        . . . . .
        . . Y . .
        . . y . .
    ', ['strategy' => ['enemyPredictionWeight' => 1.0]]);

    $move1 = $snake1->move();
    $move2 = $snake2->move();

    // With enemyPredictionWeight=0, no penalty for enemy positions
    // With enemyPredictionWeight=1, moves toward enemy should be penalized
    // Position (2, 2) can be reached by enemy moving down

    $upScore1 = $snake1->debugTrace['moves']['up']['score'];
    $upScore2 = $snake2->debugTrace['moves']['up']['score'];

    // The snake2 should have a penalty applied for positions enemy could reach
    expect($upScore2)->toBeLessThan($upScore1);

    // Check that the enemy penalty was recorded
    expect($snake2->debugTrace['moves']['up']['enemyPenalty'] ?? 0)->toBeGreaterThan(0);
});

// ===========================================
// Trap Avoidance Bug Fix Tests
// ===========================================

it('does not penalize larger areas more heavily with trap avoidance', function () {
    // Scenario: Both moves have 0 escape routes, but one has much more area
    // The move with more area should be preferred (not penalized more)
    // This is a multi-snake game so trap avoidance is active
    $snake = createSnake('
        . . . . . . . . . . .
        y y . . . . . . . . .
        y y . . . . . . . . .
        y y y . . . . . . y y
        . . y y . . . . y y y
        . . . y y A y y y y y
        . . . y y . y y y y .
        . . y y . y y y y y Y
        . y y . y y . . . . .
        . y y y y . . . . . .
        . . y y . . . . . . .
    ', ['strategy' => ['trapAvoidance' => 1.0]]);

    $move = $snake->move();

    // Down has more area than up - should choose down even with trap avoidance
    // The bug was: larger areas got bigger penalties, inverting the decision
    expect($move['move'])->toBe('down');
});

it('disables trap avoidance in solo snake games', function () {
    // Solo snake - trap avoidance should not apply
    // because solo snakes naturally fill the board with their body
    $snake = createSnake('
        . . . . . . .
        . . . Y y y .
        . . . . . y .
        . . . . . y .
        . . . . . y y
    ', ['strategy' => ['trapAvoidance' => 1.0]]);

    // Verify this is a solo game
    expect(count($snake->board->snakes))->toBe(1);

    $move = $snake->move();

    // Without trap avoidance interfering, should pick based on area
    // Left and down both have significant area
    expect($move['move'])->toBeIn(['left', 'down']);

    // Verify no trap penalty was applied (solo game = no trap avoidance)
    $trapPenalty = $snake->debugTrace['moves'][$move['move']]['trapPenalty'] ?? 0;
    expect($trapPenalty)->toBe(0);
});

// ===========================================
// Missing Strategy Parameter Tests
// ===========================================

it('applies lookaheadDepth to predict tail movement opening space', function () {
    // Snake in very constrained area where area < length*2
    // Snake length is 5, so we need area < 10 for prediction to trigger
    // This board has the snake boxed in with very little room
    $snake = createSnake('
        y y y y y
        y Y . . y
        y y y y y
    ', ['strategy' => ['lookaheadDepth' => 2]]);

    $snake->move();

    // With lookaheadDepth > 0 and area < length*2 (area < 10), predictedArea should be calculated
    $hasPredictedArea = false;
    foreach ($snake->debugTrace['moves'] as $data) {
        if (isset($data['predictedArea'])) {
            $hasPredictedArea = true;
            break;
        }
    }

    expect($hasPredictedArea)->toBeTrue();
});

it('applies targetLead to raise health threshold when below target lead', function () {
    // Our snake (2 segments) vs opponent (4 segments)
    // With targetLead=2, we want to be 6 to have a 2-unit lead
    // gapFromTarget = (4 + 2) - 2 = 4
    $snake = createSnake('
        . . . . . F . . .
        . . . . . . . . .
        . . Y y . A a a a
        . . . . . . . . .
    ', ['strategy' => ['targetLead' => 2, 'growthPriority' => 0.5, 'healthThreshold' => 50]]);

    $snake->move();

    // Verify snake sizes are parsed correctly
    expect($snake->you->length)->toBe(2);

    // Find opponent length
    $opponentLength = 0;
    foreach ($snake->board->snakes as $s) {
        if ($s->id !== $snake->you->id) {
            $opponentLength = $s->length;
        }
    }
    expect($opponentLength)->toBe(4);

    // gapFromTarget = (4 + 2) - 2 = 4
    // threshold boost = 4 * 0.5 * 8 = 16
    // effective threshold = 50 + 16 = 66
    expect($snake->debugTrace['gapFromTarget'])->toBe(4);
    expect($snake->debugTrace['effectiveThreshold'])->toEqual(66);
});

it('does not raise threshold when at or above target lead', function () {
    // Our snake (6 segments) vs opponent (4 segments)
    // With targetLead=2, we have exactly 2-unit lead (6-4=2)
    // gapFromTarget = (4 + 2) - 6 = 0
    $snake = createSnake('
        . . . . . . . . .
        Y y y y y y . . .
        . . . . . A a a a
        . . . . . . . . .
    ', ['strategy' => ['targetLead' => 2, 'growthPriority' => 0.5, 'healthThreshold' => 50]]);

    $snake->move();

    // gapFromTarget = (4 + 2) - 6 = 0 (at target)
    // No boost applied
    expect($snake->debugTrace['gapFromTarget'])->toBe(0);
    expect($snake->debugTrace['effectiveThreshold'])->toEqual(50);
});

it('with targetLead=0 only catches up to tie', function () {
    // Our snake (3 segments) vs opponent (5 segments)
    // With targetLead=0, we only want to match their size
    // gapFromTarget = (5 + 0) - 3 = 2
    $snake = createSnake('
        . . . . . . . . . .
        . Y y y . A a a a a
        . . . . . . . . . .
    ', ['strategy' => ['targetLead' => 0, 'growthPriority' => 0.5, 'healthThreshold' => 50]]);

    $snake->move();

    // gapFromTarget = (5 + 0) - 3 = 2
    // threshold boost = 2 * 0.5 * 8 = 8
    expect($snake->debugTrace['gapFromTarget'])->toBe(2);
    expect($snake->debugTrace['effectiveThreshold'])->toEqual(58);
});

it('does not penalize exceeding target lead', function () {
    // Our snake (8 segments) vs opponent (3 segments) - we have 5-unit lead
    // With targetLead=2, gapFromTarget = (3 + 2) - 8 = -3 (exceeds target)
    $snake = createSnake('
        . . . . . . . . . . .
        Y y y y y y y y . . .
        . . . . . . A a a . .
        . . . . . . . . . . .
    ', ['strategy' => ['targetLead' => 2, 'growthPriority' => 0.5, 'healthThreshold' => 50]]);

    $snake->move();

    // gapFromTarget should be negative (we exceed target)
    expect($snake->debugTrace['gapFromTarget'])->toBeLessThan(0);
    // No boost when exceeding target
    expect($snake->debugTrace['effectiveThreshold'])->toEqual(50);
});

it('uses default targetLead value of 2', function () {
    $snake = createSnake('
        . . . . .
        . . . . .
        . . Y y .
        . . . . .
        . . . . .
    ');

    expect($snake->strategy['targetLead'])->toBe(2);
});

it('high targetLead makes snake seek food even when slightly ahead', function () {
    // Our snake (5 segments) vs opponent (4 segments) - 1-unit lead
    // With targetLead=5, we want to be 9 (4+5)
    // gapFromTarget = (4 + 5) - 5 = 4
    $snake = createSnake('
        . . . . . . . . .
        . Y y y y y F . .
        . . . . A a a a .
        . . . . . . . . .
    ', ['strategy' => ['targetLead' => 5, 'growthPriority' => 1.0, 'healthThreshold' => 50]]);

    $snake->move();

    // gapFromTarget = (4 + 5) - 5 = 4
    // threshold boost = 4 * 1.0 * 8 = 32
    // effective threshold = 50 + 32 = 82
    expect($snake->debugTrace['effectiveThreshold'])->toEqual(82);
});

it('applies trappingAggression bonus for moves that trap opponents', function () {
    // We're larger (4 segments) and can potentially cut off opponent
    $snake = createSnake('
        . . . . . . .
        . . . . . . .
        . . Y y y y .
        . . . . . . A
        . . . . . . .
    ', ['strategy' => ['trappingAggression' => 1.0]]);

    $snake->move();

    // Check that trapping bonus is calculated for at least one move
    $hasTrappingBonus = false;
    foreach ($snake->debugTrace['moves'] as $dir => $data) {
        if (isset($data['trappingBonus']) && $data['trappingBonus'] > 0) {
            $hasTrappingBonus = true;
            break;
        }
    }

    expect($hasTrappingBonus)->toBeTrue();
});
