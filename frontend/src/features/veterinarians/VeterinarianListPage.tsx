import { IconPlus } from '@tabler/icons-react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useSearch } from '@tanstack/react-router';
import { ForbiddenPage } from '../../app/ForbiddenPage';
import {
  Button,
  Card,
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
import { veterinarianListQueryOptions } from './api';
import { withVeterinarianList } from './navigation';
import {
  SEARCH_MAX_LENGTH,
  toVeterinarianListFilters,
  type VeterinarianListSearch,
} from './search';
import type { Veterinarian } from './types';
import { VeterinarianBadges } from './VeterinarianBadges';

const STATUS_OPTIONS = [
  { value: '', label: 'Activos' },
  { value: 'inactivos', label: 'Inactivos' },
  { value: 'todos', label: 'Todos' },
];

/**
 * /veterinarios (2.3, los tres roles): la búsqueda en el código, el nombre y la matrícula, el estado (Activos, Inactivos,
 * Todos), la sucursal y la página van en la URL, como en /clientes. Nunca la comisión ni las notas (la API no las manda en
 * el listado, R-037); el enlace al detalle, solo con `veterinarians.show` (el administrador).
 */
export function VeterinarianListPage() {
  const search = useSearch({ from: '/_sesion/veterinarios/' });
  const navigate = useNavigate({ from: '/veterinarios/' });
  const filters = toVeterinarianListFilters(search);
  const query = useQuery(veterinarianListQueryOptions(filters));
  const branches = useQuery(branchOptionsQueryOptions());
  const can = useCan();
  const canShow = can(PERMISSIONS.veterinariansShow);

  const [text, setText] = useSearchText(search.buscar, (next) => {
    void navigate({
      search: (previous: VeterinarianListSearch) => ({
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

  const columns: Column<Veterinarian>[] = [
    { header: 'Código', cell: (veterinarian) => veterinarian.code },
    {
      header: 'Nombre',
      cell: (veterinarian) =>
        canShow ? (
          <Link
            to="/veterinarios/$id"
            params={{ id: String(veterinarian.id) }}
            state={withVeterinarianList(search)}
            className="veterinarian-link"
          >
            {veterinarian.user.name}
          </Link>
        ) : (
          <span className="veterinarian-name">{veterinarian.user.name}</span>
        ),
    },
    { header: 'Matrícula', cell: (veterinarian) => veterinarian.license_number ?? '' },
    { header: 'Especialidad', cell: (veterinarian) => veterinarian.specialty ?? '' },
    {
      header: 'Sucursales',
      cell: (veterinarian) => veterinarian.branches.map((branch) => branch.code).join(', '),
    },
    {
      header: 'Estado',
      cell: (veterinarian) => <VeterinarianBadges veterinarian={veterinarian} />,
    },
  ];

  const filtered = filters.search !== '' || filters.branch_id !== null;
  let content;

  if (query.data !== undefined && !query.isError) {
    content = (
      <DataTable
        columns={columns}
        rows={query.data.items}
        rowKey={(veterinarian) => veterinarian.id}
        pagination={query.data.pagination}
        onPageChange={(page) => {
          void navigate({
            search: (previous: VeterinarianListSearch) => ({
              ...previous,
              pagina: page === 1 ? undefined : page,
            }),
          });
        }}
        empty={
          filtered || filters.status !== null
            ? 'No hay veterinarios que coincidan con la búsqueda.'
            : 'Todavía no hay veterinarios.'
        }
        firstPageLink={
          <Link
            to="/veterinarios"
            search={(previous: VeterinarianListSearch) => ({ ...previous, pagina: undefined })}
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
          title="No se pudieron cargar los veterinarios"
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
    content = <Loading label="Cargando los veterinarios…" />;
  }

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <h2 className="page-title">Veterinarios</h2>
          </div>
          {can(PERMISSIONS.veterinariansStore) ? (
            <div className="col-auto">
              <Link
                to="/veterinarios/nuevo"
                state={withVeterinarianList(search)}
                className="btn btn-primary veterinarian-new"
              >
                <IconPlus size={18} className="me-2" aria-hidden="true" />
                Nuevo veterinario
              </Link>
            </div>
          ) : null}
        </div>
      </div>
      <Card flush>
        <div className="card-body border-bottom">
          <div className="row g-2 align-items-center">
            <SearchField
              className="col-lg-6"
              label="Buscar veterinarios"
              placeholder="Buscá por código, nombre o matrícula"
              value={text}
              onChange={setText}
              maxLength={SEARCH_MAX_LENGTH}
              busy={query.isFetching && query.data !== undefined}
            />
            <SelectField
              className="col-md-6 col-lg-2 veterinarian-status-filter"
              label="Estado"
              hideLabel
              options={STATUS_OPTIONS}
              value={search.estado ?? ''}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: VeterinarianListSearch) => ({
                    ...previous,
                    estado: value === 'inactivos' || value === 'todos' ? value : undefined,
                    pagina: undefined,
                  }),
                });
              }}
            />
            <SelectField
              className="col-md-6 col-lg-4 veterinarian-branch-filter"
              label="Sucursal"
              hideLabel
              options={branchOptions}
              value={filters.branch_id === null ? '' : String(filters.branch_id)}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: VeterinarianListSearch) => ({
                    ...previous,
                    sucursal: value === '' ? undefined : Number(value),
                    pagina: undefined,
                  }),
                });
              }}
            />
          </div>
        </div>
        {content}
      </Card>
    </>
  );
}
