import { AdminLoginForm } from '@/features/auth';

export function AdminLoginPage() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-12">
      <div className="mb-8 text-center">
        <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-900 text-lg font-bold tracking-tight text-white shadow-sm">
          T8
        </div>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">
          TABUL8
        </h1>
        <p className="mt-1 text-sm text-slate-500">
          Official Competition Tabulation Console
        </p>
      </div>
      <AdminLoginForm />
    </div>
  );
}
