import { useParams } from 'react-router-dom';
import {
  CriteriaMode,
  FinalistsRevealMode,
  OnStageMode,
  useLedWallState,
} from '@/features/led-wall';

export function ArenaLedWallPage() {
  const { eventId: rawEventId } = useParams<{ eventId?: string }>();
  const eventId = Number(rawEventId) || 1;

  const { data: state, isLoading, isError } = useLedWallState(eventId);

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-950 text-slate-400">
        Connecting to Arena Stage Feed...
      </div>
    );
  }

  if (isError || !state) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-950 text-rose-400">
        Unable to load display feed for Event #{eventId}.
      </div>
    );
  }

  const { event, active_round, contestants, revealed_finalists } = state;
  const onStageContestant =
    contestants.find((c) => c.id === event.on_stage_contestant_id) ?? null;

  return (
    <div className="relative flex min-h-screen flex-col overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 text-white">
      <header className="flex items-center justify-between border-b border-white/10 px-8 py-5">
        <div>
          <div className="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">
            TABUL8 · Official Stage Display
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">
            {event.title}
          </h1>
        </div>
        {active_round && (
          <div className="rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-sm font-medium text-slate-200">
            {active_round.name}
          </div>
        )}
      </header>

      <main className="flex flex-1 flex-col py-8">
        {event.led_mode === 'criteria' ? (
          <CriteriaMode activeRound={active_round} />
        ) : event.led_mode === 'reveal' ? (
          <FinalistsRevealMode
            activeRound={active_round}
            revealedIds={event.revealed_contestant_ids ?? []}
            revealedFinalists={revealed_finalists ?? []}
          />
        ) : (
          <OnStageMode
            contestant={onStageContestant}
            activeRound={active_round}
          />
        )}
      </main>
    </div>
  );
}
