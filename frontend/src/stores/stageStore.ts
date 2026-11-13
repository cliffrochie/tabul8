import { create } from 'zustand';

interface StageState {
  selectedContestantId: number | null;
  followStageLock: boolean;
  setSelectedContestantId: (id: number | null) => void;
  setFollowStageLock: (follow: boolean) => void;
}

export const useStageStore = create<StageState>((set) => ({
  selectedContestantId: null,
  followStageLock: true,
  setSelectedContestantId: (id) => set({ selectedContestantId: id }),
  setFollowStageLock: (follow) => set({ followStageLock: follow }),
}));
