import { IconPlus } from '@tabler/icons-react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useSearch } from '@tanstack/react-router';
import { ForbiddenPage } from '../../app/ForbiddenPage';
import {
  Button,
  Card,
  CheckboxField,
  DataTable,
  ErrorPage,
  Loading,
  SearchField,
  SelectField,
  type Column,
} from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSearchText } from '../../lib/hooks/use-search-text';
import { useCan } from '../auth/permissions';
import { branchOptionsQueryOptions } from '../branches/api';
import { scheduleListQueryOptions } from './api';
import { formatSlot } from './form';
import { withScheduleList } from './navigation';
import { ScheduleColor, ScheduleInactiveBadge } from './ScheduleBadges';
import { SEARCH_MAX_LENGTH, toScheduleListFilters, type ScheduleListSearch } from './search';
import type { Schedule } from './types';

/**
 * /configuracion/agendas (3.1a, solo el administrador): la búsqueda por nombre, la sucursal, "Incluir las inactivas" y la
 * página van en la URL, como en /configuracion/usuarios. "Nueva agenda" con `schedules.store`.
 */
export function ScheduleListPage() {
  const search = useSearch({ from: '/_sesion/configuracion/agendas/' });
  const navigate = useNavigate({ from: '/configuracion/agendas/' });
  const filters = toScheduleListFilters(search);
  const query = useQuery(scheduleListQueryOptions(filters));
  const branches = useQuery(branchOptionsQueryOptions());
  const can = useCan();

  const [text, setText] = useSearchText(search.buscar, (next) => {
    void navigate({
      search: (previous: ScheduleListSearch) => ({
        ...previous,
        buscar: next === '' ? undefined : next,
        pagina: undefined,
      }),
      replace: true,
    });
  });

  const branchOptions = [
    { value: '', label: 'Todas las sucursales' },
    ...(branches.data ?? []).map((branch) => ({
      value: String(branch.id),
      label: branch.is_active ? branch.name : `${branch.name} (inactivada)`,
    })),
  ];

  // Una sucursal de la URL que la lista todavía no trae (o que no existe): se muestra igual, para no mentir sobre el filtro.
  if (
    filters.branch_id !== null &&
    !branchOptions.some((option) => option.value === String(filters.branch_id))
  ) {
    branchOptions.push({
      value: String(filters.branch_id),
      label: `Sucursal n.º ${String(filters.branch_id)}`,
    });
  }

  const columns: Column<Schedule>[] = [
    {
      header: 'Nombre',
      cell: (schedule) => (
        <span className="d-inline-flex align-items-center gap-2">
          <ScheduleColor color={schedule.color} />
          <Link
            to="/configuracion/agendas/$id"
            params={{ id: String(schedule.id) }}
            state={withScheduleList(search)}
            className="schedule-link"
          >
            {schedule.name}
          </Link>
          <ScheduleInactiveBadge schedule={schedule} />
        </span>
      ),
    },
    { header: 'Sucursal', cell: (schedule) => schedule.branch.name },
    { header: 'Veterinario', cell: (schedule) => schedule.veterinarian?.name ?? '—' },
    { header: 'Servicio', cell: (schedule) => schedule.service?.name ?? '—' },
    {
      header: 'Turno',
      cell: (schedule) => <span className="text-nowrap">{formatSlot(schedule.slot_minutes)}</span>,
    },
  ];

  const filtered = filters.search !== '' || filters.branch_id !== null;
  let content;

  if (query.data !== undefined && !query.isError) {
    content = (
      <DataTable
        columns={columns}
        rows={query.data.items}
        rowKey={(schedule) => schedule.id}
        pagination={query.data.pagination}
        onPageChange={(page) => {
          void navigate({
            search: (previous: ScheduleListSearch) => ({
              ...previous,
              pagina: page === 1 ? undefined : page,
            }),
          });
        }}
        empty={
          filtered ? 'No hay agendas que coincidan con la búsqueda.' : 'Todavía no hay agendas.'
        }
        firstPageLink={
          <Link
            to="/configuracion/agendas"
            search={(previous: ScheduleListSearch) => ({ ...previous, pagina: undefined })}
            className="first-page-link"
          >
            Ir a la página 1
          </Link>
        }
      />
    );
  } else if (query.isError) {
    content =
      isApiError(query.error) && errorTreatment(query.error.errorCode) === 'forbidden' ? (
        <ForbiddenPage message={query.error.message} />
      ) : (
        <ErrorPage
          title="No se pudieron cargar las agendas"
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
    content = <Loading label="Cargando las agendas…" />;
  }

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/configuracion">Configuración</Link>
            </div>
            <h2 className="page-title">Agendas</h2>
          </div>
          {can(PERMISSIONS.schedulesStore) ? (
            <div className="col-auto">
              <Link
                to="/configuracion/agendas/nueva"
                state={withScheduleList(search)}
                className="btn btn-primary schedule-new"
              >
                <IconPlus size={18} className="me-2" aria-hidden="true" />
                Nueva agenda
              </Link>
            </div>
          ) : null}
        </div>
      </div>
      <Card flush>
        <div className="card-body border-bottom">
          <div className="row g-2 align-items-center">
            <SearchField
              className="col-lg-5"
              label="Buscar agendas"
              placeholder="Buscá por nombre"
              value={text}
              onChange={setText}
              maxLength={SEARCH_MAX_LENGTH}
              busy={query.isFetching && query.data !== undefined}
            />
            <SelectField
              className="col-md-6 col-lg-4 schedule-branch-filter"
              label="Sucursal"
              hideLabel
              options={branchOptions}
              value={filters.branch_id === null ? '' : String(filters.branch_id)}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: ScheduleListSearch) => ({
                    ...previous,
                    sucursal: value === '' ? undefined : Number(value),
                    pagina: undefined,
                  }),
                });
              }}
            />
            <div className="col-md-6 col-lg-3 schedule-inactive-filter">
              <CheckboxField
                label="Incluir las inactivas"
                checked={filters.include_inactive}
                onChange={(event) => {
                  const checked = event.target.checked;
                  void navigate({
                    search: (previous: ScheduleListSearch) => ({
                      ...previous,
                      inactivas: checked ? 1 : undefined,
                      pagina: undefined,
                    }),
                  });
                }}
              />
            </div>
          </div>
        </div>
        {content}
      </Card>
    </>
  );
}
