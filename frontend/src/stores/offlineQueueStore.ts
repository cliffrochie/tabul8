import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import { idbStorage } from '@/lib/idb-outbox';
import type { QueuedScoreMutation } from '@/types/api';

interface OfflineQueueState {
  queue: QueuedScoreMutation[];
  isOnline: boolean;
  isFlushing: boolean;
  setOnline: (online: boolean) => void;
  setFlushing: (flushing: boolean) => void;
  enqueueMutation: (mutation: QueuedScoreMutation) => void;
  removeProcessed: (mutationIds: string[]) => void;
  clearQueue: () => void;
}

export const useOfflineQueueStore = create<OfflineQueueState>()(
  persist(
    (set) => ({
      queue: [],
      isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
      isFlushing: false,
      setOnline: (online) => set({ isOnline: online }),
      setFlushing: (flushing) => set({ isFlushing: flushing }),
      enqueueMutation: (mutation) =>
        set((state) => {
          if (mutation.action === 'UPSERT_SCORE') {
            const filtered = state.queue.filter(
              (item) =>
                !(
                  item.action === 'UPSERT_SCORE' &&
                  item.round_id === mutation.round_id &&
                  item.contestant_id === mutation.contestant_id &&
                  item.criterion_id === mutation.criterion_id
                )
            );
            return { queue: [...filtered, mutation] };
          }
          return { queue: [...state.queue, mutation] };
        }),
      removeProcessed: (mutationIds) =>
        set((state) => ({
          queue: state.queue.filter((item) => !mutationIds.includes(item.client_mutation_id)),
        })),
      clearQueue: () => set({ queue: [] }),
    }),
    {
      name: 'tabul8-offline-outbox',
      storage: createJSONStorage(() => idbStorage),
      partialize: (state) => ({ queue: state.queue }),
    }
  )
);
