<?php

use Winter\Battlesnake\Classes\AsciiBoard;

beforeEach(function () {
    $this->instance = $this->app->make(AsciiBoard::class);
});

it('the convertToGameState method does something', function () {
    $this->assertTrue(method_exists($this->instance, 'convertToGameState'));


    $this->convertToGameState("


    ");



});
