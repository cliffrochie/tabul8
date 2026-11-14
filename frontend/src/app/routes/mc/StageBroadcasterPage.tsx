import {
  ChevronLeft,
  ChevronRight,
  Mic,
  MonitorPlay,
  Radio,
  Sparkles,
  UserCheck,
} from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { ConsoleLayout } from '@/components/layouts/ConsoleLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { useAdminEventsList } from '@/features/event-setup';
import {
  useAdminEventState,
  useAdminMutations,
} from '@/features/tabulator-console';
import { useAuthStore } from '@/stores/authStore';
import type { LedMode } from '@/types/api';

const LED_MODES: { mode: LedMode; label: string; description: string }[] = [
  {
    mode: 'on_stage',
    label: 'On Stage Spotlight',
    description: 'Broadcast active performer photo, # and origin.',
  },
  {
    mode: 'criteria',
    label: 'Round Criteria',
    description: 'Show current round judging breakdown on LED wall.',
  },
  {
    mode: 'reveal',
    label: 'Results Reveal',
    description: 'Display revealed qualifiers & ranked winners.',
  },
];

export function StageBroadcasterPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const user = useAuthStore((s) => s.user);
  const canBroadcast =
    user?.role === 'mc_stage' ||
    user?.role === 'tabulator' ||
    user?.role === 'super_admin';

  const eventsQuery = useAdminEventsList();
  const events = eventsQuery.data?.data ?? [];
  const requestedId = Number(searchParams.get('eventId')) || 0;
  const liveEvent = events.find((e) => e.status === 'live');
  const eventId =
    events.find((e) => e.id === requestedId)?.id ??
    liveEvent?.id ??
    events[0]?.id ??
    1;

  const { data: state, onlineJudgeIds, isLoading } = useAdminEventState(eventId);
  const { syncStage } = useAdminMutations(eventId, state?.active_round?.id);

  if (!canBroadcast) {
    return (
      <ConsoleLayout eventTitle="MC Stage Cue Console">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Access Restricted</CardTitle>
            <CardDescription>
              The `/mc` Stage Broadcaster console is restricted to{' '}
              <code>mc_stage</code>, <code>tabulator</code>, and{' '}
              <code>super_admin</code> operators.
            </CardDescription>
          </CardHeader>
        </Card>
      </ConsoleLayout>
    );
  }

  const contestants = state?.contestants ?? [];
  const qualifiedQueue = contestants
    .filter(
      (c) =>
        c.status === 'active' && (c.is_qualified_in_active_round ?? true),
    )
    .sort((a, b) => a.candidate_number - b.candidate_number);

  const onStageId = state?.event.on_stage_contestant_id ?? null;
  const onStageContestant =
    contestants.find((c) => c.id === onStageId) ?? null;
  const currentIndex = qualifiedQueue.findIndex((c) => c.id === onStageId);

  const pushStage = (nextContestantId: number | null) => {
    syncStage.mutate({
      event_id: eventId,
      on_stage_contestant_id: nextContestantId,
    });
  };

  const pushLedMode = (ledMode: LedMode) => {
    syncStage.mutate({
      event_id: eventId,
      led_mode: ledMode,
    });
  };

  const prevContestant =
    currentIndex > 0 ? qualifiedQueue[currentIndex - 1] : null;
  const nextContestant =
    currentIndex >= 0 && currentIndex + 1 < qualifiedQueue.length
      ? qualifiedQueue[currentIndex + 1]
      : currentIndex === -1
        ? (qualifiedQueue[0] ?? null)
        : null;

  return (
    <ConsoleLayout
      eventTitle={
        state?.event.title
          ? `${state.event.title} · MC Stage`
          : 'MC Stage Cue Console'
      }
      onlineJudgesCount={onlineJudgeIds.length}
      totalJudgesCount={state?.judges?.filter((j) => j.is_active).length ?? 0}
    >
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Badge variant="info" className="gap-1.5">
              <Radio className="h-3.5 w-3.5" />
              MC Stage Broadcaster
            </Badge>
            {state?.active_round ? (
              <Badge variant="success">
                Active Round: {state.active_round.name}
              </Badge>
            ) : (
              <Badge variant="warning">No Active Round Selected</Badge>
            )}
          </div>

          {events.length > 1 && (
            <select
              value={eventId}
              onChange={(e) => setSearchParams({ eventId: e.target.value })}
              className="h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-medium text-slate-800"
            >
              {events.map((ev) => (
                <option key={ev.id} value={ev.id}>
                  {ev.title} ({ev.status})
                </option>
              ))}
            </select>
          )}
        </div>

        {isLoading || !state ? (
          <Card>
            <CardContent className="py-10 text-center text-sm text-slate-500">
              Loading stage cue state...
            </CardContent>
          </Card>
        ) : (
          <>
            <div className="grid gap-6 lg:grid-cols-12">
              <Card className="lg:col-span-7 border-slate-300 bg-slate-900 text-white">
                <CardHeader className="flex flex-row items-center justify-between gap-2">
                  <div>
                    <CardTitle className="text-base text-white flex items-center gap-2">
                      <Mic className="h-4 w-4 text-emerald-400" />
                      Live Stage Spotlight
                    </CardTitle>
                    <CardDescription className="text-slate-300">
                      Synchronized in real time with all Judge tablets and the
                      Arena LED wall.
                    </CardDescription>
                  </div>
                  <Badge variant="info" className="uppercase">
                    LED: {state.event.led_mode}
                  </Badge>
                </CardHeader>
                <CardContent className="space-y-4">
                  {onStageContestant ? (
                    <div className="rounded-xl border border-slate-700 bg-slate-800/80 p-5">
                      <p className="text-xs font-semibold uppercase tracking-widest text-emerald-400">
                        Candidate #{onStageContestant.candidate_number}
                      </p>
                      <h2 className="mt-1 text-2xl font-bold text-white">
                        {onStageContestant.name}
                      </h2>
                      <p className="mt-1 text-sm text-slate-300">
                        {onStageContestant.origin_or_group}
                        {onStageContestant.performance_meta
                          ? ` · ${onStageContestant.performance_meta}`
                          : ''}
                      </p>
                    </div>
                  ) : (
                    <div className="rounded-xl border border-dashed border-slate-700 bg-slate-800/40 p-6 text-center text-sm text-slate-400">
                      Stage is currently standby (no performer spotlighted).
                    </div>
                  )}

                  <div className="flex flex-wrap items-center gap-2">
                    <Button
                      type="button"
                      variant="secondary"
                      disabled={syncStage.isPending || !prevContestant}
                      onClick={() =>
                        prevContestant && pushStage(prevContestant.id)
                      }
                      className="gap-1"
                    >
                      <ChevronLeft className="h-4 w-4" />
                      Previous
                    </Button>
                    <Button
                      type="button"
                      variant="default"
                      disabled={syncStage.isPending || !nextContestant}
                      onClick={() =>
                        nextContestant && pushStage(nextContestant.id)
                      }
                      className="gap-1 bg-emerald-600 hover:bg-emerald-500 text-white"
                    >
                      {currentIndex === -1
                        ? 'Cue First Performer'
                        : 'Next Performer'}
                      <ChevronRight className="h-4 w-4" />
                    </Button>
                    {onStageContestant && (
                      <Button
                        type="button"
                        variant="outline"
                        disabled={syncStage.isPending}
                        onClick={() => pushStage(null)}
                        className="border-slate-600 bg-slate-800 text-slate-200 hover:bg-slate-700"
                      >
                        Clear Stage
                      </Button>
                    )}
                  </div>
                </CardContent>
              </Card>

              <Card className="lg:col-span-5">
                <CardHeader>
                  <CardTitle className="text-base flex items-center gap-2">
                    <MonitorPlay className="h-4 w-4 text-slate-700" />
                    Arena LED Display Mode
                  </CardTitle>
                  <CardDescription>
                    Switch public venue projection screens immediately.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-2.5">
                  {LED_MODES.map((item) => {
                    const isActive = state.event.led_mode === item.mode;
                    return (
                      <button
                        key={item.mode}
                        type="button"
                        disabled={syncStage.isPending}
                        onClick={() => pushLedMode(item.mode)}
                        className={`w-full rounded-xl border p-3 text-left transition-colors ${
                          isActive
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : 'border-slate-200 bg-white text-slate-900 hover:bg-slate-50'
                        }`}
                      >
                        <div className="flex items-center justify-between">
                          <span className="text-sm font-semibold">
                            {item.label}
                          </span>
                          {isActive && (
                            <Sparkles className="h-4 w-4 text-emerald-400" />
                          )}
                        </div>
                        <p
                          className={`mt-0.5 text-xs ${
                            isActive ? 'text-slate-300' : 'text-slate-500'
                          }`}
                        >
                          {item.description}
                        </p>
                      </button>
                    );
                  })}
                </CardContent>
              </Card>
            </div>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">
                  Qualified Stage Queue ({qualifiedQueue.length})
                </CardTitle>
                <CardDescription>
                  Click any qualified performer to cue them onto stage and
                  highlight them on Judge devices.
                </CardDescription>
              </CardHeader>
              <CardContent>
                {qualifiedQueue.length === 0 ? (
                  <p className="py-6 text-center text-sm text-slate-500">
                    No qualified active contestants in this round.
                  </p>
                ) : (
                  <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {qualifiedQueue.map((contestant) => {
                      const isLive = contestant.id === onStageId;
                      return (
                        <button
                          key={contestant.id}
                          type="button"
                          disabled={syncStage.isPending}
                          onClick={() => pushStage(contestant.id)}
                          className={`rounded-xl border p-3 text-left transition-all ${
                            isLive
                              ? 'border-emerald-600 bg-emerald-50 ring-2 ring-emerald-500/30'
                              : 'border-slate-200 bg-white hover:border-slate-400 hover:bg-slate-50'
                          }`}
                        >
                          <div className="flex items-center justify-between gap-2">
                            <span className="font-mono text-xs font-bold text-slate-600">
                              #{contestant.candidate_number}
                            </span>
                            {isLive ? (
                              <Badge variant="success">ON STAGE</Badge>
                            ) : (
                              <UserCheck className="h-3.5 w-3.5 text-slate-400" />
                            )}
                          </div>
                          <p className="mt-1.5 font-semibold text-slate-900 truncate">
                            {contestant.name}
                          </p>
                          <p className="text-xs text-slate-500 truncate">
                            {contestant.origin_or_group}
                          </p>
                        </button>
                      );
                    })}
                  </div>
                )}
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </ConsoleLayout>
  );
}
