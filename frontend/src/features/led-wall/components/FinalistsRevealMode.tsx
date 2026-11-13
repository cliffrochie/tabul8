import { Lock, Trophy } from 'lucide-react';
import type { ContestantDto, RoundDto } from '@/types/api';

interface FinalistsRevealModeProps {
  activeRound: RoundDto | null;
  revealedIds: number[];
  revealedFinalists: ContestantDto[];
}

export function FinalistsRevealMode({
  activeRound,
  revealedIds,
  revealedFinalists,
}: FinalistsRevealModeProps) {
  const totalSlots = Math.max(activeRound?.advancement_count ?? 5, revealedIds.length);
  const finalistById = new Map(revealedFinalists.map((c) => [c.id, c]));

  const slots = Array.from({ length: totalSlots }, (_, index) => {
    const contestantId = revealedIds[index];
    const contestant = contestantId ? finalistById.get(contestantId) : undefined;
    return {
      slotNumber: index + 1,
      contestant,
    };
  });

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center px-6">
      <div className="mb-10 text-center">
        <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-amber-400/30 bg-amber-400/10 px-4 py-1 text-xs font-semibold uppercase tracking-[0.25em] text-amber-300">
          <Trophy className="h-3.5 w-3.5" />
          Official Qualifiers
        </div>
        <h2 className="text-4xl font-black tracking-tight text-white sm:text-5xl">
          Top {totalSlots} Finalists · {activeRound?.name ?? ''}
        </h2>
      </div>

      <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {slots.map(({ slotNumber, contestant }) => {
          const isRevealed = Boolean(contestant);
          return (
            <div
              key={slotNumber}
              className={`relative overflow-hidden rounded-2xl border p-6 transition-all duration-500 ${
                isRevealed
                  ? 'scale-100 border-amber-400/50 bg-gradient-to-br from-amber-500/20 via-slate-900/90 to-slate-950 shadow-xl shadow-amber-500/10'
                  : 'border-white/10 bg-white/5 opacity-80'
              }`}
            >
              {isRevealed && contestant ? (
                <div className="flex items-center gap-4">
                  <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-amber-400 text-2xl font-black text-slate-950">
                    #{contestant.candidate_number}
                  </div>
                  <div className="min-w-0">
                    <div className="text-xs font-semibold uppercase tracking-widest text-amber-300">
                      Finalist Spot #{slotNumber}
                    </div>
                    <div className="truncate text-2xl font-bold text-white">
                      {contestant.name}
                    </div>
                    <div className="truncate text-sm text-slate-300">
                      {contestant.origin_or_group}
                    </div>
                  </div>
                </div>
              ) : (
                <div className="flex h-16 items-center justify-center gap-3 text-slate-400">
                  <Lock className="h-5 w-5 text-slate-500" />
                  <span className="text-sm font-semibold uppercase tracking-[0.2em]">
                    Finalist Spot #{slotNumber}
                  </span>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
