<?php

use Winter\Battlesnake\Classes\APIController;

Route::group(['prefix' => 'api/bs/{snake}/{password}'], function () {
    Route::get('/', [APIController::class, 'index']);
    Route::post('/start', [APIController::class, 'start']);
    Route::post('/move', [APIController::class, 'move']);
    Route::post('/end', [APIController::class, 'end']);
});
