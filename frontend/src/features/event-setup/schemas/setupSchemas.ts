import axios from 'axios';
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { z } from 'zod';

export const createEventSchema = z.object({
  title: z.string().trim().min(2, 'Event title is required').max(180),
  subtitle: z.string().max(255).optional(),
  event_type: z.enum(['pageant', 'singing', 'dance', 'custom']),
  preset: z.enum(['pageant', 'singing', 'dance', 'custom']).optional(),
});
export type CreateEventValues = z.infer<typeof createEventSchema>;

export const createCriterionSchema = z
  .object({
    parent_id: z.number().int().positive().nullable().optional(),
    name: z.string().trim().min(2, 'Criterion name is required').max(150),
    description: z.string().max(255).optional(),
    weight_percentage: z.number().min(0).max(100),
    min_score: z.number().min(0).max(999),
    max_score: z.number().gt(0).max(1000),
    score_step: z.number().gt(0).max(100),
  })
  .refine((v) => v.min_score < v.max_score, {
    message: 'Min score must be strictly less than max score',
    path: ['min_score'],
  });
export type CreateCriterionValues = z.infer<typeof createCriterionSchema>;

export const createContestantSchema = z.object({
  candidate_number: z.number().int().min(1).optional(),
  name: z.string().trim().min(2, 'Candidate name is required').max(150),
  origin_or_group: z.string().trim().min(1, 'Origin or group is required').max(150),
  category: z.string().trim().min(1).max(80),
  performance_meta: z.string().max(255).optional(),
});
export type CreateContestantValues = z.infer<typeof createContestantSchema>;

export const createJudgeSchema = z.object({
  judge_number: z.number().int().min(1).optional(),
  name: z.string().trim().min(2, 'Judge name is required').max(150),
  role_title: z.string().trim().min(1).max(150),
  is_chairman: z.boolean(),
});
export type CreateJudgeValues = z.infer<typeof createJudgeSchema>;

export const createStaffUserSchema = z.object({
  name: z.string().trim().min(2, 'Full name is required').max(120),
  email: z.string().trim().email('Enter a valid email address').max(150),
  password: z.string().min(8, 'Password must be at least 8 characters').max(100),
  role: z.enum(['super_admin', 'tabulator', 'auditor', 'mc_stage']),
});
export type CreateStaffUserValues = z.infer<typeof createStaffUserSchema>;

export function applyServerValidationErrors<T extends FieldValues>(
  err: unknown,
  setError: UseFormSetError<T>,
): boolean {
  if (!axios.isAxiosError(err) || err.response?.status !== 422) {
    return false;
  }

  const errors = err.response.data?.errors as
    | Record<string, string[]>
    | undefined;
  if (!errors) {
    setError('root', {
      message: err.response.data?.message ?? 'Validation failed.',
    });
    return true;
  }

  let mapped = false;
  for (const [key, messages] of Object.entries(errors)) {
    if (messages?.[0]) {
      setError(key as Path<T>, { message: messages[0] });
      mapped = true;
    }
  }

  if (!mapped) {
    setError('root', {
      message: err.response.data?.message ?? 'Validation failed.',
    });
  }

  return true;
}
