import { Sparkles } from 'lucide-react';
import type { ContestantDto, RoundDto } from '@/types/api';

interface OnStageModeProps {
  contestant: ContestantDto | null;
  activeRound: RoundDto | null;
}

export function OnStageMode({ contestant, activeRound }: OnStageModeProps) {
  if (!contestant) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center text-center py-10">
        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-500/20 px-4 py-1.5 text-xs font-mono-tech font-bold uppercase tracking-wider text-emerald-300">
          <Sparkles className="h-4 w-4" />
          Standby
        </div>
        <h2 className="text-4xl font-extrabold tracking-tight text-white sm:text-6xl">
          {activeRound?.name ?? 'Main Stage'}
        </h2>
        <p className="mt-4 text-lg sm:text-xl font-mono-tech text-slate-400">
          Awaiting next contestant on stage
        </p>
      </div>
    );
  }

  return (
    <div className="my-auto py-4 sm:py-8 max-w-4xl mx-auto w-full">
      <div className="rounded-2xl p-6 sm:p-10 bg-slate-900/90 border border-slate-700/80 text-white grid grid-cols-1 md:grid-cols-12 gap-6 sm:gap-8 items-center shadow-2xl">
        <div className="md:col-span-4">
          <div className="relative mx-auto aspect-[4/5] w-full max-w-[280px] overflow-hidden rounded-xl border border-slate-700 bg-slate-950 shadow-xl">
            {contestant.photo_url ? (
              <img
                src={contestant.photo_url}
                alt={contestant.name}
                className="h-full w-full object-cover"
              />
            ) : (
              <div className="flex h-full w-full flex-col items-center justify-center bg-gradient-to-br from-slate-800 via-slate-900 to-slate-950 text-white">
                <span className="font-mono-tech text-6xl font-black tracking-tighter text-emerald-400">
                  #{contestant.candidate_number}
                </span>
                <span className="mt-2 font-mono-tech text-xs uppercase tracking-[0.25em] text-slate-400">
                  STAGE FEED
                </span>
              </div>
            )}
          </div>
        </div>

        <div className="md:col-span-8 space-y-3 text-center md:text-left">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-emerald-300 font-mono-tech text-xs font-bold">
            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping" />
            LIVE ON STAGE • CANDIDATE #{contestant.candidate_number}
          </div>

          <h2 className="text-3xl sm:text-5xl font-extrabold tracking-tight text-white">
            {contestant.name}
          </h2>

          <p className="text-base sm:text-lg font-mono-tech text-slate-300">
            Representing:{' '}
            <strong className="text-white">{contestant.origin_or_group}</strong>
          </p>

          {contestant.performance_meta && (
            <div className="p-3.5 rounded-xl bg-slate-800/90 border border-slate-700 font-mono-tech text-xs sm:text-sm text-slate-200">
              Performance: {contestant.performance_meta}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
