// @vitest-environment happy-dom
import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as authApi from './api/authApi';
import { AdminLoginForm } from './components/AdminLoginForm';

describe('AdminLoginForm', () => {
  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
  });

  it('validates email and password via Zod before submitting', async () => {
    const loginSpy = vi.spyOn(authApi, 'loginAdmin');

    render(
      <MemoryRouter>
        <AdminLoginForm />
      </MemoryRouter>,
    );

    fireEvent.change(screen.getByPlaceholderText('tabulator@tabul8.local'), {
      target: { value: 'invalid-email' },
    });
    fireEvent.click(
      screen.getByRole('button', { name: /Open Tabulator Console/i }),
    );

    await waitFor(() => {
      expect(screen.getByText('Enter a valid email address')).toBeInTheDocument();
    });
    expect(loginSpy).not.toHaveBeenCalled();
  });

  it('requires non-empty password before submitting valid email', async () => {
    const loginSpy = vi.spyOn(authApi, 'loginAdmin');

    render(
      <MemoryRouter>
        <AdminLoginForm />
      </MemoryRouter>,
    );

    fireEvent.change(screen.getByPlaceholderText('tabulator@tabul8.local'), {
      target: { value: 'tabulator@tabul8.local' },
    });
    fireEvent.click(
      screen.getByRole('button', { name: /Open Tabulator Console/i }),
    );

    await waitFor(() => {
      expect(screen.getByText('Password is required')).toBeInTheDocument();
    });
    expect(loginSpy).not.toHaveBeenCalled();
  });
});
