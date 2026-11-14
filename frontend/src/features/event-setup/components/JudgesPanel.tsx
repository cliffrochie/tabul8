import { useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import {
  Crown,
  FileDown,
  KeyRound,
  Plus,
  RefreshCw,
  ShieldAlert,
  Trash2,
} from 'lucide-react';
import { useForm } from 'react-hook-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
  applyServerValidationErrors,
  createJudgeSchema,
  type CreateJudgeValues,
} from '@/features/event-setup/schemas/setupSchemas';
import type { JudgeDto } from '@/types/api';

interface JudgesPanelProps {
  eventId: number;
  judges: JudgeDto[];
  isBusy: boolean;
  onCreateJudge: (payload: {
    judge_number?: number;
    name: string;
    role_title: string;
    is_chairman: boolean;
  }) => Promise<{ judge: JudgeDto; plain_pin: string }>;
  onUpdateJudge: (payload: {
    judgeId: number;
    is_chairman?: boolean;
    is_active?: boolean;
    name?: string;
    role_title?: string;
  }) => void;
  onDeleteJudge: (judgeId: number) => void;
  onRegeneratePin: (
    judgeId: number,
  ) => Promise<{ judge: JudgeDto; plain_pin: string }>;
  onDownloadCardsPdf: (eventId: number) => Promise<void>;
}

export function JudgesPanel({
  eventId,
  judges,
  isBusy,
  onCreateJudge,
  onUpdateJudge,
  onDeleteJudge,
  onRegeneratePin,
  onDownloadCardsPdf,
}: JudgesPanelProps) {
  const [revealedPin, setRevealedPin] = useState<{
    judgeName: string;
    pin: string;
  } | null>(null);
  const [isDownloadingPdf, setIsDownloadingPdf] = useState(false);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CreateJudgeValues>({
    resolver: zodResolver(createJudgeSchema),
    defaultValues: {
      name: '',
      role_title: 'Panel Judge',
      is_chairman: false,
    },
  });

  const handleCreate = handleSubmit(async (values) => {
    try {
      const res = await onCreateJudge({
        judge_number: values.judge_number,
        name: values.name,
        role_title: values.role_title,
        is_chairman: values.is_chairman,
      });
      setRevealedPin({ judgeName: res.judge.name, pin: res.plain_pin });
      reset({
        name: '',
        role_title: 'Panel Judge',
        is_chairman: false,
      });
    } catch (err) {
      applyServerValidationErrors(err, setError);
    }
  });

  const handleRegenerate = async (judgeId: number) => {
    const res = await onRegeneratePin(judgeId);
    setRevealedPin({ judgeName: res.judge.name, pin: res.plain_pin });
  };

  const handleDownloadPdf = async () => {
    setIsDownloadingPdf(true);
    try {
      await onDownloadCardsPdf(eventId);
    } finally {
      setIsDownloadingPdf(false);
    }
  };

  const sorted = [...judges].sort((a, b) => a.judge_number - b.judge_number);
  const activeCount = sorted.filter((j) => j.is_active).length;

  return (
    <div className="space-y-6">
      {revealedPin && (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-amber-950">
          <div className="flex items-center gap-2.5 text-sm">
            <KeyRound className="h-4 w-4 shrink-0 text-amber-700" />
            <span>
              One-time Judge Access PIN for{' '}
              <strong>{revealedPin.judgeName}</strong>:
            </span>
            <code className="rounded bg-white px-2.5 py-0.5 font-mono text-base font-bold tracking-widest text-slate-900 border border-amber-200">
              {revealedPin.pin}
            </code>
          </div>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => setRevealedPin(null)}
          >
            Dismiss
          </Button>
        </div>
      )}

      <Card>
        <CardHeader className="flex flex-wrap flex-row items-center justify-between gap-3">
          <div>
            <CardTitle className="text-base">Add Judge &amp; PIN Cards</CardTitle>
            <CardDescription>
              Create judges with auto-generated 6-digit PINs or export printable
              PDF tent cards for judge tablets.
            </CardDescription>
          </div>
          <Button
            type="button"
            variant="secondary"
            disabled={isDownloadingPdf || sorted.length === 0}
            onClick={handleDownloadPdf}
            className="gap-1.5"
          >
            <FileDown className="h-4 w-4" />
            {isDownloadingPdf
              ? 'Generating PDF...'
              : 'Download Tent Cards PDF'}
          </Button>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleCreate} className="space-y-3">
            {errors.root?.message && (
              <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                {errors.root.message}
              </p>
            )}
            <div className="grid gap-3 sm:grid-cols-12">
              <div className="space-y-1 sm:col-span-2">
                <label className="text-xs font-medium text-slate-600">
                  Judge #
                </label>
                <Input
                  type="number"
                  min={1}
                  placeholder="Auto"
                  {...register('judge_number', {
                    setValueAs: (v) =>
                      v === '' || Number.isNaN(Number(v))
                        ? undefined
                        : Number(v),
                  })}
                />
              </div>
              <div className="space-y-1 sm:col-span-4">
                <label className="text-xs font-medium text-slate-600">
                  Judge Name
                </label>
                <Input placeholder="e.g. Hon. Maria Santos" {...register('name')} />
                {errors.name && (
                  <p className="text-xs text-red-600">{errors.name.message}</p>
                )}
              </div>
              <div className="space-y-1 sm:col-span-4">
                <label className="text-xs font-medium text-slate-600">
                  Role / Title
                </label>
                <Input placeholder="Panel Judge" {...register('role_title')} />
                {errors.role_title && (
                  <p className="text-xs text-red-600">
                    {errors.role_title.message}
                  </p>
                )}
              </div>
              <div className="flex items-end pb-2 sm:col-span-2">
                <label className="flex items-center gap-2 text-xs font-medium text-slate-700">
                  <input
                    type="checkbox"
                    className="h-4 w-4 rounded border-slate-300"
                    {...register('is_chairman')}
                  />
                  Chairman
                </label>
              </div>
            </div>
            <Button
              type="submit"
              disabled={isBusy || isSubmitting}
              className="gap-1.5"
            >
              <Plus className="h-4 w-4" />
              Add Judge &amp; Issue PIN
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-2">
          <div>
            <CardTitle className="text-base">
              Judging Panel ({sorted.length})
            </CardTitle>
            <CardDescription>
              Active Quorum: {activeCount} of {sorted.length} judges active.
              Regenerating a PIN immediately revokes the judge&apos;s previous
              session token.
            </CardDescription>
          </div>
          {activeCount < 3 && sorted.length > 0 && (
            <Badge variant="warning" className="gap-1">
              <ShieldAlert className="h-3.5 w-3.5" />
              Minimum 3 active judges recommended
            </Badge>
          )}
        </CardHeader>
        <CardContent>
          {sorted.length === 0 ? (
            <p className="py-6 text-center text-sm text-slate-500">
              No judges configured yet.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full border-collapse text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase text-slate-500">
                    <th className="py-2 pr-3">#</th>
                    <th className="py-2 pr-3">Judge</th>
                    <th className="py-2 pr-3">Quorum &amp; Role</th>
                    <th className="py-2 pr-3">Last Seen</th>
                    <th className="py-2 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {sorted.map((judge) => (
                    <tr key={judge.id} className="hover:bg-slate-50/70">
                      <td className="py-2.5 pr-3 font-mono font-semibold text-slate-700">
                        J{judge.judge_number}
                      </td>
                      <td className="py-2.5 pr-3">
                        <p className="font-medium text-slate-900">
                          {judge.name}
                        </p>
                        <p className="text-xs text-slate-500">
                          {judge.role_title}
                        </p>
                      </td>
                      <td className="py-2.5 pr-3">
                        <div className="flex flex-wrap items-center gap-1.5">
                          <Badge
                            variant={judge.is_active ? 'success' : 'secondary'}
                          >
                            {judge.is_active ? 'Active' : 'Inactive'}
                          </Badge>
                          {judge.is_chairman && (
                            <Badge variant="warning" className="gap-1">
                              <Crown className="h-3 w-3" />
                              Chairman
                            </Badge>
                          )}
                        </div>
                      </td>
                      <td className="py-2.5 pr-3 text-xs text-slate-500">
                        {judge.last_seen_at
                          ? new Date(judge.last_seen_at).toLocaleTimeString()
                          : 'Never'}
                      </td>
                      <td className="py-2.5 text-right">
                        <div className="flex flex-wrap items-center justify-end gap-1.5">
                          {!judge.is_chairman && (
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={isBusy}
                              onClick={() =>
                                onUpdateJudge({
                                  judgeId: judge.id,
                                  is_chairman: true,
                                })
                              }
                            >
                              Set Chairman
                            </Button>
                          )}
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={isBusy}
                            onClick={() =>
                              onUpdateJudge({
                                judgeId: judge.id,
                                is_active: !judge.is_active,
                              })
                            }
                          >
                            {judge.is_active ? 'Deactivate' : 'Activate'}
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            disabled={isBusy}
                            onClick={() => void handleRegenerate(judge.id)}
                            className="gap-1"
                          >
                            <RefreshCw className="h-3.5 w-3.5" />
                            Regenerate PIN
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            disabled={isBusy}
                            onClick={() => onDeleteJudge(judge.id)}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
