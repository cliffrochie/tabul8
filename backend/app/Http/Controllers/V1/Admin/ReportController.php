<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Round;
use App\Models\User;
use App\Services\Scoring\ScoringEngineService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ReportController extends Controller
{
    public function exportPdf(
        Request $request,
        Round $round,
        ScoringEngineService $scoringEngine
    ): Response {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403, 'Only authorized tabulation staff can export official tally sheets.');
        }

        $round->loadMissing('event', 'criteria');
        $event = $round->event;

        $judges = $event->judges()
            ->where('is_active', true)
            ->orderBy('judge_number')
            ->get();

        $contestantsById = $event->contestants()
            ->where('status', 'active')
            ->orderBy('candidate_number')
            ->get()
            ->keyBy('id');

        $results = $scoringEngine->tabulateRound($round);

        $pdf = Pdf::loadView('pdf.official-tally', [
            'event' => $event,
            'round' => $round,
            'judges' => $judges,
            'contestantsById' => $contestantsById,
            'results' => $results,
            'generatedAt' => now()->format('Y-m-d H:i:s T'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("official-tally-round-{$round->id}.pdf");
    }
}
