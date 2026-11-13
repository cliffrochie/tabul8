import { z } from 'zod';

export const judgePinSchema = z.object({
  event_id: z.number().int().positive('Event ID is required'),
  pin: z.string().regex(/^\d{6}$/, 'Enter a valid 6-digit Judge PIN'),
});

export type JudgePinFormValues = z.infer<typeof judgePinSchema>;
