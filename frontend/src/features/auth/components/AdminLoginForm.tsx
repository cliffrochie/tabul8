import { zodResolver } from '@hookform/resolvers/zod';
import axios from 'axios';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAuthStore } from '@/stores/authStore';
import { loginAdmin } from '../api/authApi';
import { adminLoginSchema, type AdminLoginFormValues } from '../schemas/adminLoginSchema';

export function AdminLoginForm() {
  const navigate = useNavigate();
  const setUserAuth = useAuthStore((s) => s.setUserAuth);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<AdminLoginFormValues>({
    resolver: zodResolver(adminLoginSchema),
    defaultValues: { email: '', password: '' },
  });

  const onSubmit = async (values: AdminLoginFormValues) => {
    try {
      const res = await loginAdmin(values);
      setUserAuth(res.token, res.user);
      toast.success(`Signed in as ${res.user.name}`);
      navigate('/console');
    } catch (err) {
      if (axios.isAxiosError(err) && err.response?.status === 422) {
        const serverErrors = err.response.data?.errors as Record<string, string[]> | undefined;
        if (serverErrors?.email?.[0]) {
          setError('email', { message: serverErrors.email[0] });
        } else {
          setError('root', { message: err.response.data?.message ?? 'Invalid credentials' });
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
            <h1 className="text-lg font-semibold text-slate-900">Tabulator Console Sign-In</h1>
            <p className="text-xs text-slate-500">Chief Tabulator, Auditor & Stage Control</p>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-700 mb-1">Email</label>
            <Input type="email" placeholder="tabulator@tabul8.local" {...register('email')} />
            {errors.email && <p className="mt-1 text-xs text-rose-600">{errors.email.message}</p>}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-700 mb-1">Password</label>
            <Input type="password" placeholder="••••••••" {...register('password')} />
            {errors.password && (
              <p className="mt-1 text-xs text-rose-600">{errors.password.message}</p>
            )}
          </div>

          {errors.root && (
            <div className="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700">
              {errors.root.message}
            </div>
          )}

          <Button type="submit" className="w-full" size="lg" disabled={isSubmitting}>
            {isSubmitting ? 'Signing In...' : 'Open Tabulator Console'}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
