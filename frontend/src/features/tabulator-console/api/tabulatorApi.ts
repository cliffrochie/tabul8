import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { apiClient } from '@/lib/api-client';
import { getEcho } from '@/lib/echo';
import type {
  ApiResponse,
  EventStatePayload,
  LedMode,
  RoundResultDto,
  ScoreSheetStatus,
  ScoringStrategy,
} from '@/types/api';

interface PresenceMember {
  id: number;
  type: 'judge' | 'user';
  name: string;
}

interface JudgeScoreUpdatedEvent {
  round_id: number;
  judge_id: number;
  contestant_id: number;
  weighted_total: number;
  status: ScoreSheetStatus;
  updated_at: string;
  leaderboard: RoundResultDto[];
}

export interface ResolveUnlockPayload {
  round_id: number;
  contestant_id: number;
  judge_id: number;
  decision: 'APPROVE' | 'REJECT';
  reason?: string;
}

export interface ApplyDeductionPayload {
  round_id: number;
  contestant_id: number;
  points_deducted: number;
  reason?: string;
}

export interface StageSyncPayload {
  event_id: number;
  active_round_id?: number;
  scoring_strategy?: ScoringStrategy;
  on_stage_contestant_id?: number | null;
  is_round_frozen?: boolean;
  led_mode?: LedMode;
  revealed_contestant_ids?: number[];
}

export function adminEventQueryKey(eventId: number) {
  return ['admin-event-state', eventId] as const;
}

export function useAdminEventState(eventId: number) {
  const queryClient = useQueryClient();
  const [onlineJudgeIds, setOnlineJudgeIds] = useState<number[]>([]);

  const query = useQuery({
    queryKey: adminEventQueryKey(eventId),
    queryFn: async () => {
      const { data } = await apiClient.get<ApiResponse<EventStatePayload>>(
        `/events/${eventId}/state`,
      );
      return data.data;
    },
    enabled: eventId > 0,
    refetchOnWindowFocus: true,
  });

  useEffect(() => {
    if (!eventId) return;
    const echo = getEcho();
    if (!echo) return;

    const presenceChannel = echo.join(`event.${eventId}`);
    presenceChannel
      .here((members: PresenceMember[]) => {
        const ids = members
          .filter((m) => m.type === 'judge')
          .map((m) => m.id);
        setOnlineJudgeIds(Array.from(new Set(ids)));
      })
      .joining((member: PresenceMember) => {
        if (member.type !== 'judge') return;
        setOnlineJudgeIds((prev) =>
          prev.includes(member.id) ? prev : [...prev, member.id],
        );
      })
      .leaving((member: PresenceMember) => {
        if (member.type !== 'judge') return;
        setOnlineJudgeIds((prev) => prev.filter((id) => id !== member.id));
      })
      .listen('.StageStateUpdated', () => {
        void queryClient.invalidateQueries({
          queryKey: adminEventQueryKey(eventId),
        });
      });

    const tabulatorChannel = echo.private(`tabulator.${eventId}`);
    tabulatorChannel.listen(
      '.JudgeScoreUpdated',
      (payload: JudgeScoreUpdatedEvent) => {
        queryClient.setQueryData<EventStatePayload>(
          adminEventQueryKey(eventId),
          (old) => {
            if (!old) return old;
            if (old.active_round?.id !== payload.round_id) {
              return old;
            }
            const nextMatrix = { ...(old.scores_matrix ?? {}) };
            const contestantKey = String(payload.contestant_id);
            const judgeKey = String(payload.judge_id);
            nextMatrix[contestantKey] = {
              ...(nextMatrix[contestantKey] ?? {}),
              [judgeKey]: {
                status: payload.status,
                weighted_total: payload.weighted_total,
                updated_at: payload.updated_at,
              },
            };
            return {
              ...old,
              scores_matrix: nextMatrix,
              leaderboard: payload.leaderboard,
            };
          },
        );
      },
    );

    return () => {
      echo.leave(`event.${eventId}`);
      echo.leave(`tabulator.${eventId}`);
    };
  }, [eventId, queryClient]);

  return {
    ...query,
    onlineJudgeIds,
  };
}

export function useAdminMutations(eventId: number, roundId?: number) {
  const queryClient = useQueryClient();

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: adminEventQueryKey(eventId) });

  const resolveUnlock = useMutation({
    mutationFn: async (payload: ResolveUnlockPayload) => {
      const { data } = await apiClient.post<ApiResponse<unknown>>(
        '/admin/scores/resolve-unlock',
        payload,
      );
      return data;
    },
    onSuccess: (_data, variables) => {
      toast.success(
        variables.decision === 'APPROVE'
          ? 'Score sheet unlocked for judge'
          : 'Unlock request rejected',
      );
      void invalidate();
    },
    onError: (err: unknown) => {
      const message =
        err instanceof Error ? err.message : 'Failed to resolve unlock request';
      toast.error(message);
    },
  });

  const applyDeduction = useMutation({
    mutationFn: async (payload: ApplyDeductionPayload) => {
      const { data } = await apiClient.post<
        ApiResponse<{ leaderboard: RoundResultDto[] }>
      >('/admin/deductions', payload);
      return data.data;
    },
    onSuccess: (data) => {
      queryClient.setQueryData<EventStatePayload>(
        adminEventQueryKey(eventId),
        (old) => (old ? { ...old, leaderboard: data.leaderboard } : old),
      );
      toast.success('Deduction applied');
    },
    onError: () => {
      toast.error('Failed to update deduction');
    },
  });

  const syncStage = useMutation({
    mutationFn: async (payload: StageSyncPayload) => {
      const { data } = await apiClient.post<ApiResponse<EventStatePayload>>(
        '/admin/stage-sync',
        payload,
      );
      return data.data;
    },
    onSuccess: () => {
      toast.success('Stage state synchronized');
      void invalidate();
    },
    onError: () => {
      toast.error('Failed to sync stage state');
    },
  });

  const finalizeRound = useMutation({
    mutationFn: async (targetRoundId?: number) => {
      const id = targetRoundId ?? roundId;
      if (!id) throw new Error('No active round');
      const { data } = await apiClient.post<ApiResponse<unknown>>(
        `/admin/rounds/${id}/finalize`,
      );
      return data;
    },
    onSuccess: () => {
      toast.success('Round finalized and qualifiers locked');
      void invalidate();
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Cannot finalize round until all judges lock';
      toast.error(msg);
    },
  });

  return {
    resolveUnlock,
    applyDeduction,
    syncStage,
    finalizeRound,
  };
}

export async function downloadTallyPdf(roundId: number): Promise<void> {
  const response = await apiClient.get<Blob>(
    `/admin/rounds/${roundId}/tally-pdf`,
    { responseType: 'blob' },
  );
  const url = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = url;
  link.download = `official-tally-round-${roundId}.pdf`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
