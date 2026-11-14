import { cn } from '@/components/ui/cn';
import type { CriterionDto } from '@/types/api';

interface CriterionScoreCardProps {
  index: number;
  criterion: CriterionDto;
  value: number;
  disabled: boolean;
  onChange: (nextVal: number) => void;
}

export function CriterionScoreCard({
  index,
  criterion,
  value,
  disabled,
  onChange,
}: CriterionScoreCardProps) {
  const weightedPts = (value * (criterion.weight_percentage / 100)).toFixed(2);
  const presets = [75, 80, 85, 90, 95, 100].filter(
    (p) => p >= criterion.min_score && p <= criterion.max_score
  );

  const rangeSpan = Math.max(1, criterion.max_score - criterion.min_score);
  const pctFill = Math.min(
    100,
    Math.max(0, ((value - criterion.min_score) / rangeSpan) * 100)
  );

  const clamp = (raw: number) =>
    Math.min(criterion.max_score, Math.max(criterion.min_score, Number(raw.toFixed(2))));

  return (
    <div
      className={cn(
        'wf-box-sm p-4 sm:p-5 transition-all',
        disabled ? 'wf-hatch opacity-90' : 'bg-white'
      )}
    >
      <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-3">
        <div className="space-y-1 min-w-0">
          <div className="flex flex-wrap items-center gap-1.5">
            <span className="px-2.5 py-0.5 rounded-md bg-slate-900 text-white font-mono-tech text-xs font-bold">
              Weight: {criterion.weight_percentage}%
            </span>
            {index === 0 && (
              <span className="px-2 py-0.5 rounded-md border border-indigo-200 bg-indigo-50 text-indigo-700 font-mono-tech text-[10px] font-bold">
                #1 Tie-Breaker
              </span>
            )}
          </div>
          <h4 className="font-bold text-sm sm:text-base text-slate-900 tracking-tight pt-0.5">
            {index + 1}. {criterion.name}
          </h4>
          {criterion.description && (
            <p className="text-xs text-slate-500 leading-relaxed">{criterion.description}</p>
          )}
        </div>

        {/* Numeric Input & Weighted Calculation Box */}
        <div className="flex items-center justify-between sm:justify-end gap-3 bg-slate-50 px-3.5 py-2 rounded-xl border border-slate-200 font-mono-tech shrink-0">
          <div>
            <label
              htmlFor={`crit-num-${criterion.id}`}
              className="block text-[9px] uppercase text-slate-500 font-semibold"
            >
              Raw ({criterion.min_score}-{criterion.max_score})
            </label>
            <input
              id={`crit-num-${criterion.id}`}
              type="number"
              disabled={disabled}
              min={criterion.min_score}
              max={criterion.max_score}
              step={criterion.score_step}
              value={value}
              onChange={(e) => {
                const parsed = parseFloat(e.target.value);
                if (!Number.isNaN(parsed)) onChange(clamp(parsed));
              }}
              className="w-20 text-right font-bold text-base bg-white border border-slate-300 rounded-lg px-2 py-1 focus:outline-none focus:border-slate-900 disabled:bg-slate-100"
            />
          </div>
          <div className="h-8 w-px bg-slate-200" />
          <div className="text-right">
            <span className="block text-[9px] uppercase text-slate-500 font-semibold">
              Weighted
            </span>
            <span className="text-base font-bold text-slate-900">
              {weightedPts}{' '}
              <span className="text-[11px] text-slate-400 font-normal">
                /{criterion.weight_percentage}
              </span>
            </span>
          </div>
        </div>
      </div>

      {/* Range Slider + Visual Fill Track */}
      <div className="my-4">
        <div className="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden mb-2">
          <div
            className="h-full bg-slate-900 rounded-full transition-all duration-150"
            style={{ width: `${pctFill}%` }}
          />
        </div>
        <input
          type="range"
          aria-label={`${criterion.name} score slider`}
          disabled={disabled}
          min={criterion.min_score}
          max={criterion.max_score}
          step={criterion.score_step}
          value={value}
          onChange={(e) => onChange(clamp(parseFloat(e.target.value)))}
          className="wf-slider"
        />
        <div className="flex justify-between text-[10px] font-mono-tech text-slate-400 mt-1.5">
          <span>Min: {criterion.min_score}</span>
          <span>Step: {criterion.score_step}</span>
          <span>Max: {criterion.max_score}</span>
        </div>
      </div>

      {/* Touch-Friendly Presets & Steppers */}
      <div className="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-100">
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="text-[10px] font-mono-tech uppercase text-slate-400 mr-1">
            Quick Set:
          </span>
          {presets.map((p) => (
            <button
              key={p}
              type="button"
              disabled={disabled}
              onClick={() => onChange(p)}
              className={cn(
                'min-w-[38px] min-h-[34px] px-2.5 py-1 rounded-lg border text-xs font-mono-tech font-bold transition-all cursor-pointer disabled:opacity-45 disabled:cursor-not-allowed',
                value === p
                  ? 'bg-slate-900 text-white border-slate-900 shadow-sm'
                  : 'bg-white text-slate-700 border-slate-300 hover:border-slate-400'
              )}
            >
              {p}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-2 ml-auto">
          <button
            type="button"
            disabled={disabled || value <= criterion.min_score}
            onClick={() => onChange(clamp(value - criterion.score_step))}
            className="min-w-[48px] min-h-[38px] px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-mono-tech font-bold text-slate-800 hover:border-slate-400 active:scale-95 transition-all cursor-pointer disabled:opacity-45 disabled:cursor-not-allowed"
          >
            -{criterion.score_step}
          </button>
          <button
            type="button"
            disabled={disabled || value >= criterion.max_score}
            onClick={() => onChange(clamp(value + criterion.score_step))}
            className="min-w-[48px] min-h-[38px] px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-mono-tech font-bold text-slate-800 hover:border-slate-400 active:scale-95 transition-all cursor-pointer disabled:opacity-45 disabled:cursor-not-allowed"
          >
            +{criterion.score_step}
          </button>
        </div>
      </div>
    </div>
  );
}
