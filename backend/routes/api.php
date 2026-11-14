<?php

use App\Http\Controllers\V1\Admin\AdminScoreController;
use App\Http\Controllers\V1\Admin\DeductionController;
use App\Http\Controllers\V1\Admin\ReportController;
use App\Http\Controllers\V1\Admin\RoundControlController;
use App\Http\Controllers\V1\Admin\StageSyncController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\EventStateController;
use App\Http\Controllers\V1\ScoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public Authentication Routes
    Route::post('/auth/judge-pin', [AuthController::class, 'judgePin']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Public LED Wall Read-Only State (zero raw score exposure)
    Route::get('/display/events/{event}/state', [EventStateController::class, 'show']);

    // Authenticated Routes (Sanctum Bearer Token)
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Shared Live State Hydration (role-scoped)
        Route::get('/events/{event}/state', [EventStateController::class, 'show']);

        // Judge Scoring & Offline Outbox Flush
        Route::post('/scores/upsert', [ScoreController::class, 'upsert']);
        Route::post('/scores/lock', [ScoreController::class, 'lock']);
        Route::post('/scores/request-unlock', [ScoreController::class, 'requestUnlock']);

        // Tabulator / Admin Console Controls
        Route::prefix('admin')->group(function (): void {
            Route::post('/scores/resolve-unlock', [AdminScoreController::class, 'resolveUnlock']);
            Route::post('/deductions', [DeductionController::class, 'store']);
            Route::post('/stage-sync', [StageSyncController::class, 'push']);
            Route::post('/rounds/{round}/finalize', [RoundControlController::class, 'finalize']);
            Route::post('/rounds/{round}/reopen', [RoundControlController::class, 'reopen']);
            Route::post('/rounds/{round}/judges/{judge}/exclude', [RoundControlController::class, 'excludeJudge']);
            Route::get('/rounds/{round}/tally-pdf', [ReportController::class, 'exportPdf']);
        });
    });
});
