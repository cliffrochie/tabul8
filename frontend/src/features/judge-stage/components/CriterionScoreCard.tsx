import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

  const clamp = (raw: number) =>
    Math.min(criterion.max_score, Math.max(criterion.min_score, Number(raw.toFixed(2))));

  return (
    <Card className={cn(disabled && 'bg-slate-50/80 opacity-85')}>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <Badge variant="default">{criterion.weight_percentage}%</Badge>
              <h4 className="font-semibold text-sm sm:text-base text-slate-900">
                {index + 1}. {criterion.name}
              </h4>
            </div>
            {criterion.description && (
              <p className="text-xs text-slate-500 mt-1">{criterion.description}</p>
            )}
          </div>

          <div className="flex items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-3 py-1.5 tabular-nums">
            <div>
              <span className="block text-[10px] uppercase text-slate-500">
                Raw ({criterion.min_score}–{criterion.max_score})
              </span>
              <input
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
                className="w-20 text-right font-semibold text-base bg-white border border-slate-200 rounded-md px-2 py-0.5 focus:outline-none focus:ring-2 focus:ring-slate-900/20 disabled:bg-slate-100"
              />
            </div>
            <div className="h-8 w-px bg-slate-200" />
            <div className="text-right">
              <span className="block text-[10px] uppercase text-slate-500">Weighted</span>
              <span className="text-base font-bold text-slate-900">
                {weightedPts}{' '}
                <span className="text-xs font-normal text-slate-400">
                  /{criterion.weight_percentage}
                </span>
              </span>
            </div>
          </div>
        </div>

        <div>
          <input
            type="range"
            disabled={disabled}
            min={criterion.min_score}
            max={criterion.max_score}
            step={criterion.score_step}
            value={value}
            onChange={(e) => onChange(clamp(parseFloat(e.target.value)))}
            className="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-slate-900 disabled:cursor-not-allowed"
          />
          <div className="flex justify-between text-[11px] text-slate-400 mt-1 tabular-nums">
            <span>Min {criterion.min_score}</span>
            <span>Max {criterion.max_score}</span>
          </div>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-100">
          <div className="flex flex-wrap items-center gap-1.5">
            <span className="text-[11px] text-slate-500 mr-1">Presets:</span>
            {presets.map((p) => (
              <Button
                key={p}
                type="button"
                size="sm"
                variant={value === p ? 'default' : 'outline'}
                disabled={disabled}
                onClick={() => onChange(p)}
                className="tabular-nums"
              >
                {p}
              </Button>
            ))}
          </div>

          <div className="flex items-center gap-1.5">
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={disabled || value <= criterion.min_score}
              onClick={() => onChange(clamp(value - criterion.score_step))}
              className="tabular-nums"
            >
              -{criterion.score_step}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={disabled || value >= criterion.max_score}
              onClick={() => onChange(clamp(value + criterion.score_step))}
              className="tabular-nums"
            >
              +{criterion.score_step}
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
