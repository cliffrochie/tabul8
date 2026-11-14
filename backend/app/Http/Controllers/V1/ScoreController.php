<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LockScoresRequest;
use App\Http\Requests\RequestUnlockRequest;
use App\Http\Requests\UpsertScoresRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Judge;
use App\Models\Round;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class ScoreController extends Controller
{
    public function upsert(UpsertScoresRequest $request, ScoreService $scoreService): JsonResponse
    {
        /** @var Judge $judge */
        $judge = $request->user();
        $validated = $request->validated();
        $round = Round::findOrFail($validated['round_id']);
        Gate::forUser($judge)->authorize('mutateRound', $round);

        $result = $scoreService->upsertBatch(
            $judge,
            $round,
            $validated['mutations'],
            $request->ip() ?? '127.0.0.1'
        );

        return ApiResponse::success(
            $result,
            'Processed '.count($validated['mutations']).' mutation packet(s).'
        );
    }

    public function lock(LockScoresRequest $request, ScoreService $scoreService): JsonResponse
    {
        /** @var Judge $judge */
        $judge = $request->user();
        $validated = $request->validated();
        $round = Round::findOrFail($validated['round_id']);
        Gate::forUser($judge)->authorize('mutateRound', $round);

        $myScores = $scoreService->lockSheets(
            $judge,
            $round,
            $validated['contestant_ids'] ?? [],
            (bool) ($validated['lock_all'] ?? false),
            $request->ip() ?? '127.0.0.1'
        );

        return ApiResponse::success(['my_scores' => $myScores], 'Score sheet(s) locked.');
    }

    public function requestUnlock(RequestUnlockRequest $request, ScoreService $scoreService): JsonResponse
    {
        /** @var Judge $judge */
        $judge = $request->user();
        $validated = $request->validated();
        $round = Round::findOrFail($validated['round_id']);
        Gate::forUser($judge)->authorize('mutateRound', $round);

        $myScores = $scoreService->requestUnlock(
            $judge,
            $round,
            (int) $validated['contestant_id'],
            $validated['reason'] ?? null,
            $request->ip() ?? '127.0.0.1'
        );

        return ApiResponse::success(['my_scores' => $myScores], 'Unlock request submitted.');
    }
}
