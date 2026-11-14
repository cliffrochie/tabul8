import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { apiClient } from '@/lib/api-client';
import type {
  ApiResponse,
  ContestantDto,
  CriterionDto,
  CutoffMode,
  EventDto,
  JudgeDto,
  PaginatedResponse,
  RoundDto,
  ScoringStrategy,
} from '@/types/api';

export interface PreflightCheck {
  key: string;
  ok: boolean;
  message: string;
}

export interface PreflightStatus {
  ready: boolean;
  checks: PreflightCheck[];
}

export interface StaffUserDto {
  id: number;
  name: string;
  email: string;
  role: 'super_admin' | 'tabulator' | 'auditor' | 'mc_stage';
  is_active: boolean;
  last_login_at: string | null;
}

export function adminEventsListKey() {
  return ['admin-events-list'] as const;
}

export function eventPreflightKey(eventId: number) {
  return ['admin-event-preflight', eventId] as const;
}

export function staffUsersKey() {
  return ['admin-staff-users'] as const;
}

export function useAdminEventsList() {
  return useQuery({
    queryKey: adminEventsListKey(),
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<EventDto>>(
        '/admin/events?per_page=50',
      );
      return data;
    },
  });
}

export function useEventPreflight(eventId: number | null) {
  return useQuery({
    queryKey: eventPreflightKey(eventId ?? 0),
    enabled: typeof eventId === 'number' && eventId > 0,
    queryFn: async () => {
      const { data } = await apiClient.get<ApiResponse<PreflightStatus>>(
        `/admin/events/${eventId}/preflight`,
      );
      return data.data;
    },
  });
}

export function useStaffUsers(enabled: boolean) {
  return useQuery({
    queryKey: staffUsersKey(),
    enabled,
    queryFn: async () => {
      const { data } = await apiClient.get<
        ApiResponse<{ users: StaffUserDto[] }>
      >('/admin/users');
      return data.data.users;
    },
  });
}

export function useSetupMutations(eventId: number) {
  const queryClient = useQueryClient();

  const invalidateAll = () => {
    void queryClient.invalidateQueries({ queryKey: adminEventsListKey() });
    void queryClient.invalidateQueries({
      queryKey: ['admin-event-state', eventId],
    });
    void queryClient.invalidateQueries({
      queryKey: eventPreflightKey(eventId),
    });
  };

  const createEvent = useMutation({
    mutationFn: async (payload: {
      title: string;
      subtitle?: string;
      event_type: EventDto['event_type'];
      preset?: EventDto['event_type'];
      clone_from_event_id?: number;
    }) => {
      const { data } = await apiClient.post<ApiResponse<{ event: EventDto }>>(
        '/admin/events',
        payload,
      );
      return data.data.event;
    },
    onSuccess: (created) => {
      toast.success(`Event "${created.title}" created`);
      void queryClient.invalidateQueries({ queryKey: adminEventsListKey() });
    },
  });

  const updateEvent = useMutation({
    mutationFn: async (payload: {
      targetEventId?: number;
      title?: string;
      subtitle?: string | null;
      status?: EventDto['status'];
      settings?: Record<string, unknown>;
    }) => {
      const id = payload.targetEventId ?? eventId;
      const body: Record<string, unknown> = { ...payload };
      delete body.targetEventId;
      const { data } = await apiClient.put<ApiResponse<{ event: EventDto }>>(
        `/admin/events/${id}`,
        body,
      );
      return data.data.event;
    },
    onSuccess: (updated) => {
      toast.success(
        updated.status === 'live' ? 'Event is now LIVE' : 'Event updated',
      );
      invalidateAll();
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Failed to update event';
      toast.error(msg);
    },
  });

  const deleteEvent = useMutation({
    mutationFn: async (targetEventId: number) => {
      await apiClient.delete(`/admin/events/${targetEventId}`);
    },
    onSuccess: () => {
      toast.success('Setup event deleted');
      void queryClient.invalidateQueries({ queryKey: adminEventsListKey() });
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Only setup events can be deleted';
      toast.error(msg);
    },
  });

  const generateFunnel = useMutation({
    mutationFn: async (payload: {
      round_count: number;
      final_winners_count: number;
      final_back_to_zero: boolean;
    }) => {
      const { data } = await apiClient.post<ApiResponse<{ rounds: RoundDto[] }>>(
        `/admin/events/${eventId}/rounds/generate-funnel`,
        payload,
      );
      return data.data.rounds;
    },
    onSuccess: () => {
      toast.success('Multi-round elimination funnel generated');
      invalidateAll();
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Failed to generate funnel';
      toast.error(msg);
    },
  });

  const createRound = useMutation({
    mutationFn: async (payload: {
      name: string;
      scoring_strategy: ScoringStrategy;
      weight_in_overall: number;
      is_back_to_zero: boolean;
      cutoff_mode: CutoffMode;
      advancement_count: number;
      eliminate_count?: number;
    }) => {
      const { data } = await apiClient.post<ApiResponse<{ round: RoundDto }>>(
        `/admin/events/${eventId}/rounds`,
        payload,
      );
      return data.data.round;
    },
    onSuccess: () => {
      toast.success('Round added');
      invalidateAll();
    },
  });

  const updateRound = useMutation({
    mutationFn: async (payload: {
      roundId: number;
      name?: string;
      scoring_strategy?: ScoringStrategy;
      weight_in_overall?: number;
      is_back_to_zero?: boolean;
      cutoff_mode?: CutoffMode;
      advancement_count?: number;
      eliminate_count?: number;
    }) => {
      const { roundId, ...body } = payload;
      const { data } = await apiClient.put<ApiResponse<{ round: RoundDto }>>(
        `/admin/rounds/${roundId}`,
        body,
      );
      return data.data.round;
    },
    onSuccess: () => {
      toast.success('Round updated');
      invalidateAll();
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Failed to update round';
      toast.error(msg);
    },
  });

  const deleteRound = useMutation({
    mutationFn: async (roundId: number) => {
      await apiClient.delete(`/admin/rounds/${roundId}`);
    },
    onSuccess: () => {
      toast.success('Round deleted');
      invalidateAll();
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Cannot delete active or locked round';
      toast.error(msg);
    },
  });

  const reorderRounds = useMutation({
    mutationFn: async (roundIds: number[]) => {
      const { data } = await apiClient.put<ApiResponse<{ rounds: RoundDto[] }>>(
        `/admin/events/${eventId}/rounds/reorder`,
        { round_ids: roundIds },
      );
      return data.data.rounds;
    },
    onSuccess: () => {
      toast.success('Rounds reordered');
      invalidateAll();
    },
  });

  const createCriterion = useMutation({
    mutationFn: async (payload: {
      roundId: number;
      parent_id?: number | null;
      name: string;
      description?: string;
      weight_percentage: number;
      min_score: number;
      max_score: number;
      score_step: number;
    }) => {
      const { roundId, ...body } = payload;
      const { data } = await apiClient.post<
        ApiResponse<{ criterion: CriterionDto; leaf_weight_sum: number }>
      >(`/admin/rounds/${roundId}/criteria`, body);
      return data.data;
    },
    onSuccess: () => {
      toast.success('Criterion added');
      invalidateAll();
    },
  });

  const updateCriterion = useMutation({
    mutationFn: async (payload: {
      criterionId: number;
      name?: string;
      weight_percentage?: number;
      min_score?: number;
      max_score?: number;
      score_step?: number;
    }) => {
      const { criterionId, ...body } = payload;
      const { data } = await apiClient.put<
        ApiResponse<{ criterion: CriterionDto; leaf_weight_sum: number }>
      >(`/admin/criteria/${criterionId}`, body);
      return data.data;
    },
    onSuccess: () => {
      invalidateAll();
    },
  });

  const deleteCriterion = useMutation({
    mutationFn: async (criterionId: number) => {
      await apiClient.delete(`/admin/criteria/${criterionId}`);
    },
    onSuccess: () => {
      toast.success('Criterion removed');
      invalidateAll();
    },
  });

  const createContestant = useMutation({
    mutationFn: async (payload: {
      candidate_number?: number;
      name: string;
      origin_or_group: string;
      category?: string;
      performance_meta?: string;
    }) => {
      const { data } = await apiClient.post<
        ApiResponse<{ contestant: ContestantDto }>
      >(`/admin/events/${eventId}/contestants`, payload);
      return data.data.contestant;
    },
    onSuccess: () => {
      toast.success('Contestant added');
      invalidateAll();
    },
  });

  const updateContestant = useMutation({
    mutationFn: async (payload: {
      contestantId: number;
      name?: string;
      origin_or_group?: string;
      status?: ContestantDto['status'];
    }) => {
      const { contestantId, ...body } = payload;
      const { data } = await apiClient.put<
        ApiResponse<{ contestant: ContestantDto }>
      >(`/admin/contestants/${contestantId}`, body);
      return data.data.contestant;
    },
    onSuccess: () => {
      toast.success('Contestant updated');
      invalidateAll();
    },
  });

  const deleteContestant = useMutation({
    mutationFn: async (contestantId: number) => {
      await apiClient.delete(`/admin/contestants/${contestantId}`);
    },
    onSuccess: () => {
      toast.success('Contestant removed');
      invalidateAll();
    },
  });

  const importContestantsCsv = useMutation({
    mutationFn: async (csvText: string) => {
      const { data } = await apiClient.post<
        ApiResponse<{ imported_count: number }>
      >(`/admin/events/${eventId}/contestants/import-csv`, {
        csv_text: csvText,
      });
      return data.data;
    },
    onSuccess: (res) => {
      toast.success(`Imported ${res.imported_count} contestant(s)`);
      invalidateAll();
    },
  });

  const renumberContestants = useMutation({
    mutationFn: async () => {
      await apiClient.post(`/admin/events/${eventId}/contestants/renumber`);
    },
    onSuccess: () => {
      toast.success('Contestants auto-renumbered');
      invalidateAll();
    },
  });

  const uploadContestantPhoto = useMutation({
    mutationFn: async ({
      contestantId,
      file,
    }: {
      contestantId: number;
      file: File;
    }) => {
      const formData = new FormData();
      formData.append('photo', file);
      const { data } = await apiClient.post<
        ApiResponse<{ contestant: ContestantDto }>
      >(`/admin/contestants/${contestantId}/photo`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return data.data.contestant;
    },
    onSuccess: () => {
      toast.success('Portrait uploaded');
      invalidateAll();
    },
  });

  const createJudge = useMutation({
    mutationFn: async (payload: {
      judge_number?: number;
      name: string;
      role_title: string;
      is_chairman: boolean;
    }) => {
      const { data } = await apiClient.post<
        ApiResponse<{ judge: JudgeDto; plain_pin: string }>
      >(`/admin/events/${eventId}/judges`, payload);
      return data.data;
    },
    onSuccess: (res) => {
      toast.success(`Judge ${res.judge.name} added · PIN: ${res.plain_pin}`);
      invalidateAll();
    },
  });

  const updateJudge = useMutation({
    mutationFn: async (payload: {
      judgeId: number;
      is_chairman?: boolean;
      is_active?: boolean;
      name?: string;
      role_title?: string;
    }) => {
      const { judgeId, ...body } = payload;
      const { data } = await apiClient.put<ApiResponse<{ judge: JudgeDto }>>(
        `/admin/judges/${judgeId}`,
        body,
      );
      return data.data.judge;
    },
    onSuccess: () => {
      toast.success('Judge updated');
      invalidateAll();
    },
  });

  const deleteJudge = useMutation({
    mutationFn: async (judgeId: number) => {
      await apiClient.delete(`/admin/judges/${judgeId}`);
    },
    onSuccess: () => {
      toast.success('Judge removed');
      invalidateAll();
    },
  });

  const regenerateJudgePin = useMutation({
    mutationFn: async (judgeId: number) => {
      const { data } = await apiClient.post<
        ApiResponse<{ judge: JudgeDto; plain_pin: string }>
      >(`/admin/judges/${judgeId}/regenerate-pin`);
      return data.data;
    },
    onSuccess: (res) => {
      toast.success(`New PIN for ${res.judge.name}: ${res.plain_pin}`);
      invalidateAll();
    },
  });

  const createStaffUser = useMutation({
    mutationFn: async (payload: {
      name: string;
      email: string;
      password: string;
      role: StaffUserDto['role'];
    }) => {
      const { data } = await apiClient.post<
        ApiResponse<{ user: StaffUserDto }>
      >('/admin/users', payload);
      return data.data.user;
    },
    onSuccess: () => {
      toast.success('Staff account created');
      void queryClient.invalidateQueries({ queryKey: staffUsersKey() });
    },
  });

  const updateStaffUser = useMutation({
    mutationFn: async (payload: {
      userId: number;
      is_active?: boolean;
      role?: StaffUserDto['role'];
    }) => {
      const { userId, ...body } = payload;
      await apiClient.put(`/admin/users/${userId}`, body);
    },
    onSuccess: () => {
      toast.success('Staff account updated');
      void queryClient.invalidateQueries({ queryKey: staffUsersKey() });
    },
  });

  const deleteStaffUser = useMutation({
    mutationFn: async (userId: number) => {
      await apiClient.delete(`/admin/users/${userId}`);
    },
    onSuccess: () => {
      toast.success('Staff account deleted');
      void queryClient.invalidateQueries({ queryKey: staffUsersKey() });
    },
  });

  return {
    createEvent,
    updateEvent,
    deleteEvent,
    generateFunnel,
    createRound,
    updateRound,
    deleteRound,
    reorderRounds,
    createCriterion,
    updateCriterion,
    deleteCriterion,
    createContestant,
    updateContestant,
    deleteContestant,
    importContestantsCsv,
    renumberContestants,
    uploadContestantPhoto,
    createJudge,
    updateJudge,
    deleteJudge,
    regenerateJudgePin,
    createStaffUser,
    updateStaffUser,
    deleteStaffUser,
  };
}

export async function downloadJudgeCardsPdf(eventId: number): Promise<void> {
  const response = await apiClient.get<Blob>(
    `/admin/events/${eventId}/judge-cards-pdf`,
    { responseType: 'blob' },
  );
  const url = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = url;
  link.download = `judge-tent-cards-event-${eventId}.pdf`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
