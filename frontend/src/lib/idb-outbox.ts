import { get, set } from 'idb-keyval';
import type { StateStorage } from 'zustand/middleware';

const memoryFallback = new Map<string, string>();

export const idbStorage: StateStorage = {
  getItem: async (name: string): Promise<string | null> => {
    if (typeof indexedDB === 'undefined') {
      return memoryFallback.get(name) ?? null;
    }
    const value = await get<string>(name);
    return value ?? null;
  },
  setItem: async (name: string, value: string): Promise<void> => {
    if (typeof indexedDB === 'undefined') {
      memoryFallback.set(name, value);
      return;
    }
    await set(name, value);
  },
  removeItem: async (name: string): Promise<void> => {
    if (typeof indexedDB === 'undefined') {
      memoryFallback.delete(name);
      return;
    }
    await set(name, null);
  },
};
