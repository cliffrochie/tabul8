import { Trophy } from 'lucide-react';
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
    <div className="my-auto py-4 sm:py-6 max-w-5xl mx-auto w-full space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4 bg-slate-900/90 text-white p-4 sm:p-5 rounded-2xl border border-slate-700/80">
        <div>
          <div className="inline-flex items-center gap-1.5 text-emerald-400 font-mono-tech text-xs font-bold uppercase mb-1">
            <Trophy className="h-3.5 w-3.5" />
            Official Qualifiers
          </div>
          <h2 className="text-xl sm:text-2xl font-extrabold tracking-tight">
            Official Top {totalSlots} Finalists Reveal — {activeRound?.name ?? ''}
          </h2>
        </div>
        <div className="font-mono-tech text-xs text-slate-400">
          {revealedFinalists.length} / {totalSlots} Revealed
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        {slots.map(({ slotNumber, contestant }) => {
          const isRevealed = Boolean(contestant);
          return (
            <div
              key={slotNumber}
              className={`rounded-2xl p-6 min-h-[240px] flex flex-col justify-between text-center transition-all duration-300 border ${
                isRevealed
                  ? 'bg-white text-slate-900 border-white shadow-2xl scale-[1.01]'
                  : 'bg-slate-900/80 text-white border-slate-700'
              }`}
            >
              {isRevealed && contestant ? (
                <>
                  <div className="font-mono-tech text-xs font-bold px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 inline-block mx-auto">
                    Qualifier Spot #{slotNumber}
                  </div>
                  <div className="my-3">
                    {contestant.photo_url ? (
                      <img
                        src={contestant.photo_url}
                        alt={contestant.name}
                        className="h-24 w-20 mx-auto rounded-lg object-cover border border-slate-200"
                      />
                    ) : (
                      <div className="w-16 h-16 mx-auto rounded-2xl bg-slate-900 text-emerald-400 flex items-center justify-center font-mono-tech text-2xl font-extrabold">
                        #{contestant.candidate_number}
                      </div>
                    )}
                    <div className="font-mono-tech text-2xl font-extrabold text-slate-900 mt-3">
                      #{contestant.candidate_number}
                    </div>
                    <h3 className="text-xl font-extrabold text-slate-900 truncate">
                      {contestant.name}
                    </h3>
                    <p className="text-xs font-mono-tech text-slate-500 mt-0.5 truncate">
                      {contestant.origin_or_group}
                    </p>
                  </div>
                  <div className="text-[10px] font-mono-tech border-t border-slate-200 pt-2 text-slate-400">
                    VERIFIED BY TABULATION COMMITTEE
                  </div>
                </>
              ) : (
                <div className="my-auto space-y-3 py-6">
                  <div className="w-12 h-12 mx-auto rounded-2xl border border-slate-600 bg-slate-800 text-emerald-400 flex items-center justify-center font-mono-tech font-bold text-lg">
                    ?
                  </div>
                  <div className="font-mono-tech text-sm font-bold uppercase text-white">
                    Finalist Spot #{slotNumber}
                  </div>
                  <div className="text-xs font-mono-tech text-slate-400">
                    Awaiting Official Reveal
                  </div>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
