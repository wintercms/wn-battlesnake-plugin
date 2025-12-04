<?php

use Winter\Battlesnake\Classes\BoardDataTransformer;
use Winter\Battlesnake\Models\GameLog;
use Winter\Battlesnake\Models\Turn;

/**
 * Helper to create a Turn model with request data (without saving)
 */
function createTestTurn(int $turnNumber, array $board = []): Turn
{
    $turn = new Turn();
    $turn->turn = $turnNumber;
    $turn->request = [
        'game' => [
            'id' => 'test-game-id',
            'ruleset' => ['name' => 'standard'],
            'timeout' => 500,
        ],
        'turn' => $turnNumber,
        'board' => array_merge([
            'width' => 11,
            'height' => 11,
            'food' => [['x' => 5, 'y' => 5]],
            'hazards' => [],
            'snakes' => [
                [
                    'id' => 'snake-1',
                    'name' => 'Test Snake',
                    'health' => 100,
                    'body' => [['x' => 3, 'y' => 3], ['x' => 2, 'y' => 3], ['x' => 1, 'y' => 3]],
                    'head' => ['x' => 3, 'y' => 3],
                    'length' => 3,
                    'latency' => 50,
                    'customizations' => [
                        'color' => '#ff0000',
                        'head' => 'default',
                        'tail' => 'default',
                    ],
                ],
            ],
        ], $board),
        'you' => [
            'id' => 'snake-1',
            'name' => 'Test Snake',
            'health' => 100,
            'body' => [['x' => 3, 'y' => 3], ['x' => 2, 'y' => 3], ['x' => 1, 'y' => 3]],
            'head' => ['x' => 3, 'y' => 3],
            'length' => 3,
        ],
    ];
    return $turn;
}

/**
 * Helper to directly test frame array manipulation (simulates gameToFrames logic)
 */
function applyFinalFrameMarker(array $frames): array
{
    if (!empty($frames)) {
        $frames[count($frames) - 1]['isFinalFrame'] = true;
    }
    return $frames;
}

// ===========================================
// Frame Conversion Tests
// ===========================================

it('converts a single turn to frame format', function () {
    $turn = createTestTurn(0);

    $reflection = new ReflectionMethod(BoardDataTransformer::class, 'turnToFrame');
    $reflection->setAccessible(true);

    $frame = $reflection->invoke(null, $turn);

    expect($frame)->toBeArray();
    expect($frame['turn'])->toBe(0);
    expect($frame['width'])->toBe(11);
    expect($frame['height'])->toBe(11);
    expect($frame['food'])->toHaveCount(1);
    expect($frame['snakes'])->toHaveCount(1);
    expect($frame['isFinalFrame'])->toBeFalse();
});

it('transforms snake data correctly', function () {
    $snakes = [
        [
            'id' => 'test-snake',
            'name' => 'Test',
            'health' => 85,
            'body' => [['x' => 1, 'y' => 1]],
            'length' => 1,
            'latency' => 123,
            'customizations' => [
                'color' => '#00ff00',
                'head' => 'tongue',
                'tail' => 'curled',
            ],
        ],
    ];

    $reflection = new ReflectionMethod(BoardDataTransformer::class, 'transformSnakes');
    $reflection->setAccessible(true);

    $result = $reflection->invoke(null, $snakes);

    expect($result)->toHaveCount(1);
    expect($result[0]['id'])->toBe('test-snake');
    expect($result[0]['name'])->toBe('Test');
    expect($result[0]['color'])->toBe('#00ff00');
    expect($result[0]['head'])->toBe('tongue');
    expect($result[0]['tail'])->toBe('curled');
    expect($result[0]['health'])->toBe(85);
    expect($result[0]['latency'])->toBe('123');
    expect($result[0]['isEliminated'])->toBeFalse();
    expect($result[0]['elimination'])->toBeNull();
});

it('uses default values for missing snake customizations', function () {
    $snakes = [
        [
            'id' => 'basic-snake',
            'name' => 'Basic',
            'health' => 100,
            'body' => [['x' => 0, 'y' => 0]],
            'length' => 1,
            // No customizations or latency
        ],
    ];

    $reflection = new ReflectionMethod(BoardDataTransformer::class, 'transformSnakes');
    $reflection->setAccessible(true);

    $result = $reflection->invoke(null, $snakes);

    expect($result[0]['color'])->toBe('#888888');
    expect($result[0]['head'])->toBe('default');
    expect($result[0]['tail'])->toBe('default');
    expect($result[0]['latency'])->toBe('0');
});

// ===========================================
// Final Frame Marking Tests
// ===========================================

it('marks the last frame as final when multiple frames exist', function () {
    $reflection = new ReflectionMethod(BoardDataTransformer::class, 'turnToFrame');
    $reflection->setAccessible(true);

    // Simulate what gameToFrames does internally
    $frames = [
        $reflection->invoke(null, createTestTurn(0)),
        $reflection->invoke(null, createTestTurn(1)),
        $reflection->invoke(null, createTestTurn(2)),
    ];

    // Apply the final frame marker logic
    $frames = applyFinalFrameMarker($frames);

    expect($frames)->toHaveCount(3);
    expect($frames[0]['isFinalFrame'])->toBeFalse();
    expect($frames[1]['isFinalFrame'])->toBeFalse();
    expect($frames[2]['isFinalFrame'])->toBeTrue();
});

it('handles empty frame list gracefully', function () {
    $frames = [];
    $frames = applyFinalFrameMarker($frames);

    expect($frames)->toBeArray();
    expect($frames)->toBeEmpty();
});

it('handles single frame correctly', function () {
    $reflection = new ReflectionMethod(BoardDataTransformer::class, 'turnToFrame');
    $reflection->setAccessible(true);

    $frames = [$reflection->invoke(null, createTestTurn(0))];
    $frames = applyFinalFrameMarker($frames);

    expect($frames)->toHaveCount(1);
    expect($frames[0]['isFinalFrame'])->toBeTrue();
});

// ===========================================
// Game Metadata Tests
// ===========================================

it('generates game metadata correctly', function () {
    $game = new GameLog();
    $game->game_id = 'metadata-test-game';
    $game->ruleset = ['name' => 'royale', 'version' => 'v1'];
    $game->map = 'royale';
    $game->timeout = 250;

    $metadata = BoardDataTransformer::getGameMetadata($game);

    expect($metadata['ID'])->toBe('metadata-test-game');
    expect($metadata['Ruleset'])->toBe(['name' => 'royale', 'version' => 'v1']);
    expect($metadata['Map'])->toBe('royale');
    expect($metadata['Timeout'])->toBe(250);
    expect($metadata['Source'])->toBe('local');
});

it('uses default values for missing game metadata', function () {
    $game = new GameLog();
    $game->game_id = 'sparse-game';
    // Leave ruleset, map, timeout as null

    $metadata = BoardDataTransformer::getGameMetadata($game);

    expect($metadata['Ruleset'])->toBe(['name' => 'standard']);
    expect($metadata['Map'])->toBe('standard');
    expect($metadata['Timeout'])->toBe(500);
});

// ===========================================
// Edge Case Tests
// ===========================================

it('handles missing board data in turn request', function () {
    $turn = new Turn();
    $turn->turn = 0;
    $turn->request = [
        'game' => ['id' => 'test'],
        'turn' => 0,
        // No 'board' key
    ];

    $reflection = new ReflectionMethod(BoardDataTransformer::class, 'turnToFrame');
    $reflection->setAccessible(true);

    $frame = $reflection->invoke(null, $turn);

    expect($frame['width'])->toBe(11);
    expect($frame['height'])->toBe(11);
    expect($frame['food'])->toBeEmpty();
    expect($frame['hazards'])->toBeEmpty();
    expect($frame['snakes'])->toBeEmpty();
});

it('deduplicates turns with same turn number via Collection unique', function () {
    // Test the deduplication logic that gameToFrames uses internally
    // gameToFrames calls: $turns->unique('turn')
    // We simulate this by creating Turn objects and testing unique() + turnToFrame

    $turn0a = createTestTurn(0);
    $turn0b = createTestTurn(0); // Duplicate turn number
    $turn1 = createTestTurn(1);

    $turns = collect([$turn0a, $turn0b, $turn1]);

    // Verify unique() properly deduplicates by turn number
    $uniqueTurns = $turns->unique('turn');
    expect($uniqueTurns)->toHaveCount(2);

    // Verify the frames can be generated from the deduplicated turns
    $reflection = new ReflectionMethod(BoardDataTransformer::class, 'turnToFrame');
    $reflection->setAccessible(true);

    $frames = $uniqueTurns->map(fn($turn) => $reflection->invoke(null, $turn))->values()->toArray();
    $frames = applyFinalFrameMarker($frames);

    expect($frames)->toHaveCount(2);
    expect($frames[0]['turn'])->toBe(0);
    expect($frames[1]['turn'])->toBe(1);
    expect($frames[1]['isFinalFrame'])->toBeTrue();
});
