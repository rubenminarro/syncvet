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
import { formatGuaranies } from '../../lib/format/money';
import { useSearchText } from '../../lib/hooks/use-search-text';
import { useCan } from '../auth/permissions';
import { serviceListQueryOptions } from './api';
import { formatDuration, formatTaxRate } from './form';
import { withServiceList } from './navigation';
import { SEARCH_MAX_LENGTH, toServiceListFilters, type ServiceListSearch } from './search';
import { ServiceStatusBadge } from './ServiceStatusBadge';
import {
  SERVICE_CATEGORIES,
  SERVICE_CATEGORY_VALUES,
  type Service,
  type ServiceCategory,
} from './types';

const STATUS_OPTIONS = [
  { value: '', label: 'Activos' },
  { value: 'inactivos', label: 'Inactivos' },
  { value: 'todos', label: 'Todos' },
];

const CATEGORY_OPTIONS = [
  { value: '', label: 'Todas las categorías' },
  ...SERVICE_CATEGORIES.map((category) => ({ value: category.value, label: category.label })),
];

/**
 * /servicios (2.4, los tres roles): la búsqueda en el código y el nombre, la categoría, el estado (Activos, Inactivos,
 * Todos) y la página van en la URL, como en /clientes. El precio en guaraníes (`formatGuaranies`, sin pasar por un número de
 * coma flotante); el enlace al detalle con `services.show` y "Nuevo servicio" con `services.store` (el administrador).
 */
export function ServiceListPage() {
  const search = useSearch({ from: '/_sesion/servicios/' });
  const navigate = useNavigate({ from: '/servicios/' });
  const filters = toServiceListFilters(search);
  const query = useQuery(serviceListQueryOptions(filters));
  const can = useCan();
  const canShow = can(PERMISSIONS.servicesShow);

  const [text, setText] = useSearchText(search.buscar, (next) => {
    void navigate({
      search: (previous: ServiceListSearch) => ({
        ...previous,
        buscar: next === '' ? undefined : next,
        pagina: undefined,
      }),
      replace: true,
    });
  });

  const columns: Column<Service>[] = [
    { header: 'Código', cell: (service) => service.code },
    {
      header: 'Nombre',
      cell: (service) =>
        canShow ? (
          <Link
            to="/servicios/$id"
            params={{ id: String(service.id) }}
            state={withServiceList(search)}
            className="service-link"
          >
            {service.name}
          </Link>
        ) : (
          <span className="service-name">{service.name}</span>
        ),
    },
    { header: 'Categoría', cell: (service) => service.category.label },
    { header: 'Duración', cell: (service) => formatDuration(service.duration_minutes) },
    {
      header: 'Precio',
      cell: (service) => (
        <span className="text-nowrap service-price">{formatGuaranies(service.standard_price)}</span>
      ),
    },
    { header: 'IVA', cell: (service) => formatTaxRate(service.tax_rate) },
    { header: 'Estado', cell: (service) => <ServiceStatusBadge service={service} /> },
  ];

  const filtered = filters.search !== '' || filters.category !== null || filters.status !== null;
  let content;

  if (query.data !== undefined && !query.isError) {
    content = (
      <DataTable
        columns={columns}
        rows={query.data.items}
        rowKey={(service) => service.id}
        pagination={query.data.pagination}
        onPageChange={(page) => {
          void navigate({
            search: (previous: ServiceListSearch) => ({
              ...previous,
              pagina: page === 1 ? undefined : page,
            }),
          });
        }}
        empty={
          filtered ? 'No hay servicios que coincidan con la búsqueda.' : 'Todavía no hay servicios.'
        }
        firstPageLink={
          <Link
            to="/servicios"
            search={(previous: ServiceListSearch) => ({ ...previous, pagina: undefined })}
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
          title="No se pudieron cargar los servicios"
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
    content = <Loading label="Cargando los servicios…" />;
  }

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <h2 className="page-title">Servicios</h2>
          </div>
          {can(PERMISSIONS.servicesStore) ? (
            <div className="col-auto">
              <Link
                to="/servicios/nuevo"
                state={withServiceList(search)}
                className="btn btn-primary service-new"
              >
                <IconPlus size={18} className="me-2" aria-hidden="true" />
                Nuevo servicio
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
              label="Buscar servicios"
              placeholder="Buscá por código o nombre"
              value={text}
              onChange={setText}
              maxLength={SEARCH_MAX_LENGTH}
              busy={query.isFetching && query.data !== undefined}
            />
            <SelectField
              className="col-md-6 col-lg-3 service-category-filter"
              label="Categoría"
              hideLabel
              options={CATEGORY_OPTIONS}
              value={search.categoria ?? ''}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: ServiceListSearch) => ({
                    ...previous,
                    categoria: SERVICE_CATEGORY_VALUES.includes(value as ServiceCategory)
                      ? (value as ServiceCategory)
                      : undefined,
                    pagina: undefined,
                  }),
                });
              }}
            />
            <SelectField
              className="col-md-6 col-lg-3 service-status-filter"
              label="Estado"
              hideLabel
              options={STATUS_OPTIONS}
              value={search.estado ?? ''}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: ServiceListSearch) => ({
                    ...previous,
                    estado: value === 'inactivos' || value === 'todos' ? value : undefined,
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
