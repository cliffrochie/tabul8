<?php

namespace App\Services;

use App\Models\Contestant;
use App\Models\Event;
use App\Models\Round;
use Illuminate\Support\Collection;

final class RoundRosterService
{
    /**
     * Returns the ordered active qualifiers eligible to compete in $round.
     * Round 1 falls back to all active registered contestants if round_contestants is empty.
     *
     * @return Collection<int, Contestant>
     */
    public function getQualifiedContestants(Round $round): Collection
    {
        $pivoted = $round->contestants()
            ->where('contestants.status', 'active')
            ->orderBy('contestants.candidate_number')
            ->get();

        if ($pivoted->isNotEmpty()) {
            return $pivoted;
        }

        if ($round->sequence === 1) {
            return $round->event->contestants()
                ->where('status', 'active')
                ->orderBy('candidate_number')
                ->get();
        }

        return collect();
    }

    /**
     * Returns active-event contestants excluded from $round alongside the prior round name where they were cut.
     *
     * @return Collection<int, array{contestant: Contestant, eliminated_in_round_name: string}>
     */
    public function getEliminatedContestants(Round $round): Collection
    {
        if ($round->sequence <= 1) {
            return collect();
        }

        $qualifiedIds = $this->getActiveContestantIds($round);
        $allActive = $round->event->contestants()
            ->where('status', 'active')
            ->orderBy('candidate_number')
            ->get();

        $priorRounds = $round->event->rounds()
            ->where('sequence', '<', $round->sequence)
            ->with(['contestants' => fn ($q) => $q->select('contestants.id')])
            ->orderBy('sequence')
            ->get();

        $firstRound = $priorRounds->first();

        return $allActive
            ->reject(fn (Contestant $c): bool => in_array($c->id, $qualifiedIds, true))
            ->values()
            ->map(function (Contestant $contestant) use ($priorRounds, $firstRound): array {
                $eliminatedIn = $firstRound?->name ?? 'Prior Round';

                foreach ($priorRounds as $idx => $prior) {
                    $wasInPrior = $prior->sequence === 1 && $prior->contestants->isEmpty()
                        ? true
                        : $prior->contestants->contains('id', $contestant->id);

                    if ($wasInPrior) {
                        $next = $priorRounds->get($idx + 1);
                        if ($next === null || ! $next->contestants->contains('id', $contestant->id)) {
                            $eliminatedIn = $prior->name;
                            break;
                        }
                    }
                }

                return [
                    'contestant' => $contestant,
                    'eliminated_in_round_name' => $eliminatedIn,
                ];
            });
    }

    /**
     * Builds the progressive elimination funnel summary for all rounds in an event.
     *
     * @return array<int, array{round_id: int, sequence: int, name: string, status: string, starting_count: int, eliminate_count: int, advancement_count: int}>
     */
    public function buildRoundFunnel(Event $event): array
    {
        $totalActive = $event->contestants()->where('status', 'active')->count();
        $rounds = $event->rounds()->withCount('contestants')->orderBy('sequence')->get();

        $funnel = [];
        $runningPool = $totalActive;

        foreach ($rounds as $round) {
            $startingCount = ($round->sequence > 1 && $round->contestants_count > 0)
                ? (int) $round->contestants_count
                : $runningPool;

            $advancement = min($startingCount, max(1, (int) $round->advancement_count));
            $eliminate = $round->eliminate_count > 0
                ? (int) $round->eliminate_count
                : max(0, $startingCount - $advancement);

            $funnel[] = [
                'round_id' => $round->id,
                'sequence' => $round->sequence,
                'name' => $round->name,
                'status' => $round->status,
                'starting_count' => $startingCount,
                'eliminate_count' => $eliminate,
                'advancement_count' => $advancement,
            ];

            $runningPool = $advancement;
        }

        return $funnel;
    }

    /**
     * @return array<int, int>
     */
    public function getActiveContestantIds(Round $round): array
    {
        return $this->getQualifiedContestants($round)->pluck('id')->all();
    }
}
