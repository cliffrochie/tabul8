<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContestantResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\JudgeResource;
use App\Http\Resources\RoundResource;
use App\Http\Resources\RoundResultResource;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\Score;
use App\Models\User;
use App\Services\ScoreService;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventStateController extends Controller
{
    public function show(
        Request $request,
        Event $event,
        ScoreService $scoreService,
        ScoringEngineService $scoringEngine
    ): JsonResponse {
        $actor = $request->user();

        $activeRound = $event->active_round_id
            ? Round::with('criteria')->find($event->active_round_id)
            : $event->rounds()->with('criteria')->first();

        $contestants = $event->contestants()
            ->where('status', 'active')
            ->orderBy('candidate_number')
            ->get();

        $payload = [
            'event' => new EventResource($event),
            'rounds' => RoundResource::collection($event->rounds()->with('criteria')->get()),
            'active_round' => $activeRound ? new RoundResource($activeRound) : null,
            'contestants' => ContestantResource::collection($contestants),
        ];

        if ($actor instanceof Judge && $activeRound !== null) {
            $payload['my_scores'] = $scoreService->buildJudgeScoresMap($actor, $activeRound);
        } elseif ($actor instanceof User && $activeRound !== null) {
            $payload['judges'] = JudgeResource::collection($event->judges()->orderBy('judge_number')->get());
            $payload['scores_matrix'] = $this->buildAdminMatrix($activeRound);
            $results = $scoringEngine->tabulateRound($activeRound);
            $payload['leaderboard'] = RoundResultResource::collection($results);
        } else {
            // Public LED Wall: zero raw score exposure; only revealed finalist cards when in reveal mode
            $revealedIds = $event->revealed_contestant_ids ?? [];
            $payload['revealed_finalists'] = ContestantResource::collection(
                $contestants->whereIn('id', $revealedIds)->values()
            );
        }

        return ApiResponse::success($payload, 'Event state loaded.');
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function buildAdminMatrix(Round $round): array
    {
        $scores = Score::where('round_id', $round->id)->get();
        $matrix = [];

        foreach ($scores as $score) {
            $cid = (string) $score->contestant_id;
            $jid = (string) $score->judge_id;

            if (! isset($matrix[$cid][$jid])) {
                $matrix[$cid][$jid] = [
                    'status' => $score->status,
                    'weighted_total' => 0.0,
                    'updated_at' => $score->updated_at?->toIso8601String(),
                ];
            }

            $matrix[$cid][$jid]['weighted_total'] = round(
                $matrix[$cid][$jid]['weighted_total'] + (float) $score->weighted_score,
                2,
                PHP_ROUND_HALF_UP
            );
        }

        return $matrix;
    }
}
