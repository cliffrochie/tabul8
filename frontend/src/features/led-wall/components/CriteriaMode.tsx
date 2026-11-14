import type { RoundDto } from '@/types/api';

interface CriteriaModeProps {
  activeRound: RoundDto | null;
}

export function CriteriaMode({ activeRound }: CriteriaModeProps) {
  const criteria = (activeRound?.criteria ?? []).filter((c) => c.parent_id === null);

  return (
    <div className="my-auto py-4 sm:py-8 max-w-5xl mx-auto w-full space-y-6">
      <div className="text-center">
        <span className="px-3 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700 font-mono-tech text-xs font-bold">
          PUBLIC TRANSPARENCY DISPLAY
        </span>
        <h2 className="text-2xl sm:text-3xl font-extrabold tracking-tight mt-3 text-white">
          Official Criteria for Judging — {activeRound?.name ?? 'Competition Round'}
        </h2>
      </div>

      {criteria.length === 0 ? (
        <p className="text-center text-base font-mono-tech text-slate-400">
          No criteria configured for this round.
        </p>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {criteria.map((criterion) => (
            <div
              key={criterion.id}
              className="rounded-2xl p-6 bg-slate-900/90 border border-slate-700/80 text-white flex flex-col justify-between shadow-xl"
            >
              <div>
                <div className="font-mono-tech text-4xl font-extrabold text-emerald-400 border-b border-slate-800 pb-3 mb-3">
                  {criterion.weight_percentage}%
                </div>
                <h3 className="text-lg font-bold text-white mb-2">
                  {criterion.name}
                </h3>
                {criterion.description && (
                  <p className="text-xs text-slate-300 leading-relaxed">
                    {criterion.description}
                  </p>
                )}
              </div>
              <div className="mt-6 pt-3 border-t border-slate-800 font-mono-tech text-xs text-slate-400 font-semibold">
                Scale Bounds: {criterion.min_score} to {criterion.max_score} pts
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
