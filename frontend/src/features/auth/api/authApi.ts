import { apiClient } from '@/lib/api-client';
import type { AuthUser } from '@/stores/authStore';
import type { ApiResponse, JudgeDto } from '@/types/api';

interface JudgeLoginResponse {
  token: string;
  actor_type: 'judge';
  judge: JudgeDto;
}

interface AdminLoginResponse {
  token: string;
  actor_type: 'user';
  user: AuthUser;
}

export async function loginWithJudgePin(payload: {
  event_id: number;
  pin: string;
  device_fingerprint?: string;
}): Promise<JudgeLoginResponse> {
  const { data } = await apiClient.post<ApiResponse<JudgeLoginResponse>>('/auth/judge-pin', payload);
  return data.data;
}

export async function loginAdmin(payload: {
  email: string;
  password: string;
}): Promise<AdminLoginResponse> {
  const { data } = await apiClient.post<ApiResponse<AdminLoginResponse>>('/auth/login', payload);
  return data.data;
}
