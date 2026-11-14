import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { Plus, Scale, Trash2 } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { RoundDto } from '@/types/api';
import {
  applyServerValidationErrors,
  createCriterionSchema,
  type CreateCriterionValues,
} from '../schemas/setupSchemas';

interface CriteriaPanelProps {
  rounds: RoundDto[];
  isBusy: boolean;
  onCreateCriterion: (payload: {
    roundId: number;
    parent_id?: number | null;
    name: string;
    description?: string;
    weight_percentage: number;
    min_score: number;
    max_score: number;
    score_step: number;
  }) => Promise<unknown>;
  onUpdateCriterion: (payload: {
    criterionId: number;
    weight_percentage?: number;
  }) => Promise<unknown>;
  onDeleteCriterion: (criterionId: number) => void;
}

export function CriteriaPanel({
  rounds,
  isBusy,
  onCreateCriterion,
  onUpdateCriterion,
  onDeleteCriterion,
}: CriteriaPanelProps) {
  const [selectedRoundId, setSelectedRoundId] = useState<number>(
    rounds[0]?.id ?? 0,
  );
  const activeRound =
    rounds.find((r) => r.id === selectedRoundId) ?? rounds[0] ?? null;

  const criteria = activeRound?.criteria ?? [];
  const parentIds = new Set(criteria.map((c) => c.parent_id).filter(Boolean));
  const leafCriteria = criteria.filter((c) => !parentIds.has(c.id));
  const leafSum = leafCriteria.reduce(
    (sum, c) => sum + Number(c.weight_percentage),
    0,
  );
  const isSumExact = Math.abs(leafSum - 100) < 0.01;

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CreateCriterionValues>({
    resolver: zodResolver(createCriterionSchema),
    defaultValues: {
      parent_id: null,
      name: '',
      description: '',
      weight_percentage: 25,
      min_score: 70,
      max_score: 100,
      score_step: 0.5,
    },
  });

  const handleEqualSplit = async () => {
    if (leafCriteria.length === 0) return;
    const base = Number((100 / leafCriteria.length).toFixed(2));
    const remainder = Number(
      (100 - base * (leafCriteria.length - 1)).toFixed(2),
    );
    await Promise.all(
      leafCriteria.map((c, idx) =>
        onUpdateCriterion({
          criterionId: c.id,
          weight_percentage: idx === leafCriteria.length - 1 ? remainder : base,
        }),
      ),
    );
  };

  const handleNormalize = async () => {
    if (leafCriteria.length === 0 || leafSum <= 0) return;
    let running = 0;
    await Promise.all(
      leafCriteria.map((c, idx) => {
        const nextWeight =
          idx === leafCriteria.length - 1
            ? Number((100 - running).toFixed(2))
            : Number(((Number(c.weight_percentage) / leafSum) * 100).toFixed(2));
        running += nextWeight;
        return onUpdateCriterion({
          criterionId: c.id,
          weight_percentage: nextWeight,
        });
      }),
    );
  };

  const onSubmit = async (values: CreateCriterionValues) => {
    if (!activeRound) return;
    try {
      await onCreateCriterion({
        roundId: activeRound.id,
        ...values,
      });
      reset({
        parent_id: null,
        name: '',
        description: '',
        weight_percentage: Math.max(0, Number((100 - leafSum).toFixed(2))),
        min_score: 70,
        max_score: 100,
        score_step: 0.5,
      });
    } catch (err) {
      applyServerValidationErrors(err, setError);
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-12">
      <Card className="lg:col-span-7">
        <CardHeader className="flex flex-wrap flex-row items-center justify-between gap-3">
          <div>
            <CardTitle className="text-base">
              WF-11 · Scoring Rubric &amp; Leaf Weights
            </CardTitle>
            <CardDescription>
              Leaf criteria weights per round must total exactly 100.00%.
            </CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <select
              aria-label="Select Round for Criteria"
              value={activeRound?.id ?? ''}
              onChange={(e) => setSelectedRoundId(Number(e.target.value))}
              className="h-8 rounded-lg border border-slate-300 bg-white px-2.5 text-xs"
            >
              {rounds.map((r) => (
                <option key={r.id} value={r.id}>
                  R{r.sequence}: {r.name}
                </option>
              ))}
            </select>
            <Badge variant={isSumExact ? 'success' : 'warning'}>
              {leafSum.toFixed(2)}% / 100%
            </Badge>
          </div>
        </CardHeader>

        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={isBusy || leafCriteria.length === 0}
              onClick={() => {
                void handleEqualSplit();
              }}
              className="gap-1 text-xs"
            >
              <Scale className="h-3.5 w-3.5" />
              Equal Split
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={isBusy || leafCriteria.length === 0 || leafSum <= 0}
              onClick={() => {
                void handleNormalize();
              }}
              className="text-xs"
            >
              Normalize to 100%
            </Button>
          </div>

          <div className="divide-y divide-slate-100 rounded-xl border border-slate-200">
            {criteria.length === 0 && (
              <p className="p-4 text-center text-xs text-slate-500">
                No criteria configured yet for this round.
              </p>
            )}
            {criteria.map((c) => {
              const isParentGroup = parentIds.has(c.id);
              return (
                <div
                  key={c.id}
                  className={`flex items-center justify-between gap-3 px-4 py-3 ${
                    c.parent_id ? 'pl-8 bg-slate-50/60' : 'bg-white'
                  }`}
                >
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium text-slate-900">
                        {c.name}
                      </span>
                      {isParentGroup && (
                        <Badge variant="secondary" className="text-[10px]">
                          Group Header
                        </Badge>
                      )}
                    </div>
                    <div className="text-xs text-slate-500 tabular-nums">
                      Range: {c.min_score}–{c.max_score} (step {c.score_step})
                    </div>
                  </div>

                  <div className="flex items-center gap-3">
                    <span className="text-sm font-bold tabular-nums text-slate-800">
                      {Number(c.weight_percentage).toFixed(2)}%
                    </span>
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      disabled={isBusy}
                      onClick={() => onDeleteCriterion(c.id)}
                      className="text-rose-600 hover:bg-rose-50"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>
        </CardContent>
      </Card>

      <Card className="lg:col-span-5">
        <CardHeader>
          <CardTitle className="text-base">Add Criterion</CardTitle>
          <CardDescription>
            Define weighted criteria or attach child sub-criteria to a parent group.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-3">
            <div className="space-y-1">
              <label className="text-xs font-medium text-slate-700" htmlFor="crit-parent">
                Parent Group (Optional)
              </label>
              <select
                id="crit-parent"
                {...register('parent_id', {
                  setValueAs: (v) =>
                    v === '' || v === null || v === undefined
                      ? null
                      : Number(v),
                })}
                className="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900"
              >
                <option value="">Top-Level Criterion / Group</option>
                {criteria
                  .filter((c) => c.parent_id === null)
                  .map((c) => (
                    <option key={c.id} value={c.id}>
                      Child of: {c.name}
                    </option>
                  ))}
              </select>
              {errors.parent_id && (
                <p className="text-xs text-rose-600">
                  {errors.parent_id.message}
                </p>
              )}
            </div>

            <div className="space-y-1">
              <label className="text-xs font-medium text-slate-700" htmlFor="crit-name">
                Criterion Name
              </label>
              <Input id="crit-name" placeholder="Evening Gown Poise" {...register('name')} />
              {errors.name && (
                <p className="text-xs text-rose-600">{errors.name.message}</p>
              )}
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-700" htmlFor="crit-weight">
                  Weight (%)
                </label>
                <Input
                  id="crit-weight"
                  type="number"
                  step="0.01"
                  {...register('weight_percentage', { valueAsNumber: true })}
                />
                {errors.weight_percentage && (
                  <p className="text-xs text-rose-600">
                    {errors.weight_percentage.message}
                  </p>
                )}
              </div>

              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-700" htmlFor="crit-step">
                  Score Step
                </label>
                <Input
                  id="crit-step"
                  type="number"
                  step="0.1"
                  {...register('score_step', { valueAsNumber: true })}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-700" htmlFor="crit-min">
                  Min Score
                </label>
                <Input
                  id="crit-min"
                  type="number"
                  step="0.5"
                  {...register('min_score', { valueAsNumber: true })}
                />
                {errors.min_score && (
                  <p className="text-xs text-rose-600">{errors.min_score.message}</p>
                )}
              </div>
              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-700" htmlFor="crit-max">
                  Max Score
                </label>
                <Input
                  id="crit-max"
                  type="number"
                  step="0.5"
                  {...register('max_score', { valueAsNumber: true })}
                />
              </div>
            </div>

            <Button
              type="submit"
              disabled={isSubmitting || !activeRound}
              className="w-full gap-1.5"
            >
              <Plus className="h-4 w-4" />
              Add Criterion
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
