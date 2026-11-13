import type { RoundDto } from '@/types/api';

interface CriteriaModeProps {
  activeRound: RoundDto | null;
}

export function CriteriaMode({ activeRound }: CriteriaModeProps) {
  const criteria = (activeRound?.criteria ?? []).filter((c) => c.parent_id === null);

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center px-6">
      <div className="mb-10 text-center">
        <p className="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">
          Official Judging Criteria
        </p>
        <h2 className="mt-2 text-4xl font-black tracking-tight text-white sm:text-5xl">
          {activeRound?.name ?? 'Competition Round'}
        </h2>
      </div>

      {criteria.length === 0 ? (
        <p className="text-center text-lg text-slate-400">
          No criteria configured for this round.
        </p>
      ) : (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {criteria.map((criterion) => (
            <div
              key={criterion.id}
              className="flex flex-col justify-between rounded-2xl border border-white/15 bg-white/5 p-7 backdrop-blur-md"
            >
              <div>
                <div className="flex items-baseline justify-between gap-4">
                  <h3 className="text-2xl font-bold text-white">
                    {criterion.name}
                  </h3>
                  <span className="text-3xl font-black tabular-nums text-amber-300">
                    {criterion.weight_percentage}%
                  </span>
                </div>
                {criterion.description && (
                  <p className="mt-3 text-sm leading-relaxed text-slate-300">
                    {criterion.description}
                  </p>
                )}
              </div>
              <div className="mt-6 border-t border-white/10 pt-3 text-xs uppercase tracking-wider text-slate-400">
                Scale: {criterion.min_score} – {criterion.max_score} pts
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
