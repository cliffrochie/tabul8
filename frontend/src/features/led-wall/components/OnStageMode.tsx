import { Sparkles } from 'lucide-react';
import type { ContestantDto, RoundDto } from '@/types/api';

interface OnStageModeProps {
  contestant: ContestantDto | null;
  activeRound: RoundDto | null;
}

export function OnStageMode({ contestant, activeRound }: OnStageModeProps) {
  if (!contestant) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center text-center">
        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-amber-400/30 bg-amber-400/10 px-5 py-2 text-sm font-semibold uppercase tracking-[0.25em] text-amber-300">
          <Sparkles className="h-4 w-4" />
          Standby
        </div>
        <h2 className="text-5xl font-bold tracking-tight text-white sm:text-6xl">
          {activeRound?.name ?? 'Main Stage'}
        </h2>
        <p className="mt-4 text-xl text-slate-400">
          Awaiting next contestant on stage
        </p>
      </div>
    );
  }

  return (
    <div className="flex flex-1 items-center justify-center px-6">
      <div className="grid w-full max-w-6xl items-center gap-10 rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur-md lg:grid-cols-[360px_1fr] lg:p-12">
        <div className="relative mx-auto aspect-[4/5] w-full max-w-[340px] overflow-hidden rounded-2xl border border-white/15 bg-slate-900 shadow-xl">
          {contestant.photo_url ? (
            <img
              src={contestant.photo_url}
              alt={contestant.name}
              className="h-full w-full object-cover"
            />
          ) : (
            <div className="flex h-full w-full flex-col items-center justify-center bg-gradient-to-br from-slate-800 via-slate-900 to-indigo-950 text-white">
              <span className="text-7xl font-black tracking-tighter text-amber-300">
                #{contestant.candidate_number}
              </span>
              <span className="mt-2 text-xs uppercase tracking-[0.3em] text-slate-400">
                Candidate
              </span>
            </div>
          )}
          <div className="absolute left-4 top-4 rounded-xl bg-amber-400 px-4 py-1.5 text-lg font-black text-slate-950 shadow">
            #{contestant.candidate_number}
          </div>
        </div>

        <div className="space-y-5 text-center lg:text-left">
          <div className="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.25em] text-emerald-300">
            <span className="h-2 w-2 animate-pulse rounded-full bg-emerald-400" />
            Now Performing · {activeRound?.name ?? 'Live Round'}
          </div>

          <h2 className="text-5xl font-black tracking-tight text-white sm:text-6xl lg:text-7xl">
            {contestant.name}
          </h2>

          <p className="text-2xl font-medium text-amber-300 sm:text-3xl">
            {contestant.origin_or_group}
          </p>

          {contestant.performance_meta && (
            <div className="rounded-2xl border border-white/10 bg-black/30 px-6 py-4 text-lg text-slate-200">
              {contestant.performance_meta}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
