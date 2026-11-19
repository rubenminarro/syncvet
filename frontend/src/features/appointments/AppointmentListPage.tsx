import { IconClock } from '@tabler/icons-react';
import { useQueries, useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useSearch } from '@tanstack/react-router';
import { useState, type ReactNode, type SyntheticEvent } from 'react';
import { ForbiddenPage } from '../../app/ForbiddenPage';
import {
  Button,
  Card,
  CheckboxGroupField,
  DataTable,
  DateField,
  ErrorPage,
  Loading,
  PaginationBar,
  SearchField,
  SelectField,
  type Column,
} from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { formatDate, formatTime, todayInAppZone } from '../../lib/format/dates';
import { useAutoRefreshPaused } from '../../lib/hooks/auto-refresh';
import { useMediaQuery } from '../../lib/hooks/use-media-query';
import { useSearchText } from '../../lib/hooks/use-search-text';
import { SEARCH_MAX_LENGTH } from '../../lib/search-text';
import { meQueryOptions } from '../auth/api';
import {
  AgendaHeader,
  AgendaViewSwitch,
  AppointmentFlags,
  AppointmentPanel,
  AppointmentSummary,
} from './AgendaShared';
import {
  appointmentItemClass,
  countText,
  useAppointmentPanel,
  scheduleOptionLabel,
  selectedId,
  SINGLE_COLUMN_QUERY,
  useAgendaSchedules,
} from './agenda-shared';
import { appointmentListQueryOptions, rangeBlocksQueryOptions } from './api';
import { dayName, isAgendaDay } from './dates';
import { appointmentWho, isInBlock } from './grid';
import {
  listRangeError,
  STATUS_LABELS,
  STATUS_PARAMS,
  toAppointmentListFilters,
  type AppointmentListSearch,
  type StatusParam,
} from './search';
import { STATUS_ICONS } from './status-icons';
import type { Appointment } from './types';

export { SEARCH_DEBOUNCE_MS } from '../../lib/hooks/use-search-text';

const STATUS_OPTIONS = STATUS_PARAMS.map((value) => ({ value, label: STATUS_LABELS[value] }));

/** "Martes 17 de noviembre" o "Del lunes 16 de noviembre al domingo 22 de noviembre" (el anuncio). */
function rangeText(from: string, to: string): string {
  return from === to
    ? dayName(from)
    : `Del ${dayName(from).toLowerCase()} al ${dayName(to).toLowerCase()}`;
}

/**
 * /turnos (3.3): la lista de los turnos de la sucursal actual de la barra (pregunta 2), con los filtros de la API (H2): desde y
 * hasta (fechas solas; sin ninguna, hoy; hasta 31 días, con el error antes de pedir), agenda, veterinario, estados (como
 * `status[]`, H5) y la búsqueda (300 ms, `useSearchText`), de a 20 por página, todo en la URL (el modelo de /clientes). Trae
 * todos los estados si no se filtra, también los cancelados (pregunta 5: la lista los muestra); los ausentes, atenuados. Marca
 * "Dentro de un bloqueo" con los bloqueos del rango de cada agenda de la página. Se actualiza como la agenda (cada 60 s, con la
 * pausa). Un turno abre el panel de solo lectura de la 3.2 (el detalle, desde la 3.4). En el celular, tarjetas.
 */
export function AppointmentListPage() {
  const search: AppointmentListSearch = useSearch({ from: '/_sesion/turnos/' });
  const navigate = useNavigate({ from: '/turnos/' });
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const branchId = me?.current_branch?.id ?? null;
  const paused = useAutoRefreshPaused();
  const asCards = useMediaQuery(SINGLE_COLUMN_QUERY);
  const filters = toAppointmentListFilters(search);
  const today = todayInAppZone();
  // Lo que la API toma como el rango: sin fechas, hoy; con una sola, ese día.
  const from = search.desde ?? search.hasta ?? today;
  const to = search.hasta ?? search.desde ?? today;
  const panel = useAppointmentPanel<{ appointment: Appointment; inBlock: boolean }>();
  const { selected, setSelected } = panel;
  const [announced, setAnnounced] = useState<{ key: string; text: string } | null>(null);

  const query = useQuery({
    ...appointmentListQueryOptions(branchId ?? 0, filters, paused),
    enabled: branchId !== null,
  });
  const items = query.data?.items ?? [];
  const { candidates, vetOptions } = useAgendaSchedules({
    branchId,
    appointments: [],
    showCancelled: true,
    agenda: undefined,
    veterinario: undefined,
    ownVeterinarianId: null,
  });

  // Los bloqueos del rango de cada agenda de la página (en caché): la marca "Dentro de un bloqueo".
  const scheduleIds = [...new Set(items.map((appointment) => appointment.schedule.id))];
  const blocks = useQueries({
    queries: scheduleIds.map((id) => rangeBlocksQueryOptions(id, { from, to }, paused)),
  });
  const blocksBySchedule = new Map(scheduleIds.map((id, index) => [id, blocks[index]?.data ?? []]));
  const inBlock = (appointment: Appointment) =>
    isInBlock(appointment, blocksBySchedule.get(appointment.schedule.id) ?? []);

  const [text, setText] = useSearchText(search.buscar, (next) => {
    void navigate({
      search: (previous: AppointmentListSearch) => ({
        ...previous,
        buscar: next === '' ? undefined : next,
        pagina: undefined,
      }),
      replace: true,
    });
  });

  const setFilter = (patch: Partial<AppointmentListSearch>) => {
    setSelected(null);
    void navigate({
      search: (previous: AppointmentListSearch) => ({ ...previous, ...patch, pagina: undefined }),
    });
  };
  const goToPage = (page: number) => {
    setSelected(null);
    void navigate({
      search: (previous: AppointmentListSearch) => ({
        ...previous,
        pagina: page === 1 ? undefined : page,
      }),
    });
  };
  const open = (appointment: Appointment) => {
    panel.open({ appointment, inBlock: inBlock(appointment) });
  };

  // El anuncio, como en la agenda: al cambiar de filtro o de página, cuando llega lo pedido; nunca con la actualización.
  const ready = query.isSuccess && !query.isPlaceholderData;
  const announceKey = JSON.stringify([filters, branchId]);

  if (ready && announced?.key !== announceKey) {
    setAnnounced({
      key: announceKey,
      text: `${rangeText(from, to)}: ${countText(query.data.pagination.total)}.`,
    });
  }

  const columns: Column<Appointment>[] = [
    {
      header: 'Fecha y hora',
      cell: (appointment) => (
        <button
          type="button"
          className="btn btn-link p-0 text-start appointment-open"
          onClick={() => {
            open(appointment);
          }}
        >
          {formatDate(appointment.starts_at)} {formatTime(appointment.starts_at)}–
          {formatTime(appointment.ends_at)}
        </button>
      ),
    },
    { header: 'Agenda', cell: (appointment) => appointment.schedule.name },
    { header: 'Paciente o contacto', cell: (appointment) => appointmentWho(appointment) },
    { header: 'Servicio', cell: (appointment) => appointment.service?.name ?? '' },
    {
      header: 'Estado',
      cell: (appointment) => {
        const StatusIcon = STATUS_ICONS[appointment.status.value] ?? IconClock;

        return (
          <span className="text-nowrap">
            <StatusIcon size={16} className="me-1" aria-hidden="true" />
            {appointment.status.label}
          </span>
        );
      },
    },
    {
      header: 'Marcas',
      cell: (appointment) => (
        <AppointmentFlags appointment={appointment} inBlock={inBlock(appointment)} />
      ),
    },
  ];

  const emptyText =
    filters.search === '' &&
    filters.status.length === 0 &&
    filters.schedule_id === null &&
    filters.veterinarian_id === null
      ? 'No hay turnos en estas fechas.'
      : 'No hay turnos que coincidan con los filtros.';
  const firstPageLink = (
    <Link
      to="/turnos"
      search={(previous: AppointmentListSearch) => ({ ...previous, pagina: undefined })}
      className="first-page-link"
    >
      Ir a la página 1
    </Link>
  );

  let content;

  if (branchId === null) {
    // El layout ya pide elegir la sucursal actual (pregunta 2).
    content = null;
  } else if (query.data !== undefined && !query.isError) {
    content = asCards ? (
      <AppointmentCards
        rows={items}
        inBlock={inBlock}
        onOpen={open}
        empty={emptyText}
        pagination={query.data.pagination}
        onPageChange={goToPage}
        firstPageLink={firstPageLink}
      />
    ) : (
      <DataTable
        columns={columns}
        rows={items}
        rowKey={(appointment) => appointment.id}
        rowClassName={(appointment) =>
          appointment.status.value === 'no_show' ? 'appointment-row-dimmed' : undefined
        }
        pagination={query.data.pagination}
        onPageChange={goToPage}
        empty={emptyText}
        firstPageLink={firstPageLink}
      />
    );
  } else if (query.isError) {
    content =
      isApiError(query.error) && errorTreatment(query.error.errorCode) === 'forbidden' ? (
        <ForbiddenPage message={query.error.message} />
      ) : (
        <ErrorPage
          title="No se pudieron cargar los turnos"
          message={isApiError(query.error) ? userMessage(query.error) : MESSAGES.programming}
          action={
            <Button
              onClick={() => {
                void query.refetch();
              }}
            >
              Probá de nuevo
            </Button>
          }
        />
      );
  } else {
    content = <Loading label="Cargando los turnos…" />;
  }

  return (
    <>
      <AgendaHeader
        title="Turnos"
        titleClassName=""
        announcement={announced?.text ?? ''}
        newAppointment={{ agenda: search.agenda }}
      />
      <Card flush>
        <div className="card-body border-bottom">
          <div className="mb-3 appointment-list-views">
            <AgendaViewSwitch
              current="lista"
              day={from}
              agenda={search.agenda}
              veterinario={search.veterinario}
            />
          </div>
          <RangeFilter
            key={`${search.desde ?? ''}|${search.hasta ?? ''}`}
            desde={search.desde ?? (search.hasta === undefined ? today : undefined)}
            hasta={search.hasta ?? (search.desde === undefined ? today : undefined)}
            onApply={(desde, hasta) => {
              setFilter({ desde, hasta });
            }}
          />
          <div className="row g-2">
            <div className="col-12 col-md-6">
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
            <div className="col-12 col-md-6">
              <SelectField
                label="Veterinario"
                value={search.veterinario === undefined ? '' : String(search.veterinario)}
                options={vetOptions}
                onChange={(event) => {
                  setFilter({ veterinario: selectedId(event.target.value) });
                }}
              />
            </div>
          </div>
          <CheckboxGroupField<StatusParam>
            label="Estados"
            hint="Sin marcar ninguno, todos (también los cancelados)."
            className="appointment-statuses"
            options={STATUS_OPTIONS}
            value={search.estados ?? []}
            onChange={(next) => {
              // La casilla que cambió se aplica sobre la búsqueda más reciente (no sobre la de este render): dos clics
              // seguidos, antes de que llegue la navegación del primero, no pierden ninguno.
              const current = new Set(search.estados ?? []);
              const changed = STATUS_PARAMS.find(
                (value) => current.has(value) !== next.includes(value),
              );

              if (changed === undefined) {
                return;
              }

              setSelected(null);
              void navigate({
                search: (previous: AppointmentListSearch) => {
                  const marked = new Set(previous.estados ?? []);

                  if (marked.has(changed)) {
                    marked.delete(changed);
                  } else {
                    marked.add(changed);
                  }

                  const estados = STATUS_PARAMS.filter((value) => marked.has(value));

                  return {
                    ...previous,
                    estados: estados.length === 0 ? undefined : estados,
                    pagina: undefined,
                  };
                },
              });
            }}
          />
          <SearchField
            label="Buscar turnos"
            placeholder="Buscá por paciente, cliente, documento o contacto"
            value={text}
            onChange={setText}
            maxLength={SEARCH_MAX_LENGTH}
            busy={query.isFetching && query.data !== undefined}
          />
        </div>
        {selected !== null ? (
          <div className="card-body border-bottom">
            <AppointmentPanel
              appointment={selected.appointment}
              inBlock={selected.inBlock}
              headingRef={panel.headingRef}
              onClose={panel.close}
            />
          </div>
        ) : null}
        {content}
      </Card>
    </>
  );
}

/**
 * Desde y hasta (fechas solas de Asunción), aplicadas con "Filtrar" (una fecha a medio elegir no es un filtro): las reglas de la
 * API con sus mensajes, en el campo, con el foco en él y sin cambiar la URL ni pedir nada si hay un error.
 */
function RangeFilter({
  desde,
  hasta,
  onApply,
}: {
  desde: string | undefined;
  hasta: string | undefined;
  onApply: (desde: string | undefined, hasta: string | undefined) => void;
}) {
  const [from, setFrom] = useState(desde ?? '');
  const [to, setTo] = useState(hasta ?? '');
  const [error, setError] = useState<string | undefined>(undefined);

  const submit = (event: SyntheticEvent<HTMLFormElement>) => {
    event.preventDefault();
    const fromValue = isAgendaDay(from) ? from : undefined;
    const toValue = isAgendaDay(to) ? to : undefined;
    const rangeError = listRangeError(fromValue, toValue);
    setError(rangeError ?? undefined);

    if (rangeError !== null) {
      const field = event.currentTarget.elements.namedItem('hasta');

      if (field instanceof HTMLInputElement) {
        field.focus();
      }

      return;
    }

    onApply(fromValue, toValue);
  };

  return (
    <form className="row g-2 align-items-start appointment-range" noValidate onSubmit={submit}>
      <div className="col-6 col-md-3">
        <DateField
          name="desde"
          label="Desde"
          value={from}
          onChange={(event) => {
            setFrom(event.target.value);
          }}
        />
      </div>
      <div className="col-6 col-md-3">
        <DateField
          name="hasta"
          label="Hasta"
          value={to}
          error={error}
          onChange={(event) => {
            setTo(event.target.value);
          }}
        />
      </div>
      <div className="col-12 col-md-3 appointment-range-submit">
        <Button type="submit" variant="secondary">
          Filtrar
        </Button>
      </div>
    </form>
  );
}

/** La lista en el celular: una tarjeta por turno (sin la tabla, que no entra en 375 px), con la misma paginación. */
function AppointmentCards({
  rows,
  inBlock,
  onOpen,
  empty,
  pagination,
  onPageChange,
  firstPageLink,
}: {
  rows: readonly Appointment[];
  inBlock: (appointment: Appointment) => boolean;
  onOpen: (appointment: Appointment) => void;
  empty: string;
  pagination: Parameters<typeof PaginationBar>[0]['pagination'];
  onPageChange: (page: number) => void;
  firstPageLink: ReactNode;
}) {
  const outOfRange = rows.length === 0 && pagination.current_page > pagination.last_page;

  return (
    <>
      <div className="card-body">
        {rows.length === 0 ? (
          <p className="text-secondary text-center mb-0">
            {outOfRange ? (
              <span className="page-out-of-range">Esta página no existe. {firstPageLink}</span>
            ) : (
              empty
            )}
          </p>
        ) : (
          <ul className="list-unstyled mb-0 appointment-cards">
            {rows.map((appointment) => {
              return (
                <li key={appointment.id} className="mb-2">
                  <button
                    type="button"
                    className={appointmentItemClass(
                      appointment,
                      inBlock(appointment),
                      'agenda-week-item',
                      'appointment-card',
                    )}
                    data-status={appointment.status.value}
                    onClick={() => {
                      onOpen(appointment);
                    }}
                  >
                    <span className="agenda-item-line small text-secondary">
                      {formatDate(appointment.starts_at)} · {appointment.schedule.name}
                    </span>
                    <AppointmentSummary appointment={appointment} inBlock={inBlock(appointment)} />
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </div>
      <div className="card-footer">
        <PaginationBar pagination={pagination} onPageChange={onPageChange} />
      </div>
    </>
  );
}
