import { useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import {
  FileSpreadsheet,
  Hash,
  ImagePlus,
  Plus,
  Trash2,
  UserCheck,
  UserX,
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
  createContestantSchema,
  type CreateContestantValues,
} from '@/features/event-setup/schemas/setupSchemas';
import type { ContestantDto, RoundDto } from '@/types/api';

interface ContestantsPanelProps {
  contestants: ContestantDto[];
  activeRound: RoundDto | null;
  isBusy: boolean;
  onCreateContestant: (payload: {
    candidate_number?: number;
    name: string;
    origin_or_group: string;
    category?: string;
    performance_meta?: string;
  }) => Promise<unknown>;
  onUpdateContestant: (payload: {
    contestantId: number;
    name?: string;
    origin_or_group?: string;
    status?: ContestantDto['status'];
  }) => void;
  onDeleteContestant: (contestantId: number) => void;
  onImportCsv: (csvText: string) => Promise<unknown>;
  onRenumber: () => void;
  onUploadPhoto: (payload: { contestantId: number; file: File }) => void;
}

export function ContestantsPanel({
  contestants,
  activeRound,
  isBusy,
  onCreateContestant,
  onUpdateContestant,
  onDeleteContestant,
  onImportCsv,
  onRenumber,
  onUploadPhoto,
}: ContestantsPanelProps) {
  const [csvText, setCsvText] = useState(
    'name,origin_or_group,category,performance_meta\n',
  );
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editName, setEditName] = useState('');
  const [editOrigin, setEditOrigin] = useState('');

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CreateContestantValues>({
    resolver: zodResolver(createContestantSchema),
    defaultValues: {
      name: '',
      origin_or_group: '',
      category: 'Open',
      performance_meta: '',
    },
  });

  const handleCreate = handleSubmit(async (values) => {
    try {
      await onCreateContestant({
        candidate_number: values.candidate_number,
        name: values.name,
        origin_or_group: values.origin_or_group,
        category: values.category,
        performance_meta: values.performance_meta || undefined,
      });
      reset({
        name: '',
        origin_or_group: '',
        category: values.category,
        performance_meta: '',
      });
    } catch (err) {
      applyServerValidationErrors(err, setError);
    }
  });

  const handleImportSubmit = async () => {
    if (!csvText.trim()) return;
    await onImportCsv(csvText);
    setCsvText('name,origin_or_group,category,performance_meta\n');
  };

  const sorted = [...contestants].sort(
    (a, b) => a.candidate_number - b.candidate_number,
  );

  return (
    <div className="space-y-6">
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Add Contestant</CardTitle>
            <CardDescription>
              Register an individual performer or candidate. Leave candidate
              number blank to auto-assign the next slot.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleCreate} className="space-y-3">
              {errors.root?.message && (
                <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                  {errors.root.message}
                </p>
              )}
              <div className="grid gap-3 sm:grid-cols-4">
                <div className="space-y-1">
                  <label className="text-xs font-medium text-slate-600">
                    # (Optional)
                  </label>
                  <Input
                    type="number"
                    min={1}
                    placeholder="Auto"
                    {...register('candidate_number', {
                      setValueAs: (v) =>
                        v === '' || Number.isNaN(Number(v))
                          ? undefined
                          : Number(v),
                    })}
                  />
                  {errors.candidate_number && (
                    <p className="text-xs text-red-600">
                      {errors.candidate_number.message}
                    </p>
                  )}
                </div>
                <div className="space-y-1 sm:col-span-3">
                  <label className="text-xs font-medium text-slate-600">
                    Full Name / Team Name
                  </label>
                  <Input
                    placeholder="e.g. Candidate One"
                    {...register('name')}
                  />
                  {errors.name && (
                    <p className="text-xs text-red-600">{errors.name.message}</p>
                  )}
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                  <label className="text-xs font-medium text-slate-600">
                    Origin / Municipality / Group
                  </label>
                  <Input
                    placeholder="e.g. Barangay Poblacion"
                    {...register('origin_or_group')}
                  />
                  {errors.origin_or_group && (
                    <p className="text-xs text-red-600">
                      {errors.origin_or_group.message}
                    </p>
                  )}
                </div>
                <div className="space-y-1">
                  <label className="text-xs font-medium text-slate-600">
                    Category
                  </label>
                  <Input placeholder="Open" {...register('category')} />
                  {errors.category && (
                    <p className="text-xs text-red-600">
                      {errors.category.message}
                    </p>
                  )}
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-600">
                  Performance Piece / Notes (Optional)
                </label>
                <Input
                  placeholder="e.g. Acoustic Ballad · 4m 30s"
                  {...register('performance_meta')}
                />
              </div>

              <Button
                type="submit"
                disabled={isBusy || isSubmitting}
                className="gap-1.5"
              >
                <Plus className="h-4 w-4" />
                Add Contestant
              </Button>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <FileSpreadsheet className="h-4 w-4 text-slate-600" />
              Bulk CSV Import &amp; Sequencing
            </CardTitle>
            <CardDescription>
              Paste CSV rows (`name,origin_or_group,category,performance_meta`)
              or renumber all contestants cleanly from #1.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <textarea
              value={csvText}
              onChange={(e) => setCsvText(e.target.value)}
              rows={5}
              className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-900 focus:border-slate-500 focus:outline-none"
              placeholder="name,origin_or_group,category,performance_meta"
            />
            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                variant="secondary"
                disabled={isBusy || !csvText.trim()}
                onClick={handleImportSubmit}
                className="gap-1.5"
              >
                <FileSpreadsheet className="h-4 w-4" />
                Import CSV
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={isBusy || sorted.length === 0}
                onClick={onRenumber}
                className="gap-1.5"
              >
                <Hash className="h-4 w-4" />
                Auto-Renumber (#1..#{sorted.length})
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-2">
          <div>
            <CardTitle className="text-base">
              Contestant Roster ({sorted.length})
            </CardTitle>
            <CardDescription>
              {activeRound
                ? `Qualification badges reflect active round: ${activeRound.name}`
                : 'No active round started yet — full initial roster.'}
            </CardDescription>
          </div>
        </CardHeader>
        <CardContent>
          {sorted.length === 0 ? (
            <p className="py-6 text-center text-sm text-slate-500">
              No contestants registered yet. Add one above or import via CSV.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full border-collapse text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase text-slate-500">
                    <th className="py-2 pr-3">#</th>
                    <th className="py-2 pr-3">Contestant</th>
                    <th className="py-2 pr-3">Origin / Group</th>
                    <th className="py-2 pr-3">Round Status</th>
                    <th className="py-2 pr-3">Portrait</th>
                    <th className="py-2 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {sorted.map((contestant) => {
                    const isEditing = editingId === contestant.id;
                    const isQualified =
                      contestant.is_qualified_in_active_round ?? true;
                    return (
                      <tr key={contestant.id} className="hover:bg-slate-50/70">
                        <td className="py-2.5 pr-3 font-mono font-semibold text-slate-700">
                          #{contestant.candidate_number}
                        </td>
                        <td className="py-2.5 pr-3">
                          {isEditing ? (
                            <Input
                              value={editName}
                              onChange={(e) => setEditName(e.target.value)}
                              className="h-8 text-xs"
                            />
                          ) : (
                            <div>
                              <p className="font-medium text-slate-900">
                                {contestant.name}
                              </p>
                              <p className="text-xs text-slate-500">
                                {contestant.category}
                                {contestant.performance_meta
                                  ? ` · ${contestant.performance_meta}`
                                  : ''}
                              </p>
                            </div>
                          )}
                        </td>
                        <td className="py-2.5 pr-3 text-slate-700">
                          {isEditing ? (
                            <Input
                              value={editOrigin}
                              onChange={(e) => setEditOrigin(e.target.value)}
                              className="h-8 text-xs"
                            />
                          ) : (
                            contestant.origin_or_group
                          )}
                        </td>
                        <td className="py-2.5 pr-3">
                          <div className="flex flex-wrap items-center gap-1.5">
                            <Badge
                              variant={
                                contestant.status === 'active'
                                  ? 'success'
                                  : contestant.status === 'eliminated'
                                    ? 'warning'
                                    : 'secondary'
                              }
                            >
                              {contestant.status}
                            </Badge>
                            {activeRound && (
                              <Badge
                                variant={isQualified ? 'info' : 'secondary'}
                                className="gap-1"
                              >
                                {isQualified ? (
                                  <>
                                    <UserCheck className="h-3 w-3" />
                                    {contestant.qualified_from_rank
                                      ? `Q (Rank #${contestant.qualified_from_rank})`
                                      : 'Qualified'}
                                  </>
                                ) : (
                                  <>
                                    <UserX className="h-3 w-3" />
                                    {contestant.eliminated_in_round_name
                                      ? `Out (${contestant.eliminated_in_round_name})`
                                      : 'Eliminated'}
                                  </>
                                )}
                              </Badge>
                            )}
                          </div>
                        </td>
                        <td className="py-2.5 pr-3">
                          <label className="inline-flex cursor-pointer items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            <ImagePlus className="h-3.5 w-3.5" />
                            {contestant.photo_url ? 'Replace' : 'Upload'}
                            <input
                              type="file"
                              accept="image/jpeg,image/png,image/webp"
                              className="sr-only"
                              onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                  onUploadPhoto({
                                    contestantId: contestant.id,
                                    file,
                                  });
                                }
                                e.target.value = '';
                              }}
                            />
                          </label>
                        </td>
                        <td className="py-2.5 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            {isEditing ? (
                              <>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="default"
                                  disabled={isBusy}
                                  onClick={() => {
                                    onUpdateContestant({
                                      contestantId: contestant.id,
                                      name: editName.trim(),
                                      origin_or_group: editOrigin.trim(),
                                    });
                                    setEditingId(null);
                                  }}
                                >
                                  Save
                                </Button>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="ghost"
                                  onClick={() => setEditingId(null)}
                                >
                                  Cancel
                                </Button>
                              </>
                            ) : (
                              <>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="outline"
                                  onClick={() => {
                                    setEditingId(contestant.id);
                                    setEditName(contestant.name);
                                    setEditOrigin(contestant.origin_or_group);
                                  }}
                                >
                                  Edit
                                </Button>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="ghost"
                                  disabled={isBusy}
                                  onClick={() =>
                                    onUpdateContestant({
                                      contestantId: contestant.id,
                                      status:
                                        contestant.status === 'withdrawn'
                                          ? 'active'
                                          : 'withdrawn',
                                    })
                                  }
                                >
                                  {contestant.status === 'withdrawn'
                                    ? 'Reinstate'
                                    : 'Withdraw'}
                                </Button>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="destructive"
                                  disabled={isBusy}
                                  onClick={() =>
                                    onDeleteContestant(contestant.id)
                                  }
                                >
                                  <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
