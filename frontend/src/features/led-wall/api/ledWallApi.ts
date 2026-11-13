import { useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { getEcho } from '@/lib/echo';
import type {
  ApiResponse,
  ContestantDto,
  EventStatePayload,
  LedMode,
} from '@/types/api';

interface LedWallUpdatedEvent {
  led_mode: LedMode;
  on_stage_contestant: ContestantDto | null;
  revealed_contestant_ids: number[];
  finalists_public_cards: ContestantDto[];
}

export function ledWallQueryKey(eventId: number) {
  return ['led-wall-state', eventId] as const;
}

export function useLedWallState(eventId: number) {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ledWallQueryKey(eventId),
    queryFn: async () => {
      const { data } = await apiClient.get<ApiResponse<EventStatePayload>>(
        `/display/events/${eventId}/state`,
      );
      return data.data;
    },
    enabled: eventId > 0,
    refetchInterval: 30_000,
  });

  useEffect(() => {
    if (!eventId) return;
    const echo = getEcho();
    if (!echo) return;

    const channel = echo.channel(`public-led.${eventId}`);
    channel.listen('.LedWallUpdated', (payload: LedWallUpdatedEvent) => {
      queryClient.setQueryData<EventStatePayload>(
        ledWallQueryKey(eventId),
        (old) => {
          if (!old) return old;
          return {
            ...old,
            event: {
              ...old.event,
              led_mode: payload.led_mode,
              on_stage_contestant_id: payload.on_stage_contestant?.id ?? null,
              revealed_contestant_ids: payload.revealed_contestant_ids,
            },
            revealed_finalists: payload.finalists_public_cards,
          };
        },
      );
    });

    return () => {
      echo.leave(`public-led.${eventId}`);
    };
  }, [eventId, queryClient]);

  return query;
}
