import { Component, type ErrorInfo, type ReactNode } from 'react';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface AppErrorBoundaryProps {
  children: ReactNode;
}

interface AppErrorBoundaryState {
  hasError: boolean;
  errorMessage: string | null;
}

export class AppErrorBoundary extends Component<
  AppErrorBoundaryProps,
  AppErrorBoundaryState
> {
  public override state: AppErrorBoundaryState = {
    hasError: false,
    errorMessage: null,
  };

  public static getDerivedStateFromError(error: Error): AppErrorBoundaryState {
    return {
      hasError: true,
      errorMessage: error.message || 'An unexpected rendering error occurred.',
    };
  }

  public override componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('[TABUL8 AppErrorBoundary]', error, info.componentStack);
  }

  private handleReset = (): void => {
    this.setState({ hasError: false, errorMessage: null });
  };

  private handleReload = (): void => {
    window.location.reload();
  };

  public override render(): ReactNode {
    if (!this.state.hasError) {
      return this.props.children;
    }

    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-6">
        <div className="w-full max-w-md rounded-2xl border border-rose-200 bg-white p-6 shadow-sm space-y-4">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
              <AlertTriangle className="h-5 w-5" />
            </div>
            <div>
              <h1 className="text-base font-semibold text-slate-900">
                Something went wrong in this screen
              </h1>
              <p className="mt-1 text-xs text-slate-600">
                Your saved scores and outbox mutations are preserved safely.
                You can retry rendering this view or reload the application.
              </p>
            </div>
          </div>

          {this.state.errorMessage && (
            <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 break-words">
              {this.state.errorMessage}
            </div>
          )}

          <div className="flex flex-wrap items-center gap-2 pt-1">
            <Button type="button" variant="outline" onClick={this.handleReset}>
              Retry Render
            </Button>
            <Button
              type="button"
              onClick={this.handleReload}
              className="gap-1.5"
            >
              <RefreshCw className="h-4 w-4" />
              Reload Application
            </Button>
          </div>
        </div>
      </div>
    );
  }
}
