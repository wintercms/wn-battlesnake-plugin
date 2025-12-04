<?php

use Winter\Battlesnake\Classes\AsciiBoard;

it('parses an empty board', function () {
    $state = AsciiBoard::parse('
        . . .
        . . .
        . . .
    ');

    expect($state['board']['width'])->toBe(3);
    expect($state['board']['height'])->toBe(3);
    expect($state['board']['food'])->toBeEmpty();
    expect($state['board']['snakes'])->toHaveCount(1); // Just "you" with empty body
});

it('parses food positions', function () {
    // Note: Y is flipped - ASCII row 0 = Battlesnake Y=2 (top), row 2 = Y=0 (bottom)
    $state = AsciiBoard::parse('
        . F .
        . . .
        F . F
    ');

    expect($state['board']['food'])->toHaveCount(3);
    expect($state['board']['food'][0])->toBe(['x' => 1, 'y' => 2]); // Top row in ASCII = high Y
    expect($state['board']['food'][1])->toBe(['x' => 0, 'y' => 0]); // Bottom row in ASCII = low Y
    expect($state['board']['food'][2])->toBe(['x' => 2, 'y' => 0]);
});

it('parses your snake', function () {
    $state = AsciiBoard::parse('
        . . . . .
        . Y y y .
        . . . . .
    ');

    expect($state['you']['head'])->toBe(['x' => 1, 'y' => 1]);
    expect($state['you']['body'])->toHaveCount(3);
    expect($state['you']['length'])->toBe(3);
    expect($state['you']['body'][0])->toBe(['x' => 1, 'y' => 1]); // Head first
});

it('parses enemy snakes', function () {
    // Note: Y is flipped - ASCII row 0 = Battlesnake Y=2, row 2 = Y=0
    $state = AsciiBoard::parse('
        . A a a .
        . . . . .
        . B b . .
    ');

    // Should have 3 snakes: you (empty), enemy A, enemy B
    expect($state['board']['snakes'])->toHaveCount(3);

    $enemyA = collect($state['board']['snakes'])->firstWhere('id', 'enemy-a');
    $enemyB = collect($state['board']['snakes'])->firstWhere('id', 'enemy-b');

    expect($enemyA['length'])->toBe(3);
    expect($enemyA['head'])->toBe(['x' => 1, 'y' => 2]); // Top row = Y=2

    expect($enemyB['length'])->toBe(2);
    expect($enemyB['head'])->toBe(['x' => 1, 'y' => 0]); // Bottom row = Y=0
});

it('parses hazards', function () {
    $state = AsciiBoard::parse('
        H H H
        . . .
        . . .
    ');

    expect($state['board']['hazards'])->toHaveCount(3);
});

it('creates a complete game state for testing', function () {
    $state = AsciiBoard::parse('
        . . . . .
        . A a . .
        . . . . .
        . Y y y .
        . . F . .
    ');

    // Verify all required fields exist
    expect($state)->toHaveKey('game');
    expect($state)->toHaveKey('turn');
    expect($state)->toHaveKey('board');
    expect($state)->toHaveKey('you');

    expect($state['game'])->toHaveKey('id');
    expect($state['game'])->toHaveKey('ruleset');
    expect($state['game'])->toHaveKey('timeout');

    expect($state['you'])->toHaveKey('id');
    expect($state['you'])->toHaveKey('health');
    expect($state['you']['health'])->toBe(100);
});
