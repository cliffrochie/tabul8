import { zodResolver } from '@hookform/resolvers/zod';
import axios from 'axios';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAuthStore } from '@/stores/authStore';
import { loginWithJudgePin } from '../api/authApi';
import { judgePinSchema, type JudgePinFormValues } from '../schemas/judgePinSchema';

export function JudgePinForm() {
  const navigate = useNavigate();
  const setJudgeAuth = useAuthStore((s) => s.setJudgeAuth);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<JudgePinFormValues>({
    resolver: zodResolver(judgePinSchema),
    defaultValues: { event_id: 1, pin: '' },
  });

  const onSubmit = async (values: JudgePinFormValues) => {
    try {
      const res = await loginWithJudgePin({
        ...values,
        device_fingerprint: navigator.userAgent.slice(0, 120),
      });
      setJudgeAuth(res.token, res.judge);
      toast.success(`Welcome, ${res.judge.name}`);
      navigate('/judge/stage');
    } catch (err) {
      if (axios.isAxiosError(err) && err.response?.status === 422) {
        const serverErrors = err.response.data?.errors as Record<string, string[]> | undefined;
        if (serverErrors?.pin?.[0]) {
          setError('pin', { message: serverErrors.pin[0] });
        } else {
          setError('root', { message: err.response.data?.message ?? 'Invalid PIN' });
        }
      }
    }
  };

  return (
    <Card className="w-full max-w-md shadow-sm">
      <CardHeader>
        <div className="flex items-center gap-3">
          <div className="h-10 w-10 rounded-xl bg-slate-900 text-white font-bold text-sm flex items-center justify-center">
            T8
          </div>
          <div>
            <h1 className="text-lg font-semibold text-slate-900">Judge Terminal Sign-In</h1>
            <p className="text-xs text-slate-500">Enter your assigned 6-digit Adjudicator PIN</p>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-700 mb-1">Event ID</label>
            <Input type="number" min={1} {...register('event_id', { valueAsNumber: true })} />
            {errors.event_id && (
              <p className="mt-1 text-xs text-rose-600">{errors.event_id.message}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-700 mb-1">6-Digit Judge PIN</label>
            <Input
              type="password"
              inputMode="numeric"
              maxLength={6}
              placeholder="••••••"
              className="text-center tracking-[0.4em] font-semibold text-base tabular-nums"
              {...register('pin')}
            />
            {errors.pin && <p className="mt-1 text-xs text-rose-600">{errors.pin.message}</p>}
          </div>

          {errors.root && (
            <div className="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700">
              {errors.root.message}
            </div>
          )}

          <Button type="submit" className="w-full" size="lg" disabled={isSubmitting}>
            {isSubmitting ? 'Authenticating...' : 'Enter Adjudication Terminal'}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
