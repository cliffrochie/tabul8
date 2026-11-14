// @vitest-environment happy-dom
import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { JudgeMatrixGrid } from '@/features/judge-matrix/components/JudgeMatrixGrid';
import { EliminatedRosterDrawer } from '@/features/judge-stage/components/EliminatedRosterDrawer';
import type { ContestantDto, CriterionDto, JudgeScoreSheet } from '@/types/api';

afterEach(() => {
  cleanup();
});

const criteria: CriterionDto[] = [
  {
    id: 10,
    round_id: 2,
    parent_id: null,
    name: 'Final Q&A',
    description: null,
    weight_percentage: 100,
    min_score: 70,
    max_score: 100,
    score_step: 1,
    sequence: 1,
  },
];

const contestants: ContestantDto[] = [
  {
    id: 1,
    event_id: 1,
    candidate_number: 1,
    name: 'Ariana Qualifier',
    origin_or_group: 'Manila',
    performance_meta: null,
    category: 'Senior',
    asset_ratio: 'PORTRAIT_4:5',
    status: 'active',
    photo_url: null,
    is_qualified_in_active_round: true,
    qualified_from_rank: 1,
    qualification_method: 'AUTO_TOP_N',
    eliminated_in_round_name: null,
  },
  {
    id: 2,
    event_id: 1,
    candidate_number: 2,
    name: 'Beatrice Qualifier',
    origin_or_group: 'Cebu',
    performance_meta: null,
    category: 'Senior',
    asset_ratio: 'PORTRAIT_4:5',
    status: 'active',
    photo_url: null,
    is_qualified_in_active_round: true,
    qualified_from_rank: 2,
    qualification_method: 'MANUAL_WILDCARD',
    eliminated_in_round_name: null,
  },
  {
    id: 3,
    event_id: 1,
    candidate_number: 3,
    name: 'Clara Eliminated',
    origin_or_group: 'Davao',
    performance_meta: null,
    category: 'Senior',
    asset_ratio: 'PORTRAIT_4:5',
    status: 'active',
    photo_url: null,
    is_qualified_in_active_round: false,
    qualified_from_rank: null,
    qualification_method: null,
    eliminated_in_round_name: 'Round 1',
  },
];

const myScores: Record<string, JudgeScoreSheet> = {
  '1': {
    status: 'DRAFT',
    updated_at: null,
    weighted_total: 92,
    personal_rank: 1,
    is_tied: false,
    criteria: { '10': 92 },
  },
  '2': {
    status: 'DRAFT',
    updated_at: null,
    weighted_total: 70,
    personal_rank: 2,
    is_tied: false,
    criteria: { '10': 70 }, // still at min_score
  },
};

describe('Judge multi-round elimination views', () => {
  it('renders only active qualifiers in JudgeMatrixGrid and puts eliminated candidates in a read-only drawer', () => {
    const onLockAll = vi.fn();
    render(
      <div>
        <JudgeMatrixGrid
          roundName="Round 2: Top Finalists"
          isRoundFrozen={false}
          contestants={contestants}
          criteria={criteria}
          myScores={myScores}
          onScoreChange={vi.fn()}
          onLockRow={vi.fn()}
          onLockAll={onLockAll}
          onRequestUnlock={vi.fn()}
        />
        <EliminatedRosterDrawer
          contestants={contestants.filter(
            (c) => c.is_qualified_in_active_round === false,
          )}
        />
      </div>,
    );

    // Main grid renders active qualifiers with scoped personal ranks (#1 of 2, #2 of 2)
    expect(screen.getByText('Ariana Qualifier')).toBeInTheDocument();
    expect(screen.getByText('Beatrice Qualifier')).toBeInTheDocument();
    expect(screen.getByText('#1 of 2')).toBeInTheDocument();
    expect(screen.getByText('#2 of 2')).toBeInTheDocument();

    // Eliminated candidate is hidden until collapsible drawer is toggled open
    expect(screen.queryByText('Clara Eliminated')).not.toBeInTheDocument();
    const drawerToggle = screen.getByRole('button', {
      name: /Eliminated \/ Non-Qualifying/i,
    });
    fireEvent.click(drawerToggle);

    expect(screen.getByText('Clara Eliminated')).toBeInTheDocument();
    expect(screen.getByText(/Eliminated in Round 1/i)).toBeInTheDocument();

    // Drawer must contain zero score number inputs (main grid has 2 qualifiers x 1 criterion = 2 inputs)
    expect(screen.getAllByRole('spinbutton')).toHaveLength(2);
  });

  it('prompts confirmation before invoking onLockAll when any qualifier criterion is still at min_score', () => {
    const onLockAll = vi.fn();
    render(
      <JudgeMatrixGrid
        roundName="Round 2: Top Finalists"
        isRoundFrozen={false}
        contestants={contestants}
        criteria={criteria}
        myScores={myScores}
        onScoreChange={vi.fn()}
        onLockRow={vi.fn()}
        onLockAll={onLockAll}
        onRequestUnlock={vi.fn()}
      />,
    );

    fireEvent.click(
      screen.getByRole('button', { name: /Lock & Submit Entire Round Sheet/i }),
    );
    expect(onLockAll).not.toHaveBeenCalled();

    const confirmBtn = screen.getByRole('button', { name: /Confirm Lock All/i });
    fireEvent.click(confirmBtn);
    expect(onLockAll).toHaveBeenCalledTimes(1);
  });
});
