import { useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { Plus, Save, ShieldCheck, Trash2, UserPlus } from 'lucide-react';
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
import type { StaffUserDto } from '@/features/event-setup/api/setupApi';
import {
  applyServerValidationErrors,
  createStaffUserSchema,
  type CreateStaffUserValues,
} from '@/features/event-setup/schemas/setupSchemas';
import type { EventDto } from '@/types/api';

interface PenaltyPreset {
  code: string;
  label: string;
  points: number;
}

const DEFAULT_PENALTIES: PenaltyPreset[] = [
  { code: 'OVERTIME_30S', label: 'Time Overrun (>30s)', points: 1.0 },
  { code: 'COSTUME_PROP', label: 'Prohibited Prop / Wardrobe', points: 2.0 },
  { code: 'LATE_CALL', label: 'Late Stage Call', points: 0.5 },
];

function parsePenaltyPresets(settings: Record<string, unknown>): PenaltyPreset[] {
  const raw = settings?.penalty_presets;
  if (!Array.isArray(raw) || raw.length === 0) {
    return DEFAULT_PENALTIES;
  }
  return raw
    .map((item) => {
      if (typeof item !== 'object' || item === null) return null;
      const rec = item as Record<string, unknown>;
      return {
        code: typeof rec.code === 'string' ? rec.code : 'PENALTY',
        label: typeof rec.label === 'string' ? rec.label : 'Deduction',
        points: Number(rec.points) || 0,
      };
    })
    .filter((v): v is PenaltyPreset => v !== null);
}

interface RulesUsersPanelProps {
  event: EventDto;
  isSuperAdmin: boolean;
  staffUsers: StaffUserDto[];
  isBusy: boolean;
  onSaveEventSettings: (settings: Record<string, unknown>) => Promise<unknown>;
  onCreateStaffUser: (payload: {
    name: string;
    email: string;
    password: string;
    role: StaffUserDto['role'];
  }) => Promise<unknown>;
  onUpdateStaffUser: (payload: {
    userId: number;
    is_active?: boolean;
    role?: StaffUserDto['role'];
  }) => void;
  onDeleteStaffUser: (userId: number) => void;
}

export function RulesUsersPanel({
  event,
  isSuperAdmin,
  staffUsers,
  isBusy,
  onSaveEventSettings,
  onCreateStaffUser,
  onUpdateStaffUser,
  onDeleteStaffUser,
}: RulesUsersPanelProps) {
  const [presets, setPresets] = useState<PenaltyPreset[]>(() =>
    parsePenaltyPresets(event.settings ?? {}),
  );
  const [tieMode, setTieMode] = useState<string>(
    typeof event.settings?.tie_break_policy === 'string'
      ? event.settings.tie_break_policy
      : 'chairman_override',
  );
  const [newCode, setNewCode] = useState('');
  const [newLabel, setNewLabel] = useState('');
  const [newPoints, setNewPoints] = useState('1');

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CreateStaffUserValues>({
    resolver: zodResolver(createStaffUserSchema),
    defaultValues: {
      name: '',
      email: '',
      password: '',
      role: 'tabulator',
    },
  });

  const handleAddPreset = () => {
    const code = newCode.trim().toUpperCase().replace(/\s+/g, '_');
    const label = newLabel.trim();
    const points = Number(newPoints);
    if (!code || !label || Number.isNaN(points) || points <= 0) return;

    setPresets((prev) => [...prev, { code, label, points }]);
    setNewCode('');
    setNewLabel('');
    setNewPoints('1');
  };

  const handleSaveRules = async () => {
    await onSaveEventSettings({
      ...(event.settings ?? {}),
      penalty_presets: presets,
      tie_break_policy: tieMode,
    });
  };

  const handleCreateStaff = handleSubmit(async (values) => {
    try {
      await onCreateStaffUser(values);
      reset({ name: '', email: '', password: '', role: 'tabulator' });
    } catch (err) {
      applyServerValidationErrors(err, setError);
    }
  });

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader className="flex flex-wrap flex-row items-center justify-between gap-3">
          <div>
            <CardTitle className="text-base">
              Penalty Catalogue &amp; Scoring Rules
            </CardTitle>
            <CardDescription>
              Configure standard deduction presets and cutoff tie-resolution
              policy stored in event settings.
            </CardDescription>
          </div>
          <Button
            type="button"
            disabled={isBusy}
            onClick={() => void handleSaveRules()}
            className="gap-1.5"
          >
            <Save className="h-4 w-4" />
            Save Event Rules
          </Button>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="max-w-sm space-y-1">
            <label className="text-xs font-medium text-slate-600">
              Cutoff Tie Policy
            </label>
            <select
              value={tieMode}
              onChange={(e) => setTieMode(e.target.value)}
              className="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900"
            >
              <option value="chairman_override">
                Require Explicit Chairman / Wildcard Override (409 Guard)
              </option>
              <option value="expand_cutoff">
                Auto-Expand Cutoff Slot Advisory
              </option>
            </select>
          </div>

          <div className="space-y-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Deduction Presets
            </p>
            <div className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
              {presets.map((preset, idx) => (
                <div
                  key={`${preset.code}-${idx}`}
                  className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                >
                  <div className="flex items-center gap-2">
                    <Badge variant="secondary" className="font-mono text-xs">
                      {preset.code}
                    </Badge>
                    <span className="font-medium text-slate-900">
                      {preset.label}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="font-mono text-xs font-semibold text-red-600">
                      -{preset.points} pts
                    </span>
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      onClick={() =>
                        setPresets((prev) => prev.filter((_, i) => i !== idx))
                      }
                    >
                      <Trash2 className="h-3.5 w-3.5 text-slate-500" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>

            <div className="grid gap-2 pt-2 sm:grid-cols-12">
              <Input
                value={newCode}
                onChange={(e) => setNewCode(e.target.value)}
                placeholder="CODE (e.g. OVERTIME)"
                className="sm:col-span-3"
              />
              <Input
                value={newLabel}
                onChange={(e) => setNewLabel(e.target.value)}
                placeholder="Preset description"
                className="sm:col-span-5"
              />
              <Input
                type="number"
                step="0.5"
                min="0.5"
                value={newPoints}
                onChange={(e) => setNewPoints(e.target.value)}
                placeholder="Pts"
                className="sm:col-span-2"
              />
              <Button
                type="button"
                variant="outline"
                onClick={handleAddPreset}
                className="gap-1 sm:col-span-2"
              >
                <Plus className="h-3.5 w-3.5" />
                Add
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <ShieldCheck className="h-4 w-4 text-slate-700" />
            Staff Accounts &amp; RBAC
          </CardTitle>
          <CardDescription>
            {isSuperAdmin
              ? 'Manage tabulator, auditor, MC stage, and super-admin operator accounts.'
              : 'Staff account management is restricted to super_admin users.'}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {!isSuperAdmin ? (
            <p className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
              You are signed in without <code>super_admin</code> privileges.
              Contact a Super Admin to provision or deactivate staff accounts.
            </p>
          ) : (
            <div className="space-y-5">
              <form
                onSubmit={handleCreateStaff}
                className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-12"
              >
                {errors.root?.message && (
                  <p className="sm:col-span-12 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {errors.root.message}
                  </p>
                )}
                <div className="space-y-1 sm:col-span-3">
                  <label className="text-xs font-medium text-slate-600">
                    Name
                  </label>
                  <Input placeholder="Full Name" {...register('name')} />
                  {errors.name && (
                    <p className="text-xs text-red-600">{errors.name.message}</p>
                  )}
                </div>
                <div className="space-y-1 sm:col-span-3">
                  <label className="text-xs font-medium text-slate-600">
                    Email
                  </label>
                  <Input
                    type="email"
                    placeholder="staff@tabul8.local"
                    {...register('email')}
                  />
                  {errors.email && (
                    <p className="text-xs text-red-600">
                      {errors.email.message}
                    </p>
                  )}
                </div>
                <div className="space-y-1 sm:col-span-3">
                  <label className="text-xs font-medium text-slate-600">
                    Password
                  </label>
                  <Input
                    type="password"
                    placeholder="Min 8 chars"
                    {...register('password')}
                  />
                  {errors.password && (
                    <p className="text-xs text-red-600">
                      {errors.password.message}
                    </p>
                  )}
                </div>
                <div className="space-y-1 sm:col-span-3">
                  <label className="text-xs font-medium text-slate-600">
                    Role
                  </label>
                  <select
                    {...register('role')}
                    className="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900"
                  >
                    <option value="tabulator">tabulator</option>
                    <option value="mc_stage">mc_stage</option>
                    <option value="auditor">auditor</option>
                    <option value="super_admin">super_admin</option>
                  </select>
                </div>
                <div className="sm:col-span-12">
                  <Button
                    type="submit"
                    disabled={isBusy || isSubmitting}
                    className="gap-1.5"
                  >
                    <UserPlus className="h-4 w-4" />
                    Create Staff Account
                  </Button>
                </div>
              </form>

              <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase text-slate-500">
                      <th className="py-2 pr-3">Name</th>
                      <th className="py-2 pr-3">Email</th>
                      <th className="py-2 pr-3">Role</th>
                      <th className="py-2 pr-3">Status</th>
                      <th className="py-2 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {staffUsers.map((account) => (
                      <tr key={account.id}>
                        <td className="py-2 pr-3 font-medium text-slate-900">
                          {account.name}
                        </td>
                        <td className="py-2 pr-3 text-xs text-slate-600">
                          {account.email}
                        </td>
                        <td className="py-2 pr-3">
                          <select
                            value={account.role}
                            disabled={isBusy}
                            onChange={(e) =>
                              onUpdateStaffUser({
                                userId: account.id,
                                role: e.target.value as StaffUserDto['role'],
                              })
                            }
                            className="h-8 rounded border border-slate-300 bg-white px-2 text-xs text-slate-800"
                          >
                            <option value="super_admin">super_admin</option>
                            <option value="tabulator">tabulator</option>
                            <option value="mc_stage">mc_stage</option>
                            <option value="auditor">auditor</option>
                          </select>
                        </td>
                        <td className="py-2 pr-3">
                          <Badge
                            variant={account.is_active ? 'success' : 'secondary'}
                          >
                            {account.is_active ? 'Active' : 'Suspended'}
                          </Badge>
                        </td>
                        <td className="py-2 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={isBusy}
                              onClick={() =>
                                onUpdateStaffUser({
                                  userId: account.id,
                                  is_active: !account.is_active,
                                })
                              }
                            >
                              {account.is_active ? 'Suspend' : 'Activate'}
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="destructive"
                              disabled={isBusy}
                              onClick={() => onDeleteStaffUser(account.id)}
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
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
