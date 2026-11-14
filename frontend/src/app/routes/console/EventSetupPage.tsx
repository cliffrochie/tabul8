import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ConsoleLayout } from '@/components/layouts/ConsoleLayout';
import { Button } from '@/components/ui/button';
import { useAdminEventState } from '@/features/tabulator-console';
import {
  ContestantsPanel,
  CriteriaPanel,
  EventsPanel,
  JudgesPanel,
  PreFlightBanner,
  RoundsFunnelPanel,
  RulesUsersPanel,
  downloadJudgeCardsPdf,
  useAdminEventsList,
  useEventPreflight,
  useSetupMutations,
  useStaffUsers,
} from '@/features/event-setup';
import { useAuthStore } from '@/stores/authStore';

type SetupTab =
  | 'events'
  | 'rounds'
  | 'criteria'
  | 'contestants'
  | 'judges'
  | 'rules-users';

const TABS: { id: SetupTab; label: string }[] = [
  { id: 'events', label: 'WF-08/09 · Events' },
  { id: 'rounds', label: 'WF-10 · Rounds Funnel' },
  { id: 'criteria', label: 'WF-11 · Criteria & Weights' },
  { id: 'contestants', label: 'WF-12 · Contestants' },
  { id: 'judges', label: 'WF-13 · Judges & PINs' },
  { id: 'rules-users', label: 'WF-14 · Rules & Users' },
];

export function EventSetupPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [activeTab, setActiveTab] = useState<SetupTab>('events');
  const userRole = useAuthStore((s) => s.user?.role);
  const isSuperAdmin = userRole === 'super_admin';

  const eventsListQuery = useAdminEventsList();
  const events = eventsListQuery.data?.data ?? [];
  const requestedId = Number(searchParams.get('eventId')) || 0;
  const eventId =
    events.find((e) => e.id === requestedId)?.id ?? events[0]?.id ?? requestedId ?? 1;

  const { data: state, isLoading } = useAdminEventState(eventId);
  const { data: preflight } = useEventPreflight(state?.event?.id ?? null);
  const staffQuery = useStaffUsers(isSuperAdmin);
  const setup = useSetupMutations(eventId);

  const selectEvent = (nextId: number) => {
    setSearchParams({ eventId: String(nextId) });
  };

  return (
    <ConsoleLayout eventTitle={state?.event?.title ?? 'Event Setup Builder'}>
      <div className="space-y-6">
        {state?.event && (
          <PreFlightBanner
            event={state.event}
            preflight={preflight}
            isUpdating={setup.updateEvent.isPending}
            onGoLive={() =>
              setup.updateEvent.mutate({
                targetEventId: state.event.id,
                status: 'live',
              })
            }
          />
        )}

        <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
          {TABS.map((tab) => (
            <Button
              key={tab.id}
              type="button"
              size="sm"
              variant={activeTab === tab.id ? 'default' : 'outline'}
              onClick={() => setActiveTab(tab.id)}
            >
              {tab.label}
            </Button>
          ))}
        </div>

        {isLoading && events.length === 0 ? (
          <div className="py-12 text-center text-sm text-slate-500">
            Loading event configuration...
          </div>
        ) : activeTab === 'events' ? (
          <EventsPanel
            events={events}
            selectedEventId={eventId}
            isBusy={setup.createEvent.isPending || setup.deleteEvent.isPending}
            onSelectEvent={selectEvent}
            onCreateEvent={(payload) => setup.createEvent.mutateAsync(payload)}
            onDeleteEvent={(targetId) => setup.deleteEvent.mutate(targetId)}
          />
        ) : !state ? (
          <div className="py-12 text-center text-sm text-slate-500">
            Select or create an event to configure stages and criteria.
          </div>
        ) : activeTab === 'rounds' ? (
          <RoundsFunnelPanel
            eventId={state.event.id}
            rounds={state.rounds}
            totalContestants={
              state.contestants.filter((c) => c.status === 'active').length
            }
            isBusy={
              setup.generateFunnel.isPending ||
              setup.updateRound.isPending ||
              setup.createRound.isPending ||
              setup.deleteRound.isPending ||
              setup.reorderRounds.isPending
            }
            onGenerateFunnel={(payload) => setup.generateFunnel.mutate(payload)}
            onUpdateRound={(payload) => setup.updateRound.mutate(payload)}
            onCreateRound={(payload) => setup.createRound.mutate(payload)}
            onDeleteRound={(roundId) => setup.deleteRound.mutate(roundId)}
            onReorderRounds={(roundIds) => setup.reorderRounds.mutate(roundIds)}
          />
        ) : activeTab === 'criteria' ? (
          <CriteriaPanel
            rounds={state.rounds}
            isBusy={
              setup.createCriterion.isPending ||
              setup.updateCriterion.isPending ||
              setup.deleteCriterion.isPending
            }
            onCreateCriterion={(payload) =>
              setup.createCriterion.mutateAsync(payload)
            }
            onUpdateCriterion={(payload) =>
              setup.updateCriterion.mutateAsync(payload)
            }
            onDeleteCriterion={(criterionId) =>
              setup.deleteCriterion.mutate(criterionId)
            }
          />
        ) : activeTab === 'contestants' ? (
          <ContestantsPanel
            contestants={state.contestants}
            activeRound={state.active_round}
            isBusy={
              setup.createContestant.isPending ||
              setup.updateContestant.isPending ||
              setup.deleteContestant.isPending ||
              setup.importContestantsCsv.isPending ||
              setup.renumberContestants.isPending ||
              setup.uploadContestantPhoto.isPending
            }
            onCreateContestant={(payload) =>
              setup.createContestant.mutateAsync(payload)
            }
            onUpdateContestant={(payload) =>
              setup.updateContestant.mutate(payload)
            }
            onDeleteContestant={(contestantId) =>
              setup.deleteContestant.mutate(contestantId)
            }
            onImportCsv={(csvText) =>
              setup.importContestantsCsv.mutateAsync(csvText)
            }
            onRenumber={() => setup.renumberContestants.mutate()}
            onUploadPhoto={(payload) =>
              setup.uploadContestantPhoto.mutate(payload)
            }
          />
        ) : activeTab === 'judges' ? (
          <JudgesPanel
            eventId={state.event.id}
            judges={state.judges ?? []}
            isBusy={
              setup.createJudge.isPending ||
              setup.updateJudge.isPending ||
              setup.deleteJudge.isPending ||
              setup.regenerateJudgePin.isPending
            }
            onCreateJudge={(payload) =>
              setup.createJudge.mutateAsync(payload)
            }
            onUpdateJudge={(payload) => setup.updateJudge.mutate(payload)}
            onDeleteJudge={(judgeId) => setup.deleteJudge.mutate(judgeId)}
            onRegeneratePin={(judgeId) =>
              setup.regenerateJudgePin.mutateAsync(judgeId)
            }
            onDownloadCardsPdf={(targetId) => downloadJudgeCardsPdf(targetId)}
          />
        ) : (
          <RulesUsersPanel
            key={state.event.id}
            event={state.event}
            isSuperAdmin={isSuperAdmin}
            staffUsers={staffQuery.data ?? []}
            isBusy={
              setup.updateEvent.isPending ||
              setup.createStaffUser.isPending ||
              setup.updateStaffUser.isPending ||
              setup.deleteStaffUser.isPending
            }
            onSaveEventSettings={(settings) =>
              setup.updateEvent.mutateAsync({
                targetEventId: state.event.id,
                settings,
              })
            }
            onCreateStaffUser={(payload) =>
              setup.createStaffUser.mutateAsync(payload)
            }
            onUpdateStaffUser={(payload) =>
              setup.updateStaffUser.mutate(payload)
            }
            onDeleteStaffUser={(userId) =>
              setup.deleteStaffUser.mutate(userId)
            }
          />
        )}
      </div>
    </ConsoleLayout>
  );
}
