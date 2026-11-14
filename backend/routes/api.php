<?php

use App\Http\Controllers\V1\Admin\AdminScoreController;
use App\Http\Controllers\V1\Admin\ContestantSetupController;
use App\Http\Controllers\V1\Admin\CriterionSetupController;
use App\Http\Controllers\V1\Admin\DeductionController;
use App\Http\Controllers\V1\Admin\EventCrudController;
use App\Http\Controllers\V1\Admin\JudgeSetupController;
use App\Http\Controllers\V1\Admin\ReportController;
use App\Http\Controllers\V1\Admin\RoundControlController;
use App\Http\Controllers\V1\Admin\RoundSetupController;
use App\Http\Controllers\V1\Admin\StageSyncController;
use App\Http\Controllers\V1\Admin\UserManagementController;
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
    Route::get('/display/events/{event}/contestants/{contestant}/photo', [ContestantSetupController::class, 'showDisplayPhoto']);

    // Authenticated Routes (Sanctum Bearer Token)
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Shared Live State Hydration (role-scoped)
        Route::get('/events/{event}/state', [EventStateController::class, 'show']);
        Route::get('/events/{event}/contestants/{contestant}/photo', [ContestantSetupController::class, 'showPhoto']);

        // Judge Scoring & Offline Outbox Flush
        Route::post('/scores/upsert', [ScoreController::class, 'upsert']);
        Route::post('/scores/lock', [ScoreController::class, 'lock']);
        Route::post('/scores/request-unlock', [ScoreController::class, 'requestUnlock']);

        // Tabulator / Admin Console Controls
        Route::prefix('admin')->group(function (): void {
            Route::get('/events', [EventCrudController::class, 'index']);
            Route::post('/events', [EventCrudController::class, 'store']);
            Route::put('/events/{event}', [EventCrudController::class, 'update']);
            Route::delete('/events/{event}', [EventCrudController::class, 'destroy']);
            Route::get('/events/{event}/preflight', [EventCrudController::class, 'preflight']);

            Route::post('/events/{event}/rounds', [RoundSetupController::class, 'store']);
            Route::post('/events/{event}/rounds/generate-funnel', [RoundSetupController::class, 'generateFunnel']);
            Route::put('/events/{event}/rounds/reorder', [RoundSetupController::class, 'reorder']);
            Route::put('/rounds/{round}', [RoundSetupController::class, 'update']);
            Route::delete('/rounds/{round}', [RoundSetupController::class, 'destroy']);

            Route::post('/rounds/{round}/criteria', [CriterionSetupController::class, 'store']);
            Route::put('/criteria/{criterion}', [CriterionSetupController::class, 'update']);
            Route::delete('/criteria/{criterion}', [CriterionSetupController::class, 'destroy']);

            Route::post('/events/{event}/contestants', [ContestantSetupController::class, 'store']);
            Route::post('/events/{event}/contestants/import-csv', [ContestantSetupController::class, 'importCsv']);
            Route::post('/events/{event}/contestants/renumber', [ContestantSetupController::class, 'renumber']);
            Route::put('/contestants/{contestant}', [ContestantSetupController::class, 'update']);
            Route::delete('/contestants/{contestant}', [ContestantSetupController::class, 'destroy']);
            Route::post('/contestants/{contestant}/photo', [ContestantSetupController::class, 'uploadPhoto']);

            Route::post('/events/{event}/judges', [JudgeSetupController::class, 'store']);
            Route::put('/judges/{judge}', [JudgeSetupController::class, 'update']);
            Route::delete('/judges/{judge}', [JudgeSetupController::class, 'destroy']);
            Route::post('/judges/{judge}/regenerate-pin', [JudgeSetupController::class, 'regeneratePin']);
            Route::get('/events/{event}/judge-cards-pdf', [JudgeSetupController::class, 'exportJudgeCardsPdf']);

            Route::get('/users', [UserManagementController::class, 'index']);
            Route::post('/users', [UserManagementController::class, 'store']);
            Route::put('/users/{user}', [UserManagementController::class, 'update']);
            Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);

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
