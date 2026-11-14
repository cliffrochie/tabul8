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
use App\Services\RoundRosterService;
use App\Services\ScoreService;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EventStateController extends Controller
{
    public function show(
        Request $request,
        Event $event,
        ScoreService $scoreService,
        ScoringEngineService $scoringEngine,
        RoundRosterService $rosterService
    ): JsonResponse {
        $actor = $request->user();
        if (! $request->is('api/v1/display/*')) {
            if ($actor === null) {
                abort(403, 'Authentication required.');
            }
            Gate::forUser($actor)->authorize('viewState', $event);
        }

        $activeRound = $event->active_round_id
            ? Round::with('criteria')->find($event->active_round_id)
            : $event->rounds()->with('criteria')->first();

        $contestants = $event->contestants()
            ->where('status', 'active')
            ->orderBy('candidate_number')
            ->get();

        if ($activeRound !== null) {
            $qualifiedMap = $rosterService->getQualifiedContestants($activeRound)->keyBy('id');
            $eliminatedMap = $rosterService->getEliminatedContestants($activeRound)
                ->keyBy(fn (array $row) => $row['contestant']->id);

            foreach ($contestants as $contestant) {
                $qualified = $qualifiedMap->get($contestant->id);
                if ($qualified !== null) {
                    $pivot = $qualified->pivot;
                    $contestant->setAttribute('is_qualified_in_active_round', true);
                    $contestant->setAttribute(
                        'qualified_from_rank',
                        $pivot?->qualified_from_rank ? (int) $pivot->qualified_from_rank : null
                    );
                    $contestant->setAttribute(
                        'qualification_method',
                        $pivot?->qualification_method ?? ($activeRound->sequence === 1 ? 'INITIAL_ROSTER' : null)
                    );
                    $contestant->setAttribute('eliminated_in_round_name', null);
                } else {
                    $elim = $eliminatedMap->get($contestant->id);
                    $contestant->setAttribute('is_qualified_in_active_round', false);
                    $contestant->setAttribute('qualified_from_rank', null);
                    $contestant->setAttribute('qualification_method', null);
                    $contestant->setAttribute(
                        'eliminated_in_round_name',
                        $elim['eliminated_in_round_name'] ?? 'Prior Round'
                    );
                }
            }
        }

        $payload = [
            'event' => new EventResource($event),
            'rounds' => RoundResource::collection($event->rounds()->with('criteria')->orderBy('sequence')->get()),
            'active_round' => $activeRound ? new RoundResource($activeRound) : null,
            'round_funnel' => $rosterService->buildRoundFunnel($event),
            'contestants' => ContestantResource::collection($contestants),
        ];

        if ($actor instanceof Judge && $activeRound !== null) {
            $payload['my_scores'] = $scoreService->buildJudgeScoresMap($actor, $activeRound);
        } elseif ($actor instanceof User && $activeRound !== null) {
            $payload['judges'] = JudgeResource::collection($event->judges()->orderBy('judge_number')->get());
            $payload['scores_matrix'] = $this->buildAdminMatrix($activeRound, $rosterService);
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
    private function buildAdminMatrix(Round $round, RoundRosterService $rosterService): array
    {
        $activeIds = $rosterService->getActiveContestantIds($round);
        $scores = Score::where('round_id', $round->id)
            ->whereIn('contestant_id', $activeIds)
            ->get();
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
