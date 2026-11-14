// @vitest-environment happy-dom
import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { RoundsFunnelPanel } from '@/features/event-setup/components/RoundsFunnelPanel';
import type { RoundDto } from '@/types/api';

afterEach(() => {
  cleanup();
});

const initialRounds: RoundDto[] = [
  {
    id: 1,
    event_id: 1,
    name: 'Round 1: Preliminaries',
    sequence: 1,
    scoring_strategy: 'weighted',
    weight_in_overall: 50,
    is_back_to_zero: true,
    cutoff_mode: 'eliminate_n',
    advancement_count: 10,
    eliminate_count: 5,
    status: 'upcoming',
    is_locked: false,
  },
  {
    id: 2,
    event_id: 1,
    name: 'Round 2: Semi-Finals',
    sequence: 2,
    scoring_strategy: 'weighted',
    weight_in_overall: 50,
    is_back_to_zero: false,
    cutoff_mode: 'top_n_advance',
    advancement_count: 5,
    eliminate_count: 5,
    status: 'upcoming',
    is_locked: false,
  },
];

describe('RoundsFunnelPanel dual-synced cascade', () => {
  it('updates Advance Top to 12 and cascades Round 2 entering pool to 12 when Round 1 Eliminate changes from 5 to 3', () => {
    render(
      <RoundsFunnelPanel
        eventId={1}
        rounds={initialRounds}
        totalContestants={15}
        isBusy={false}
        onGenerateFunnel={vi.fn()}
        onUpdateRound={vi.fn()}
        onCreateRound={vi.fn()}
        onDeleteRound={vi.fn()}
        onReorderRounds={vi.fn()}
      />,
    );

    const r1ElimInput = screen.getByLabelText('Round 1 Eliminate') as HTMLInputElement;
    const r1AdvInput = screen.getByLabelText('Round 1 Advance Top') as HTMLInputElement;

    expect(r1ElimInput.value).toBe('5');
    expect(r1AdvInput.value).toBe('10');
    expect(screen.getByTestId('round-entering-2')).toHaveTextContent('10 entering');

    fireEvent.change(r1ElimInput, { target: { value: '3' } });

    expect(r1AdvInput.value).toBe('12');
    expect(screen.getByTestId('round-entering-2')).toHaveTextContent('12 entering');
  });
});
