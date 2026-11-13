import axios from 'axios';
import { toast } from 'sonner';
import { env } from '@/config/env';
import { useAuthStore } from '@/stores/authStore';

export const apiClient = axios.create({
  baseURL: env.apiUrl,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 8000,
});

apiClient.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const url: string = error.config?.url ?? '';

    if (status === 401 && !url.includes('/auth/')) {
      useAuthStore.getState().clearAuth();
      toast.error('Session expired. Please sign in again.');
      window.location.href = '/login/judge';
    } else if (status === 409) {
      toast.warning(error.response?.data?.message ?? 'Action blocked due to round or sheet lock.');
    } else if (status >= 500) {
      toast.error('Server error occurred. Changes queued locally if offline.');
    }

    return Promise.reject(error);
  }
);
