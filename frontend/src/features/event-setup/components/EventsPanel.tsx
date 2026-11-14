import { zodResolver } from '@hookform/resolvers/zod';
import { Copy, Plus, Trash2 } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { EventDto } from '@/types/api';
import {
  applyServerValidationErrors,
  createEventSchema,
  type CreateEventValues,
} from '../schemas/setupSchemas';

interface EventsPanelProps {
  events: EventDto[];
  selectedEventId: number;
  isBusy: boolean;
  onSelectEvent: (eventId: number) => void;
  onCreateEvent: (payload: {
    title: string;
    subtitle?: string;
    event_type: EventDto['event_type'];
    preset?: EventDto['event_type'];
    clone_from_event_id?: number;
  }) => Promise<EventDto>;
  onDeleteEvent: (eventId: number) => void;
}

export function EventsPanel({
  events,
  selectedEventId,
  isBusy,
  onSelectEvent,
  onCreateEvent,
  onDeleteEvent,
}: EventsPanelProps) {
  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CreateEventValues>({
    resolver: zodResolver(createEventSchema),
    defaultValues: {
      title: '',
      subtitle: '',
      event_type: 'pageant',
      preset: 'pageant',
    },
  });

  const onSubmit = async (values: CreateEventValues) => {
    try {
      const created = await onCreateEvent(values);
      reset({
        title: '',
        subtitle: '',
        event_type: values.event_type,
        preset: values.event_type,
      });
      onSelectEvent(created.id);
    } catch (err) {
      applyServerValidationErrors(err, setError);
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-12">
      <Card className="lg:col-span-7">
        <CardHeader>
          <CardTitle className="text-base">WF-08 · Competition Events</CardTitle>
          <CardDescription>
            Switch active setup event, clone an existing rubric blueprint, or delete draft setup events.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {events.map((ev) => {
            const isSelected = ev.id === selectedEventId;
            return (
              <div
                key={ev.id}
                className={`flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3.5 ${
                  isSelected
                    ? 'border-indigo-400 bg-indigo-50/40'
                    : 'border-slate-200 bg-white'
                }`}
              >
                <button
                  type="button"
                  onClick={() => onSelectEvent(ev.id)}
                  className="flex-1 text-left cursor-pointer"
                >
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-slate-900">{ev.title}</span>
                    <Badge
                      variant={
                        ev.status === 'live'
                          ? 'success'
                          : ev.status === 'completed'
                            ? 'secondary'
                            : 'warning'
                      }
                    >
                      {ev.status}
                    </Badge>
                    <Badge variant="default">{ev.event_type}</Badge>
                  </div>
                  {ev.subtitle && (
                    <p className="text-xs text-slate-500 mt-0.5">{ev.subtitle}</p>
                  )}
                </button>

                <div className="flex items-center gap-1.5">
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={isBusy}
                    onClick={async () => {
                      const cloned = await onCreateEvent({
                        title: `${ev.title} (Copy)`,
                        subtitle: ev.subtitle ?? undefined,
                        event_type: ev.event_type,
                        clone_from_event_id: ev.id,
                      });
                      onSelectEvent(cloned.id);
                    }}
                    className="gap-1 text-xs"
                  >
                    <Copy className="h-3.5 w-3.5" />
                    Clone
                  </Button>
                  {ev.status === 'setup' && (
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      disabled={isBusy}
                      onClick={() => onDeleteEvent(ev.id)}
                      className="text-rose-600 hover:bg-rose-50"
                      title="Delete setup event"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  )}
                </div>
              </div>
            );
          })}
        </CardContent>
      </Card>

      <Card className="lg:col-span-5">
        <CardHeader>
          <CardTitle className="text-base">WF-09 · New Event Blueprint</CardTitle>
          <CardDescription>
            Create a competition event pre-seeded with round stages and criteria weights.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <div className="space-y-1">
              <label className="text-xs font-medium text-slate-700" htmlFor="ev-title">
                Event Title
              </label>
              <Input id="ev-title" placeholder="Miss Grand Regionals 2026" {...register('title')} />
              {errors.title && (
                <p className="text-xs text-rose-600">{errors.title.message}</p>
              )}
            </div>

            <div className="space-y-1">
              <label className="text-xs font-medium text-slate-700" htmlFor="ev-sub">
                Subtitle (optional)
              </label>
              <Input id="ev-sub" placeholder="Grand Coronation Night" {...register('subtitle')} />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-700" htmlFor="ev-type">
                  Competition Type
                </label>
                <select
                  id="ev-type"
                  {...register('event_type')}
                  className="h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm"
                >
                  <option value="pageant">Pageant</option>
                  <option value="singing">Singing</option>
                  <option value="dance">Dance</option>
                  <option value="custom">Custom</option>
                </select>
              </div>
              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-700" htmlFor="ev-preset">
                  Starter Blueprint
                </label>
                <select
                  id="ev-preset"
                  {...register('preset')}
                  className="h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm"
                >
                  <option value="pageant">3-Round Pageant</option>
                  <option value="singing">2-Round Singing</option>
                  <option value="dance">2-Round Dance</option>
                  <option value="custom">1-Round Blank</option>
                </select>
              </div>
            </div>

            {errors.root && (
              <p className="text-xs text-rose-600">{errors.root.message}</p>
            )}

            <Button type="submit" disabled={isSubmitting} className="w-full gap-1.5">
              <Plus className="h-4 w-4" />
              {isSubmitting ? 'Creating...' : 'Create Event from Blueprint'}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
