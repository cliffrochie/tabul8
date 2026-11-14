<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Round;
use App\Models\Score;
use App\Services\RoundRosterService;
use App\Services\Scoring\ScoringEngineService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class ReportController extends Controller
{
    public function exportPdf(
        Request $request,
        Round $round,
        ScoringEngineService $scoringEngine,
        RoundRosterService $rosterService
    ): Response|JsonResponse {
        $actor = $request->user();
        if ($actor === null) {
            abort(403, 'Authentication required.');
        }

        Gate::forUser($actor)->authorize('exportTally', $round);

        $round->loadMissing('event', 'criteria');
        $event = $round->event;

        $judges = $event->judges()
            ->where('is_active', true)
            ->orderBy('judge_number')
            ->get();

        $contestants = $rosterService->getQualifiedContestants($round);
        $contestantsById = $contestants->keyBy('id');

        $isPreview = $request->boolean('preview');
        if ($round->status !== 'finalized' && ! $isPreview) {
            $activeJudgeIds = $judges->pluck('id');
            $qualifiedContestantIds = $contestants->pluck('id');
            $leafCriteriaIds = $round->criteria
                ->filter(fn ($c) => ! $round->criteria->contains('parent_id', $c->id))
                ->pluck('id');

            $expectedCells = $activeJudgeIds->count()
                * $qualifiedContestantIds->count()
                * $leafCriteriaIds->count();

            $scoreQuery = Score::where('round_id', $round->id)
                ->whereIn('judge_id', $activeJudgeIds)
                ->whereIn('contestant_id', $qualifiedContestantIds);

            $hasUnlockedRows = (clone $scoreQuery)
                ->whereIn('status', ['DRAFT', 'UNLOCK_REQUESTED'])
                ->exists();

            $lockedCount = (clone $scoreQuery)
                ->where('status', 'LOCKED')
                ->count();

            if ($hasUnlockedRows || ($expectedCells > 0 && $lockedCount < $expectedCells)) {
                return ApiResponse::error(
                    'Official tally PDF cannot be exported until judge quorum lock is complete. Pass ?preview=1 for an unofficial draft preview.',
                    ['quorum' => ['Not all active judges have locked scores for this round.']],
                    409
                );
            }
        }

        $results = $scoringEngine->tabulateRound($round);
        $canonicalPayload = [
            'event_id' => $event->id,
            'round_id' => $round->id,
            'round_status' => $round->status,
            'results' => $results->map(fn ($row) => [
                'contestant_id' => $row->contestantId,
                'final_rank' => $row->finalRank,
                'net_composite' => $row->netComposite,
                'cumulative_score' => $row->cumulativeScore,
                'is_qualified' => $row->isQualified,
            ])->values()->all(),
        ];
        $verificationHash = hash('sha256', json_encode($canonicalPayload, JSON_THROW_ON_ERROR));
        $watermarkText = $round->status === 'finalized'
            ? 'FINAL & OFFICIAL'
            : ($isPreview ? 'DRAFT PREVIEW' : 'UNOFFICIAL DRAFT');

        $pdf = Pdf::loadView('pdf.official-tally', [
            'event' => $event,
            'round' => $round,
            'judges' => $judges,
            'contestantsById' => $contestantsById,
            'results' => $results,
            'verificationHash' => $verificationHash,
            'watermarkText' => $watermarkText,
            'generatedAt' => now()->format('Y-m-d H:i:s T'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("official-tally-round-{$round->id}.pdf");
    }
}
