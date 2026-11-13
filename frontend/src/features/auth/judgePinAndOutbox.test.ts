import { beforeEach, describe, expect, it } from 'vitest';
import { judgePinSchema } from '@/features/auth';
import { useOfflineQueueStore } from '@/stores/offlineQueueStore';

describe('judgePinSchema', () => {
  it('accepts a valid 6-digit PIN and positive event_id', () => {
    const res = judgePinSchema.safeParse({ event_id: 1, pin: '104928' });
    expect(res.success).toBe(true);
  });

  it('rejects non-6-digit PINs', () => {
    const res = judgePinSchema.safeParse({ event_id: 1, pin: '123' });
    expect(res.success).toBe(false);
  });
});

describe('offlineQueueStore compaction', () => {
  beforeEach(() => {
    useOfflineQueueStore.getState().clearQueue();
  });

  it('replaces older pending UPSERT_SCORE for the same contestant and criterion', () => {
    const store = useOfflineQueueStore.getState();

    store.enqueueMutation({
      client_mutation_id: 'm1',
      round_id: 1,
      contestant_id: 3,
      criterion_id: 101,
      raw_score: 88.5,
      action: 'UPSERT_SCORE',
      client_timestamp: '2026-11-13T09:30:00Z',
    });

    store.enqueueMutation({
      client_mutation_id: 'm2',
      round_id: 1,
      contestant_id: 3,
      criterion_id: 101,
      raw_score: 94.0,
      action: 'UPSERT_SCORE',
      client_timestamp: '2026-11-13T09:30:02Z',
    });

    const queue = useOfflineQueueStore.getState().queue;
    expect(queue).toHaveLength(1);
    expect(queue[0].client_mutation_id).toBe('m2');
    expect(queue[0].raw_score).toBe(94.0);
  });
});
