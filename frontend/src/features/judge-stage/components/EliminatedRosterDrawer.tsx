import { useState } from 'react';
import { ChevronDown, ChevronRight, UserX } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import type { ContestantDto } from '@/types/api';

interface EliminatedRosterDrawerProps {
  contestants: ContestantDto[];
}

export function EliminatedRosterDrawer({
  contestants,
}: EliminatedRosterDrawerProps) {
  const [isOpen, setIsOpen] = useState(false);

  if (contestants.length === 0) {
    return null;
  }

  return (
    <Card className="mt-4 overflow-hidden border-slate-200 bg-slate-50/60">
      <button
        type="button"
        aria-expanded={isOpen}
        onClick={() => setIsOpen((prev) => !prev)}
        className="flex w-full cursor-pointer items-center justify-between px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 hover:bg-slate-100/70"
      >
        <span className="flex items-center gap-2">
          <UserX className="h-3.5 w-3.5 text-slate-400" />
          Eliminated / Non-Qualifying ({contestants.length})
        </span>
        {isOpen ? (
          <ChevronDown className="h-4 w-4 text-slate-400" />
        ) : (
          <ChevronRight className="h-4 w-4 text-slate-400" />
        )}
      </button>

      {isOpen && (
        <div className="divide-y divide-slate-200/70 border-t border-slate-200 bg-white">
          {contestants.map((c) => (
            <div
              key={c.id}
              className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm text-slate-600"
            >
              <div className="flex items-center gap-2.5">
                <span className="inline-flex h-6 w-6 items-center justify-center rounded bg-slate-100 text-xs font-semibold text-slate-500 tabular-nums">
                  #{c.candidate_number}
                </span>
                <div>
                  <div className="font-medium text-slate-700">{c.name}</div>
                  <div className="text-xs text-slate-400">
                    {c.origin_or_group}
                  </div>
                </div>
              </div>
              <Badge variant="secondary">
                Eliminated in {c.eliminated_in_round_name ?? 'Prior Round'}
              </Badge>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}
