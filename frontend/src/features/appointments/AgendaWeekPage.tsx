import { IconChevronLeft, IconChevronRight, IconLock } from '@tabler/icons-react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate, useSearch } from '@tanstack/react-router';
import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { Alert, Button, Card, DateField, Loading, SelectField } from '../../components/ui';
import { isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { todayInAppZone } from '../../lib/format/dates';
import { useAutoRefreshPaused } from '../../lib/hooks/auto-refresh';
import { useMediaQuery } from '../../lib/hooks/use-media-query';
import { meQueryOptions } from '../auth/api';
import { scheduleDetailQueryOptions } from '../schedules/api';
import { AgendaGrid } from './AgendaGrid';
import {
  AgendaFlags,
  AgendaViewSwitch,
  AppointmentPanel,
  AppointmentSummary,
  MissingAppointments,
} from './AgendaShared';
import {
  countText,
  ownVeterinarianOf,
  scheduleOptionLabel,
  selectedId,
  SINGLE_COLUMN_QUERY,
  useAgendaSchedules,
} from './agenda-shared';
import { appointmentRangeQueryOptions, rangeBlocksQueryOptions } from './api';
import {
  addDaysToDay,
  dayName,
  isAgendaDay,
  minutesToClock,
  weekEndOf,
  weekStartOf,
  weekTitle,
} from './dates';
import {
  appointmentLabel,
  buildWeekGrid,
  selectColumns,
  type DayGrid,
  type GridColumn,
  type GridItem,
} from './grid';
import type { AgendaSearch } from './search';

/**
 * /agenda?vista=semana (3.3, pregunta 4): la semana de lunes a domingo (en Asunción) de **una agenda por vez**, en siete
 * columnas con la grilla del día (`buildWeekGrid`, que reutiliza `buildDayGrid`), el horario y los bloqueos sombreados. La
 * agenda sale del selector (sin elegir, la primera de la vista por defecto: la del veterinario, pregunta 3). Un pedido de la
 * semana de la sucursal (hasta 500, con el aviso de los que faltan) más el detalle y los bloqueos de la semana de la agenda.
 * En el celular, la misma semana como una lista por día. Se actualiza como el día (cada 60 s, con la pausa).
 */
export function AgendaWeekPage() {
  const search: AgendaSearch = useSearch({ from: '/_sesion/agenda/' });
  const navigate = useNavigate({ from: '/agenda/' });
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const branchId = me?.current_branch?.id ?? null;
  const today = todayInAppZone();
  const day = search.fecha ?? today;
  const range = { from: weekStartOf(day), to: weekEndOf(day) };
  const showCancelled = search.cancelados === 1;
  const paused = useAutoRefreshPaused();
  const asList = useMediaQuery(SINGLE_COLUMN_QUERY);
  const [selected, setSelected] = useState<GridItem | null>(null);
  const panelRef = useRef<HTMLHeadingElement>(null);
  const emptyRef = useRef<HTMLParagraphElement>(null);
  const keyboardInContent = useRef(false);
  const openerRef = useRef<HTMLElement | null>(null);
  const [announced, setAnnounced] = useState<{ key: string; text: string } | null>(null);

  const rangeQuery = useQuery({
    ...appointmentRangeQueryOptions(branchId ?? 0, range, paused),
    enabled: branchId !== null,
  });
  const appointments = rangeQuery.data?.items ?? [];
  const ownVeterinarianId = ownVeterinarianOf(me, search.todos);
  const { schedulesQuery, candidates, vetOptions } = useAgendaSchedules({
    branchId,
    appointments,
    showCancelled,
    agenda: undefined,
    veterinario: search.veterinario,
    ownVeterinarianId,
  });
  // Las agendas del selector: las del filtro de veterinario (o de la vista por defecto); la elegida en la URL, aunque no esté.
  const options = selectColumns(candidates, [], {
    scheduleId: undefined,
    veterinarianId: search.veterinario,
    ownVeterinarianId,
  });
  const chosen =
    search.agenda === undefined
      ? options[0]
      : candidates.find((schedule) => schedule.id === search.agenda);

  if (chosen !== undefined && !options.some((schedule) => schedule.id === chosen.id)) {
    options.unshift(chosen);
  }

  const detail = useQuery({
    ...scheduleDetailQueryOptions(chosen?.id ?? 0),
    enabled: chosen !== undefined,
  });
  const blocks = useQuery({
    ...rangeBlocksQueryOptions(chosen?.id ?? 0, range, paused),
    enabled: chosen !== undefined,
  });

  const grid =
    chosen === undefined
      ? null
      : buildWeekGrid({
          day,
          schedule: {
            schedule: chosen,
            hours: detail.data?.hours ?? null,
            blocks: blocks.data ?? null,
          },
          appointments,
          showCancelled,
          today,
        });
  // Los que se ven en la semana (los de la agenda, de lunes a domingo, sin los cancelados ocultos).
  const visibleCount =
    grid === null ? 0 : grid.columns.reduce((sum, column) => sum + column.items.length, 0);

  // El anuncio, como en el día: al cambiar de semana, de agenda o de filtro; nunca con la actualización periódica.
  const ready = rangeQuery.isSuccess && !rangeQuery.isPlaceholderData && schedulesQuery.isSuccess;
  const announceKey = [
    range.from,
    chosen?.id,
    search.veterinario,
    search.todos,
    showCancelled,
    branchId,
  ].join('|');

  if (ready && announced?.key !== announceKey) {
    setAnnounced({
      key: announceKey,
      text:
        chosen === undefined
          ? `${weekTitle(day)}: ninguna agenda.`
          : `${weekTitle(day)}, ${chosen.name}: ${countText(visibleCount)}.`,
    });
  }

  // La fecha va siempre en la URL (como en el día): un día cualquiera de la semana.
  const goToDay = (next: string) => {
    setSelected(null);
    void navigate({ search: (previous) => ({ ...previous, fecha: next }) });
  };
  const setFilter = (patch: Partial<AgendaSearch>) => {
    setSelected(null);
    void navigate({ search: (previous) => ({ ...previous, ...patch }) });
  };
  const onOpen = (item: GridItem) => {
    openerRef.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setSelected(item);
    window.setTimeout(() => panelRef.current?.focus(), 0);
  };

  let content;

  if (branchId === null) {
    content = null;
  } else if (rangeQuery.isError && rangeQuery.data === undefined) {
    content = (
      <Alert
        title={isApiError(rangeQuery.error) ? userMessage(rangeQuery.error) : MESSAGES.programming}
      >
        <Button
          variant="secondary"
          onClick={() => {
            void rangeQuery.refetch();
          }}
        >
          Probá de nuevo
        </Button>
      </Alert>
    );
  } else if (rangeQuery.data === undefined || schedulesQuery.data === undefined) {
    content = <Loading label="Cargando la semana…" />;
  } else if (grid === null || chosen === undefined) {
    content = (
      <Card>
        <p className="text-secondary mb-0 agenda-empty" ref={emptyRef} tabIndex={-1}>
          {candidates.length === 0
            ? 'La sucursal no tiene agendas activas ni turnos esta semana.'
            : 'Ninguna agenda coincide con los filtros.'}
        </p>
      </Card>
    );
  } else if (asList) {
    content = <WeekList grid={grid} onOpen={onOpen} busy={rangeQuery.isPlaceholderData} />;
  } else {
    content = (
      <AgendaGrid
        grid={grid}
        label={`${weekTitle(day)}, ${chosen.name}`}
        busy={rangeQuery.isPlaceholderData}
        onPrevious={() => {
          goToDay(addDaysToDay(day, -7));
        }}
        onNext={() => {
          goToDay(addDaysToDay(day, 7));
        }}
        onOpen={onOpen}
      />
    );
  }

  const emptyShown = content !== null && grid === null && rangeQuery.data !== undefined;

  useEffect(() => {
    if (emptyShown && keyboardInContent.current && document.activeElement === document.body) {
      emptyRef.current?.focus();
    }
  }, [emptyShown]);

  const onContentKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.defaultPrevented || event.target !== emptyRef.current) {
      return;
    }

    if (event.key === 'PageUp' || event.key === 'PageDown') {
      event.preventDefault();
      goToDay(addDaysToDay(day, event.key === 'PageUp' ? -7 : 7));
    }
  };

  const selectedNow =
    selected === null || grid === null
      ? selected
      : (grid.columns
          .flatMap((column) => column.items)
          .find((item) => item.appointment.id === selected.appointment.id) ?? selected);

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">Agenda</div>
            <h2 className="page-title agenda-title">{weekTitle(day)}</h2>
          </div>
        </div>
      </div>
      <div className="visually-hidden" role="status" aria-live="polite">
        {announced?.text ?? ''}
      </div>
      <Card>
        <div className="row g-2 align-items-end agenda-toolbar">
          <div className="col-12 col-md-auto">
            <AgendaViewSwitch
              current="semana"
              day={day}
              agenda={search.agenda}
              veterinario={search.veterinario}
              extra={{ cancelados: search.cancelados, todos: search.todos }}
            />
          </div>
          <div className="col-12 col-md-auto">
            <div className="btn-group w-100" role="group" aria-label="Semana">
              <Button
                variant="secondary"
                className="agenda-previous"
                aria-label="Semana anterior"
                onClick={() => {
                  goToDay(addDaysToDay(day, -7));
                }}
              >
                <IconChevronLeft size={18} aria-hidden="true" />
              </Button>
              <Button
                variant="secondary"
                className="agenda-today"
                onClick={() => {
                  goToDay(today);
                }}
              >
                Esta semana
              </Button>
              <Button
                variant="secondary"
                className="agenda-next"
                aria-label="Semana siguiente"
                onClick={() => {
                  goToDay(addDaysToDay(day, 7));
                }}
              >
                <IconChevronRight size={18} aria-hidden="true" />
              </Button>
            </div>
          </div>
          <div className="col-12 col-sm-6 col-md-auto agenda-date">
            <DateField
              label="Fecha"
              value={day}
              onChange={(event) => {
                if (isAgendaDay(event.target.value)) {
                  goToDay(event.target.value);
                }
              }}
            />
          </div>
          <div className="col-12 col-sm-6 col-md">
            <SelectField
              label="Agenda"
              value={chosen === undefined ? '' : String(chosen.id)}
              options={
                options.length === 0
                  ? [{ value: '', label: 'Sin agendas' }]
                  : options.map((schedule) => ({
                      value: String(schedule.id),
                      label: scheduleOptionLabel(schedule),
                    }))
              }
              onChange={(event) => {
                setFilter({ agenda: selectedId(event.target.value) });
              }}
            />
          </div>
          <div className="col-12 col-sm-6 col-md">
            <SelectField
              label="Veterinario"
              value={search.veterinario === undefined ? '' : String(search.veterinario)}
              options={vetOptions}
              onChange={(event) => {
                // Otro veterinario: la agenda vuelve a la primera de sus agendas.
                setFilter({ veterinario: selectedId(event.target.value), agenda: undefined });
              }}
            />
          </div>
          <div className="col-12 col-sm-6 col-md-auto">
            <AgendaFlags
              showCancelled={showCancelled}
              todos={search.todos === 1}
              isVeterinarian={me?.veterinarian !== null && me?.veterinarian !== undefined}
              onCancelled={(checked) => {
                void navigate({
                  search: (previous) => ({ ...previous, cancelados: checked ? 1 : undefined }),
                });
              }}
              onTodos={(checked) => {
                setFilter({ todos: checked ? 1 : undefined, agenda: undefined });
              }}
            />
          </div>
        </div>
      </Card>
      <MissingAppointments range={rangeQuery.data} what="de la semana" />
      {selectedNow !== null ? (
        <div className="mt-3">
          <AppointmentPanel
            appointment={selectedNow.appointment}
            inBlock={selectedNow.inBlock}
            headingRef={panelRef}
            onClose={() => {
              setSelected(null);
              openerRef.current?.focus();
            }}
          />
        </div>
      ) : null}
      <div
        className="mt-3"
        onKeyDown={onContentKeyDown}
        onFocus={() => {
          keyboardInContent.current = true;
        }}
        onBlur={(event) => {
          const next = event.relatedTarget;

          if (next !== null && !event.currentTarget.contains(next)) {
            keyboardInContent.current = false;
          }
        }}
      >
        {content}
      </div>
    </>
  );
}

/** ¿El día no tiene horario? (el horario ya llegó y todo el rango está fuera de él). */
function closedAllDay(column: GridColumn, grid: DayGrid): boolean {
  return (
    column.closed.length === 1 &&
    column.closed[0]?.start === grid.start &&
    column.closed[0].end === grid.end
  );
}

/**
 * La semana en el celular (diseño de la F3, "Celular, 375 px"): una lista agrupada por día, con las mismas tarjetas de turno y
 * los bloqueos de cada día, sin la grilla. Cada turno es un botón (Tab los recorre en orden) con su nombre accesible completo.
 */
function WeekList({
  grid,
  onOpen,
  busy,
}: {
  grid: DayGrid;
  onOpen: (item: GridItem) => void;
  busy: boolean;
}) {
  return (
    <div className={`agenda-week-list${busy ? ' agenda-grid-busy' : ''}`} aria-busy={busy}>
      {grid.columns.map((column) => {
        const headingId = `agenda-week-${column.day}`;
        const items = [...column.items].sort(
          (a, b) => a.start - b.start || a.appointment.id - b.appointment.id,
        );

        return (
          <section key={column.key} className="card mb-2" aria-labelledby={headingId}>
            <div className="card-header py-2">
              <h3 className="card-title agenda-week-day" id={headingId}>
                {dayName(column.day)}
                {column.subheading === '' ? '' : ` (${column.subheading.toLowerCase()})`}
              </h3>
            </div>
            <div className="card-body py-2">
              {column.blocks.map((block) => (
                <p key={block.id} className="mb-1 small agenda-week-block">
                  <IconLock size={14} className="me-1" aria-hidden="true" />
                  Bloqueado de {minutesToClock(block.start)} a {minutesToClock(block.end)}
                  {block.reason === null ? '' : `: ${block.reason}`}
                </p>
              ))}
              {items.length === 0 ? (
                <p className="mb-0 text-secondary small">
                  {closedAllDay(column, grid) ? 'Sin atención.' : 'Sin turnos.'}
                </p>
              ) : (
                <ul className="list-unstyled mb-0">
                  {items.map((item) => (
                    <li key={item.appointment.id} className="mb-1">
                      <button
                        type="button"
                        data-key={`a:${String(item.appointment.id)}`}
                        data-status={item.appointment.status.value}
                        className={weekItemClass(item)}
                        aria-label={appointmentLabel(item, column.itemContext)}
                        style={
                          column.schedule.color === null
                            ? undefined
                            : { borderLeftColor: column.schedule.color }
                        }
                        onClick={() => {
                          onOpen(item);
                        }}
                      >
                        <AppointmentSummary appointment={item.appointment} inBlock={item.inBlock} />
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </section>
        );
      })}
    </div>
  );
}

function weekItemClass(item: GridItem): string {
  const status = item.appointment.status.value;

  return [
    'agenda-item',
    'agenda-week-item',
    status === 'no_show' ? 'agenda-item-dimmed' : '',
    status === 'cancelled' ? 'agenda-item-cancelled' : '',
    item.inBlock ? 'agenda-item-in-block' : '',
  ]
    .filter((name) => name !== '')
    .join(' ');
}
