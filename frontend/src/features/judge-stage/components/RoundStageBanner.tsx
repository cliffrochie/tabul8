import { Badge } from '@/components/ui/badge';
import type { RoundDto } from '@/types/api';

interface RoundStageBannerProps {
  round: RoundDto;
  totalRounds: number;
  activeQualifierCount: number;
}

export function RoundStageBanner({
  round,
  totalRounds,
  activeQualifierCount,
}: RoundStageBannerProps) {
  const isFinalStage = round.sequence >= totalRounds && totalRounds > 0;

  return (
    <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-xs">
      <div className="flex flex-wrap items-center gap-2.5">
        <span className="text-xs font-bold uppercase tracking-wider text-indigo-700">
          STAGE {round.sequence} OF {Math.max(1, totalRounds)} •{' '}
          {isFinalStage
            ? 'FINAL STAGE'
            : `TOP ${round.advancement_count} ADVANCE`}
        </span>
        <span className="text-sm font-semibold text-slate-900">
          {round.name}
        </span>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Badge variant={round.is_back_to_zero ? 'default' : 'info'}>
          {round.is_back_to_zero ? 'Reset 0' : 'Cumulative'}
        </Badge>
        <Badge variant="success">
          {activeQualifierCount} Active Qualifiers
        </Badge>
      </div>
    </div>
  );
}
