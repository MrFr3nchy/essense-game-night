<?php

use App\Http\Controllers\BoardGameController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\SteamController;
use App\Http\Middleware\IdentifyUser;
use Illuminate\Support\Facades\Route;

// Public health check — no auth required, safe to call before cookie is set.
Route::get('/health', HealthController::class);

// Search endpoints: 30 requests/minute
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/steam/search', [SteamController::class, 'search']);
    Route::get('/board-games/search', [BoardGameController::class, 'search']);
});

// Authenticated game endpoints: 60 requests/minute
Route::middleware([IdentifyUser::class, 'throttle:60,1'])->group(function () {
    Route::get('/games', [GameController::class, 'index']);
    Route::post('/games', [GameController::class, 'store']);
    Route::post('/games/{id}/vote', [GameController::class, 'vote']);
    Route::delete('/games/{id}/vote', [GameController::class, 'removeVote']);
    Route::delete('/games/{id}', [GameController::class, 'destroy']);
    Route::get('/me', [GameController::class, 'me']);
    Route::post('/reset', [GameController::class, 'reset']);
});
