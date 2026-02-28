<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HabitController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LootBoxController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

// ─── Public (no auth required) ──────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ─── Protected (Sanctum token required) ─────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // Player profile & inventory
    Route::get('/profile',   [ProfileController::class, 'show']);
    Route::get('/inventory', [InventoryController::class, 'index']);

    // Habits CRUD
    Route::get('/habits',          [HabitController::class, 'index']);
    Route::post('/habits',         [HabitController::class, 'store']);
    Route::get('/habits/{habit}',  [HabitController::class, 'show']);
    Route::put('/habits/{habit}',  [HabitController::class, 'update']);
    Route::delete('/habits/{habit}', [HabitController::class, 'destroy']);

    // Habits gamification
    Route::post('/habits/{habit}/complete', [HabitController::class, 'completeHabit']);

    // History & stats
    Route::get('/habits/{habit}/logs', [StatsController::class, 'habitLogs']);
    Route::get('/stats',               [StatsController::class, 'overview']);

    // Loot boxes
    Route::get('/lootbox',      [LootBoxController::class, 'catalog']);
    Route::post('/lootbox/open', [LootBoxController::class, 'open']);
});
