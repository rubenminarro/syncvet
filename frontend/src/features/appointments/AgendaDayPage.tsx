import { useQueries, useQuery } from '@tanstack/react-query';
import { useNavigate, useSearch } from '@tanstack/react-router';
import { useRef, useState } from 'react';
import { Card, DateField, Loading, SelectField } from '../../components/ui';
import { todayInAppZone } from '../../lib/format/dates';
import { useAutoRefreshPaused } from '../../lib/hooks/auto-refresh';
import { useMediaQuery } from '../../lib/hooks/use-media-query';
import { meQueryOptions } from '../auth/api';
import { scheduleDetailQueryOptions } from '../schedules/api';
import { AgendaGrid } from './AgendaGrid';
import {
  AgendaContent,
  AgendaEmpty,
  AgendaFlags,
  AgendaHeader,
  AgendaStepper,
  AgendaViewSwitch,
  AppointmentPanel,
  MissingAppointments,
  RangeLoadError,
} from './AgendaShared';
import {
  countText,
  ownVeterinarianOf,
  scheduleOptionLabel,
  selectedId,
  SINGLE_COLUMN_QUERY,
  useAgendaSchedules,
  useAppointmentPanel,
  useCreateFromCell,
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
  const today = todayInAppZone();
  const day = search.fecha ?? today;
  const showCancelled = search.cancelados === 1;
  const paused = useAutoRefreshPaused();
  const singleColumn = useMediaQuery(SINGLE_COLUMN_QUERY);
  const [mobileScheduleId, setMobileScheduleId] = useState<number | null>(null);
  const panel = useAppointmentPanel<GridItem>();
  const createFromCell = useCreateFromCell();
  const emptyRef = useRef<HTMLParagraphElement>(null);
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
    panel.setSelected(null);
    void navigate({ search: (previous) => ({ ...previous, fecha: next }) });
  };
  const step = (direction: -1 | 1) => {
    goToDay(addDaysToDay(day, direction));
  };
  const setFilter = (patch: Partial<AgendaSearch>) => {
    panel.setSelected(null);
    void navigate({ search: (previous) => ({ ...previous, ...patch }) });
  };

  let content;

  if (branchId === null) {
    // El layout ya pide elegir la sucursal actual (pregunta 2).
    content = null;
  } else if (rangeQuery.isError && rangeQuery.data === undefined) {
    content = (
      <RangeLoadError
        error={rangeQuery.error}
        onRetry={() => {
          void rangeQuery.refetch();
        }}
      />
    );
  } else if (rangeQuery.data === undefined || schedulesQuery.data === undefined) {
    content = <Loading label="Cargando la agenda…" />;
  } else if (filtered.length === 0) {
    content = (
      <AgendaEmpty
        emptyRef={emptyRef}
        text={
          candidates.length === 0
            ? 'La sucursal no tiene agendas activas ni turnos este día.'
            : 'Ninguna agenda coincide con los filtros.'
        }
      />
    );
  } else {
    content = (
      <AgendaGrid
        grid={grid}
        label={`Agenda del ${dayTitle(day).toLowerCase()}`}
        busy={rangeQuery.isPlaceholderData}
        onPrevious={() => {
          step(-1);
        }}
        onNext={() => {
          step(1);
        }}
        onOpen={panel.open}
        onCreate={createFromCell}
      />
    );
  }

  const emptyShown = content !== null && filtered.length === 0 && rangeQuery.data !== undefined;
  const selectedNow =
    panel.selected === null
      ? null
      : (grid.columns
          .flatMap((column) => column.items)
          .find((item) => item.appointment.id === panel.selected?.appointment.id) ??
        panel.selected);

  return (
    <>
      <AgendaHeader
        title={dayTitle(day)}
        announcement={announced?.text ?? ''}
        newAppointment={{ agenda: search.agenda }}
      />
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
            <AgendaStepper
              label="Día"
              previousLabel="Día anterior"
              nextLabel="Día siguiente"
              todayLabel="Hoy"
              onStep={step}
              onToday={() => {
                goToDay(today);
              }}
            />
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
          <div className="col-12 col-sm-6 col-md agenda-filter">
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
          <div className="col-12 col-sm-6 col-md agenda-filter">
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
            headingRef={panel.headingRef}
            onClose={panel.close}
          />
        </div>
      ) : null}
      <AgendaContent emptyShown={emptyShown} emptyRef={emptyRef} onStep={step}>
        {content}
      </AgendaContent>
    </>
  );
}
