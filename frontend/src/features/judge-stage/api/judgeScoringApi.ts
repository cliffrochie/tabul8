import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect } from 'react';
import { toast } from 'sonner';
import { apiClient } from '@/lib/api-client';
import { getEcho } from '@/lib/echo';
import { useAuthStore } from '@/stores/authStore';
import { useOfflineQueueStore } from '@/stores/offlineQueueStore';
import { useStageStore } from '@/stores/stageStore';
import type {
  ApiResponse,
  EventStatePayload,
  JudgeScoreSheet,
  QueuedScoreMutation,
} from '@/types/api';

export const eventStateQueryKey = (eventId: number) => ['events', eventId, 'state'] as const;

export function useEventState(eventId: number | undefined) {
  const queryClient = useQueryClient();
  const judgeId = useAuthStore((s) => s.judge?.id);
  const { followStageLock, setSelectedContestantId } = useStageStore();

  const query = useQuery({
    queryKey: eventStateQueryKey(eventId ?? 0),
    queryFn: async () => {
      const { data } = await apiClient.get<ApiResponse<EventStatePayload>>(`/events/${eventId}/state`);
      return data.data;
    },
    enabled: Boolean(eventId),
  });

  // Subscribe to Reverb channels for live stage sync & unlock approvals
  useEffect(() => {
    if (!eventId) return;

    const echo = getEcho();
    const presenceChannel = echo.join(`event.${eventId}`);

    presenceChannel.listen('.StageStateUpdated', (payload: {
      on_stage_contestant_id: number | null;
      is_round_frozen: boolean;
      active_round_id: number | null;
    }) => {
      if (followStageLock && payload.on_stage_contestant_id) {
        setSelectedContestantId(payload.on_stage_contestant_id);
      }
      queryClient.invalidateQueries({ queryKey: eventStateQueryKey(eventId) });
    });

    if (judgeId) {
      const judgeChannel = echo.private(`judge.${judgeId}`);
      judgeChannel.listen('.UnlockRequestResolved', (payload: { decision: string }) => {
        toast.info(
          payload.decision === 'APPROVE'
            ? 'Unlock approved! You may now edit the score sheet.'
            : 'Unlock request was declined by Chief Tabulator.'
        );
        queryClient.invalidateQueries({ queryKey: eventStateQueryKey(eventId) });
      });
    }

    return () => {
      echo.leave(`event.${eventId}`);
      if (judgeId) {
        echo.leave(`judge.${judgeId}`);
      }
    };
  }, [eventId, judgeId, followStageLock, setSelectedContestantId, queryClient]);

  return query;
}

export function useOfflineAutoFlush(eventId: number | undefined, roundId: number | undefined) {
  const queryClient = useQueryClient();
  const { queue, isFlushing, setOnline, setFlushing, removeProcessed } = useOfflineQueueStore();

  const flushQueue = useCallback(async () => {
    if (!eventId || !roundId || queue.length === 0 || isFlushing) return;

    setFlushing(true);
    try {
      const { data } = await apiClient.post<
        ApiResponse<{
          results: Array<{ client_mutation_id: string; status: string }>;
          my_scores: Record<string, JudgeScoreSheet>;
        }>
      >('/scores/upsert', {
        round_id: roundId,
        mutations: queue,
      });

      const processedIds = data.data.results.map((r) => r.client_mutation_id);
      removeProcessed(processedIds);
      queryClient.setQueryData<EventStatePayload>(eventStateQueryKey(eventId), (prev) =>
        prev ? { ...prev, my_scores: data.data.my_scores } : prev
      );
      toast.success(`Synced ${processedIds.length} queued score packet(s).`);
    } catch {
      // Keep in IndexedDB outbox for next reconnect attempt
    } finally {
      setFlushing(false);
    }
  }, [eventId, roundId, queue, isFlushing, setFlushing, removeProcessed, queryClient]);

  useEffect(() => {
    const handleOnline = () => {
      setOnline(true);
      void flushQueue();
    };
    const handleOffline = () => setOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, [setOnline, flushQueue]);

  return { flushQueue };
}

export function useScoreMutations(eventId: number, roundId: number) {
  const queryClient = useQueryClient();
  const { isOnline, enqueueMutation } = useOfflineQueueStore();

  const applyOptimisticScore = (contestantId: number, criterionId: number, rawScore: number) => {
    queryClient.setQueryData<EventStatePayload>(eventStateQueryKey(eventId), (prev) => {
      if (!prev || !prev.my_scores || !prev.active_round?.criteria) return prev;
      const existing = prev.my_scores[String(contestantId)] ?? {
        status: 'DRAFT' as const,
        updated_at: new Date().toISOString(),
        weighted_total: 0,
        personal_rank: 1,
        is_tied: false,
        criteria: {},
      };
      const updatedCriteria = { ...existing.criteria, [String(criterionId)]: rawScore };
      const weightedTotal = prev.active_round.criteria.reduce((sum, crit) => {
        const val = updatedCriteria[String(crit.id)] ?? crit.min_score;
        return sum + val * (crit.weight_percentage / 100);
      }, 0);

      return {
        ...prev,
        my_scores: {
          ...prev.my_scores,
          [String(contestantId)]: {
            ...existing,
            criteria: updatedCriteria,
            weighted_total: Number(weightedTotal.toFixed(2)),
            updated_at: new Date().toISOString(),
          },
        },
      };
    });
  };

  const upsertScore = useMutation({
    mutationFn: async (params: { contestantId: number; criterionId: number; rawScore: number }) => {
      const mutation: QueuedScoreMutation = {
        client_mutation_id: `${params.contestantId}-${params.criterionId}-${Date.now()}`,
        round_id: roundId,
        contestant_id: params.contestantId,
        criterion_id: params.criterionId,
        raw_score: params.rawScore,
        action: 'UPSERT_SCORE',
        client_timestamp: new Date().toISOString(),
      };

      applyOptimisticScore(params.contestantId, params.criterionId, params.rawScore);

      if (!isOnline) {
        enqueueMutation(mutation);
        return null;
      }

      try {
        const { data } = await apiClient.post<
          ApiResponse<{ my_scores: Record<string, JudgeScoreSheet> }>
        >('/scores/upsert', {
          round_id: roundId,
          mutations: [mutation],
        });
        return data.data.my_scores;
      } catch (err) {
        if (!navigator.onLine) {
          enqueueMutation(mutation);
          return null;
        }
        throw err;
      }
    },
    onSuccess: (myScores) => {
      if (myScores) {
        queryClient.setQueryData<EventStatePayload>(eventStateQueryKey(eventId), (prev) =>
          prev ? { ...prev, my_scores: myScores } : prev
        );
      }
    },
  });

  const lockSheets = useMutation({
    mutationFn: async (params: { contestantIds?: number[]; lockAll?: boolean }) => {
      const { data } = await apiClient.post<
        ApiResponse<{ my_scores: Record<string, JudgeScoreSheet> }>
      >('/scores/lock', {
        round_id: roundId,
        contestant_ids: params.contestantIds,
        lock_all: params.lockAll ?? false,
      });
      return data.data.my_scores;
    },
    onSuccess: (myScores) => {
      queryClient.setQueryData<EventStatePayload>(eventStateQueryKey(eventId), (prev) =>
        prev ? { ...prev, my_scores: myScores } : prev
      );
      toast.success('Score sheet locked & submitted.');
    },
  });

  const requestUnlock = useMutation({
    mutationFn: async (params: { contestantId: number; reason?: string }) => {
      const { data } = await apiClient.post<
        ApiResponse<{ my_scores: Record<string, JudgeScoreSheet> }>
      >('/scores/request-unlock', {
        round_id: roundId,
        contestant_id: params.contestantId,
        reason: params.reason,
      });
      return data.data.my_scores;
    },
    onSuccess: (myScores) => {
      queryClient.setQueryData<EventStatePayload>(eventStateQueryKey(eventId), (prev) =>
        prev ? { ...prev, my_scores: myScores } : prev
      );
      toast.info('Unlock request sent to Chief Tabulator.');
    },
  });

  return { upsertScore, lockSheets, requestUnlock };
}
