import type { ReactNode } from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'sonner';
import { AppErrorBoundary } from '@/components/errors/AppErrorBoundary';
import { queryClient } from '@/lib/react-query';

export function AppProvider({ children }: { children: ReactNode }) {
  return (
    <AppErrorBoundary>
      <QueryClientProvider client={queryClient}>
        {children}
        <Toaster position="bottom-right" richColors />
      </QueryClientProvider>
    </AppErrorBoundary>
  );
}
