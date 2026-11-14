// @vitest-environment happy-dom
import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AppErrorBoundary } from './AppErrorBoundary';

function Crasher({ shouldThrow }: { shouldThrow: boolean }) {
  if (shouldThrow) {
    throw new Error('Unexpected tabulation render failure');
  }
  return <div>Recovered Panel</div>;
}

describe('AppErrorBoundary', () => {
  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
  });

  it('catches child render errors and displays fallback recovery UI', () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});

    render(
      <AppErrorBoundary>
        <Crasher shouldThrow />
      </AppErrorBoundary>,
    );

    expect(
      screen.getByText(/Something went wrong in this screen/i),
    ).toBeInTheDocument();
    expect(
      screen.getByText(/Unexpected tabulation render failure/i),
    ).toBeInTheDocument();

    const resetBtn = screen.getByRole('button', { name: /Retry Render/i });
    expect(resetBtn).toBeInTheDocument();
    expect(
      screen.getByRole('button', { name: /Reload Application/i }),
    ).toBeInTheDocument();

    fireEvent.click(resetBtn);
  });
});
