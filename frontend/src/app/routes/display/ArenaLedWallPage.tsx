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
      <div className="wf-hatch-dark flex min-h-screen items-center justify-center text-slate-400 font-mono-tech text-sm">
        Connecting to Arena Stage Feed...
      </div>
    );
  }

  if (isError || !state) {
    return (
      <div className="wf-hatch-dark flex min-h-screen items-center justify-center text-rose-400 font-mono-tech text-sm px-4 text-center">
        Unable to load display feed for Event #{eventId}.
      </div>
    );
  }

  const { event, active_round, contestants, revealed_finalists } = state;
  const onStageContestant =
    contestants.find((c) => c.id === event.on_stage_contestant_id) ?? null;

  return (
    <div className="wf-hatch-dark relative flex min-h-screen flex-col justify-between overflow-hidden text-white p-5 sm:p-10">
      <header className="flex flex-wrap items-center justify-between gap-4 pb-5 border-b border-slate-800 max-w-[1600px] w-full mx-auto">
        <div className="min-w-0">
          <span className="text-[11px] font-mono-tech uppercase text-emerald-400 block">
            [WF-07] Arena LED Wall Projection
          </span>
          <h1 className="text-xl sm:text-3xl font-extrabold tracking-tight mt-0.5 text-white truncate">
            {event.title}
            {active_round ? ` — ${active_round.name}` : ''}
          </h1>
        </div>
        {active_round && (
          <div className="rounded-full border border-slate-700 bg-slate-800/80 px-4 py-1.5 text-xs sm:text-sm font-mono-tech font-semibold text-slate-200 shrink-0">
            {active_round.name}
          </div>
        )}
      </header>

      <main className="flex flex-1 flex-col justify-center py-6 sm:py-8 max-w-[1600px] w-full mx-auto">
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

      <footer className="pt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs font-mono-tech text-slate-400 max-w-[1600px] w-full mx-auto">
        <span>READ-ONLY PUBLIC PROJECTION FEED</span>
        <span>SYNCED VIA WEBSOCKETS • ZERO RAW SCORE EXPOSURE</span>
      </footer>
    </div>
  );
}
