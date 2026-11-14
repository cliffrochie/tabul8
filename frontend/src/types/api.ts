export interface ApiResponse<T> {
  data: T;
  message: string;
  errors: null;
}

export interface ApiError {
  data: null;
  message: string;
  errors: Record<string, string[]> | null;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
  message: string;
  errors: null;
}

export type ScoringStrategy = 'weighted' | 'ordinal' | 'trimmed';
export type ScoreSheetStatus = 'DRAFT' | 'LOCKED' | 'UNLOCK_REQUESTED';
export type LedMode = 'on_stage' | 'criteria' | 'reveal';
export type CutoffMode = 'top_n_advance' | 'eliminate_n';
export type QualificationMethod =
  | 'INITIAL_ROSTER'
  | 'AUTO_TOP_N'
  | 'MANUAL_WILDCARD';

export interface CriterionDto {
  id: number;
  round_id: number;
  parent_id: number | null;
  name: string;
  description: string | null;
  weight_percentage: number;
  min_score: number;
  max_score: number;
  score_step: number;
  sequence: number;
}

export interface RoundDto {
  id: number;
  event_id: number;
  name: string;
  sequence: number;
  scoring_strategy: ScoringStrategy;
  weight_in_overall: number;
  is_back_to_zero: boolean;
  cutoff_mode?: CutoffMode;
  advancement_count: number;
  eliminate_count?: number;
  status: 'upcoming' | 'active' | 'locked' | 'finalized';
  is_locked: boolean;
  criteria?: CriterionDto[];
}

export interface ContestantDto {
  id: number;
  event_id: number;
  candidate_number: number;
  name: string;
  origin_or_group: string;
  performance_meta: string | null;
  category: string;
  asset_ratio: 'PORTRAIT_4:5' | 'PHOTO_300x300' | 'WIDE_16:9';
  status: 'active' | 'eliminated' | 'withdrawn';
  photo_url: string | null;
  is_qualified_in_active_round?: boolean;
  qualified_from_rank?: number | null;
  qualification_method?: QualificationMethod | null;
  eliminated_in_round_name?: string | null;
}

export interface RoundFunnelStep {
  round_id: number;
  sequence: number;
  name: string;
  status: 'upcoming' | 'active' | 'locked' | 'finalized';
  starting_count: number;
  eliminate_count: number;
  advancement_count: number;
}

export interface JudgeDto {
  id: number;
  event_id: number;
  judge_number: number;
  name: string;
  role_title: string;
  is_chairman: boolean;
  is_active: boolean;
  last_seen_at: string | null;
}

export interface EventDto {
  id: number;
  title: string;
  subtitle: string | null;
  event_type: 'pageant' | 'singing' | 'dance' | 'custom';
  status: 'setup' | 'live' | 'completed';
  active_round_id: number | null;
  on_stage_contestant_id: number | null;
  led_mode: LedMode;
  revealed_contestant_ids: number[];
  settings: Record<string, unknown>;
}

export interface JudgeScoreSheet {
  status: ScoreSheetStatus;
  updated_at: string | null;
  weighted_total: number;
  personal_rank: number;
  is_tied: boolean;
  criteria: Record<string, number>;
}

export interface JudgeScoreBreakdownItem {
  judge_id: number;
  judge_number: number;
  score: number;
  rank: number;
  is_dropped: boolean;
}

export interface RoundResultDto {
  round_id: number;
  contestant_id: number;
  mean_score: number;
  trimmed_mean: number;
  rank_sum: number;
  first_place_count: number;
  deduction_points: number;
  net_composite: number;
  cumulative_score: number | null;
  primary_crit_avg: number;
  chairman_rank: number | null;
  final_rank: number;
  is_tied: boolean;
  tie_break_rule_applied: string | null;
  is_qualified: boolean;
  judge_breakdown_snapshot: JudgeScoreBreakdownItem[];
  computed_at: string | null;
}

export interface AdminMatrixCell {
  status: ScoreSheetStatus;
  weighted_total: number;
  updated_at: string | null;
}

export interface EventStatePayload {
  event: EventDto;
  rounds: RoundDto[];
  active_round: RoundDto | null;
  round_funnel?: RoundFunnelStep[];
  contestants: ContestantDto[];
  my_scores?: Record<string, JudgeScoreSheet>;
  judges?: JudgeDto[];
  scores_matrix?: Record<string, Record<string, AdminMatrixCell>>;
  leaderboard?: RoundResultDto[];
  revealed_finalists?: ContestantDto[];
}

export interface QueuedScoreMutation {
  client_mutation_id: string;
  round_id: number;
  contestant_id: number;
  criterion_id?: number;
  raw_score?: number;
  action: 'UPSERT_SCORE' | 'LOCK_SHEET';
  client_timestamp: string;
}
