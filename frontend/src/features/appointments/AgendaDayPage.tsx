import { IconChevronLeft, IconChevronRight } from '@tabler/icons-react';
import { useQueries, useQuery } from '@tanstack/react-query';
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
import { addDaysToDay, dayName, dayTitle, isAgendaDay } from './dates';
import { buildDayGrid, isVisible, type ColumnSchedule, type GridItem } from './grid';
import type { AgendaSearch } from './search';

export { MISSING_APPOINTMENTS_MESSAGE, SINGLE_COLUMN_QUERY } from './agenda-shared';

/**
 * /agenda (3.2): la agenda del día de la sucursal actual de la barra (pregunta 2), con una columna por agenda activa (las
 * filtradas) y, para el veterinario, su vista por defecto (pregunta 3). Un pedido de los turnos del día (hasta 500, con el
 * aviso de los que faltan) más el detalle y los bloqueos de cada agenda que se ve; los filtros, en el navegador (H2). Se
 * actualiza cada 60 s con la pestaña visible y sin un diálogo abierto (pregunta 9). En la 3.2, solo lectura: un turno abre
 * un panel con sus datos (el detalle, desde la 3.4).
 */
export function AgendaDayPage() {
  const search: AgendaSearch = useSearch({ from: '/_sesion/agenda/' });
  const navigate = useNavigate({ from: '/agenda/' });
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const branchId = me?.current_branch?.id ?? null;
  const day = search.fecha ?? todayInAppZone();
  const showCancelled = search.cancelados === 1;
  const paused = useAutoRefreshPaused();
  const singleColumn = useMediaQuery(SINGLE_COLUMN_QUERY);
  const [mobileScheduleId, setMobileScheduleId] = useState<number | null>(null);
  const [selected, setSelected] = useState<GridItem | null>(null);
  const panelRef = useRef<HTMLHeadingElement>(null);
  const emptyRef = useRef<HTMLParagraphElement>(null);
  // ¿El foco estaba en la grilla (o en el aviso del día sin agendas)? Para no perderlo al cambiar de día con el teclado.
  const keyboardInContent = useRef(false);
  const openerRef = useRef<HTMLElement | null>(null);
  const [announced, setAnnounced] = useState<{ key: string; text: string } | null>(null);

  const rangeQuery = useQuery({
    ...appointmentRangeQueryOptions(branchId ?? 0, { from: day, to: day }, paused),
    enabled: branchId !== null,
  });

  const appointments = rangeQuery.data?.items ?? [];
  const { schedulesQuery, candidates, filtered, vetOptions } = useAgendaSchedules({
    branchId,
    appointments,
    showCancelled,
    agenda: search.agenda,
    veterinario: search.veterinario,
    ownVeterinarianId: ownVeterinarianOf(me, search.todos),
  });
  const shown =
    singleColumn && filtered.length > 1
      ? [filtered.find((schedule) => schedule.id === mobileScheduleId) ?? filtered[0]].filter(
          (schedule): schedule is ColumnSchedule => schedule !== undefined,
        )
      : filtered;

  const details = useQueries({
    queries: shown.map((schedule) => scheduleDetailQueryOptions(schedule.id)),
  });
  const blocks = useQueries({
    queries: shown.map((schedule) =>
      rangeBlocksQueryOptions(schedule.id, { from: day, to: day }, paused),
    ),
  });

  const shownIds = new Set(shown.map((schedule) => schedule.id));
  const grid = buildDayGrid({
    day,
    schedules: shown.map((schedule, index) => ({
      schedule,
      hours: details[index]?.data?.hours ?? null,
      blocks: blocks[index]?.data ?? null,
    })),
    appointments: appointments.filter((appointment) => shownIds.has(appointment.schedule.id)),
    showCancelled,
  });
  const filteredIds = new Set(filtered.map((schedule) => schedule.id));
  const visibleCount = appointments.filter(
    (appointment) =>
      filteredIds.has(appointment.schedule.id) && isVisible(appointment, showCancelled),
  ).length;

  // El anuncio (role="status"): al cambiar de día o de filtro, cuando llega lo pedido; la actualización periódica no
  // cambia la clave y no anuncia nada (no interrumpe al lector de pantalla).
  const ready = rangeQuery.isSuccess && !rangeQuery.isPlaceholderData;
  const announceKey = [
    day,
    search.agenda,
    search.veterinario,
    search.todos,
    showCancelled,
    branchId,
  ].join('|');

  if (ready && announced?.key !== announceKey) {
    setAnnounced({ key: announceKey, text: `${dayName(day)}: ${countText(visibleCount)}.` });
  }

  // La fecha va siempre en la URL, también la de hoy ("Hoy"): un enlace copiado muestra el mismo día al otro día.
  const goToDay = (next: string) => {
    setSelected(null);
    void navigate({ search: (previous) => ({ ...previous, fecha: next }) });
  };
  const today = todayInAppZone();

  let content;

  if (branchId === null) {
    // El layout ya pide elegir la sucursal actual (pregunta 2).
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
    content = <Loading label="Cargando la agenda…" />;
  } else if (filtered.length === 0) {
    content = (
      <Card>
        <p className="text-secondary mb-0 agenda-empty" ref={emptyRef} tabIndex={-1}>
          {candidates.length === 0
            ? 'La sucursal no tiene agendas activas ni turnos este día.'
            : 'Ninguna agenda coincide con los filtros.'}
        </p>
      </Card>
    );
  } else {
    content = (
      <AgendaGrid
        grid={grid}
        label={`Agenda del ${dayTitle(day).toLowerCase()}`}
        busy={rangeQuery.isPlaceholderData}
        onPrevious={() => {
          goToDay(addDaysToDay(day, -1));
        }}
        onNext={() => {
          goToDay(addDaysToDay(day, 1));
        }}
        onOpen={(item) => {
          openerRef.current =
            document.activeElement instanceof HTMLElement ? document.activeElement : null;
          setSelected(item);
          window.setTimeout(() => panelRef.current?.focus(), 0);
        }}
      />
    );
  }

  // Un día sin agendas que mostrar no tiene grilla: si el foco venía de ella (Av Pág), pasa al aviso, que sigue
  // respondiendo a Re Pág y Av Pág; nunca se pierde en el body.
  const emptyShown = content !== null && filtered.length === 0 && rangeQuery.data !== undefined;

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
      goToDay(addDaysToDay(day, event.key === 'PageUp' ? -1 : 1));
    }
  };

  const selectedNow =
    selected === null
      ? null
      : (grid.columns
          .flatMap((column) => column.items)
          .find((item) => item.appointment.id === selected.appointment.id) ?? selected);

  const setFilter = (patch: Partial<AgendaSearch>) => {
    setSelected(null);
    void navigate({ search: (previous) => ({ ...previous, ...patch }) });
  };

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">Agenda</div>
            <h2 className="page-title agenda-title">{dayTitle(day)}</h2>
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
              current="dia"
              day={day}
              agenda={search.agenda}
              veterinario={search.veterinario}
              extra={{ cancelados: search.cancelados, todos: search.todos }}
            />
          </div>
          <div className="col-12 col-md-auto">
            <div className="btn-group w-100" role="group" aria-label="Día">
              <Button
                variant="secondary"
                className="agenda-previous"
                aria-label="Día anterior"
                onClick={() => {
                  goToDay(addDaysToDay(day, -1));
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
                Hoy
              </Button>
              <Button
                variant="secondary"
                className="agenda-next"
                aria-label="Día siguiente"
                onClick={() => {
                  goToDay(addDaysToDay(day, 1));
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
              value={search.agenda === undefined ? '' : String(search.agenda)}
              options={[
                { value: '', label: 'Todas' },
                ...candidates.map((schedule) => ({
                  value: String(schedule.id),
                  label: scheduleOptionLabel(schedule),
                })),
              ]}
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
                setFilter({ veterinario: selectedId(event.target.value) });
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
                setFilter({ todos: checked ? 1 : undefined });
              }}
            />
          </div>
        </div>
        {singleColumn && filtered.length > 1 ? (
          <SelectField
            label="Agenda que se ve"
            className="agenda-single"
            value={String(shown[0]?.id ?? '')}
            options={filtered.map((schedule) => ({
              value: String(schedule.id),
              label: scheduleOptionLabel(schedule),
            }))}
            onChange={(event) => {
              setMobileScheduleId(Number(event.target.value));
            }}
          />
        ) : null}
      </Card>
      <MissingAppointments range={rangeQuery.data} what="del día" />
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
