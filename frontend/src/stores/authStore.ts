import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { JudgeDto } from '@/types/api';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: 'super_admin' | 'tabulator' | 'auditor' | 'mc_stage';
}

interface AuthState {
  token: string | null;
  actorType: 'judge' | 'user' | null;
  judge: JudgeDto | null;
  user: AuthUser | null;
  isAuthenticated: boolean;
  hasHydrated: boolean;
  setJudgeAuth: (token: string, judge: JudgeDto) => void;
  setUserAuth: (token: string, user: AuthUser) => void;
  clearAuth: () => void;
  setHasHydrated: (value: boolean) => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      actorType: null,
      judge: null,
      user: null,
      isAuthenticated: false,
      hasHydrated: false,
      setJudgeAuth: (token, judge) =>
        set({
          token,
          actorType: 'judge',
          judge,
          user: null,
          isAuthenticated: true,
        }),
      setUserAuth: (token, user) =>
        set({
          token,
          actorType: 'user',
          judge: null,
          user,
          isAuthenticated: true,
        }),
      clearAuth: () =>
        set({
          token: null,
          actorType: null,
          judge: null,
          user: null,
          isAuthenticated: false,
        }),
      setHasHydrated: (value) => set({ hasHydrated: value }),
    }),
    {
      name: 'tabul8-auth-storage',
      onRehydrateStorage: () => (state) => {
        state?.setHasHydrated(true);
      },
    }
  )
);
