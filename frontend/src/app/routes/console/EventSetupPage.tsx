import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ConsoleLayout } from '@/components/layouts/ConsoleLayout';
import { Button } from '@/components/ui/button';
import { useAdminEventState } from '@/features/tabulator-console';
import {
  CriteriaPanel,
  EventsPanel,
  PreFlightBanner,
  RoundsFunnelPanel,
  useAdminEventsList,
  useEventPreflight,
  useSetupMutations,
} from '@/features/event-setup';

type SetupTab = 'events' | 'rounds' | 'criteria';

const TABS: { id: SetupTab; label: string }[] = [
  { id: 'events', label: 'WF-08/09 · Events' },
  { id: 'rounds', label: 'WF-10 · Rounds Funnel' },
  { id: 'criteria', label: 'WF-11 · Criteria & Weights' },
];

export function EventSetupPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [activeTab, setActiveTab] = useState<SetupTab>('events');

  const eventsListQuery = useAdminEventsList();
  const events = eventsListQuery.data?.data ?? [];
  const requestedId = Number(searchParams.get('eventId')) || 0;
  const eventId =
    events.find((e) => e.id === requestedId)?.id ?? events[0]?.id ?? requestedId ?? 1;

  const { data: state, isLoading } = useAdminEventState(eventId);
  const { data: preflight } = useEventPreflight(state?.event?.id ?? null);
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
        ) : (
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
        )}
      </div>
    </ConsoleLayout>
  );
}
