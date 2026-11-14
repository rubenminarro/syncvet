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
import { clientListQueryOptions } from './api';
import { withClientList } from './navigation';
import { SEARCH_MAX_LENGTH, toListFilters, type ClientListSearch, type KindParam } from './search';
import type { Client } from './types';

export { SEARCH_DEBOUNCE_MS } from '../../lib/hooks/use-search-text';

const KIND_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'persona', label: 'Persona' },
  { value: 'empresa', label: 'Empresa' },
] as const;

function isKindParam(value: string): value is KindParam {
  return value === 'persona' || value === 'empresa';
}

/** /clientes: la búsqueda, el tipo y la página van en la URL; recargar o volver atrás muestra lo mismo. */
export function ClientListPage() {
  const search = useSearch({ from: '/_sesion/clientes/' });
  const navigate = useNavigate({ from: '/clientes/' });
  const filters = toListFilters(search);
  const query = useQuery(clientListQueryOptions(filters));
  const can = useCan();

  // El campo es local; la URL cambia 300 ms después de la última tecla, sin llenar el historial y de vuelta en la página 1.
  const [text, setText] = useSearchText(search.buscar, (next) => {
    void navigate({
      search: (previous: ClientListSearch) => ({
        ...previous,
        buscar: next === '' ? undefined : next,
        pagina: undefined,
      }),
      replace: true,
    });
  });

  const showLink = can(PERMISSIONS.clientsShow);
  const columns: Column<Client>[] = [
    {
      header: 'Nombre',
      cell: (client) =>
        showLink ? (
          <Link
            to="/clientes/$id"
            params={{ id: String(client.id) }}
            state={withClientList(search)}
            className="client-link"
          >
            {client.display_name}
          </Link>
        ) : (
          client.display_name
        ),
    },
    { header: 'Tipo', cell: (client) => client.kind.label },
    { header: 'Documento', cell: (client) => client.document_number },
    { header: 'Teléfono', cell: (client) => client.phone ?? '' },
    { header: 'Email', cell: (client) => client.email ?? '' },
  ];

  let content;

  if (query.data !== undefined && !query.isError) {
    content = (
      <DataTable
        columns={columns}
        rows={query.data.items}
        rowKey={(client) => client.id}
        pagination={query.data.pagination}
        onPageChange={(page) => {
          void navigate({
            search: (previous: ClientListSearch) => ({
              ...previous,
              pagina: page === 1 ? undefined : page,
            }),
          });
        }}
        empty={
          filters.search === '' && filters.kind === null
            ? 'Todavía no hay clientes.'
            : 'No hay clientes que coincidan con la búsqueda.'
        }
        firstPageLink={
          <Link
            to="/clientes"
            search={(previous: ClientListSearch) => ({ ...previous, pagina: undefined })}
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
          title="No se pudieron cargar los clientes"
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
    content = <Loading label="Cargando los clientes…" />;
  }

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <h2 className="page-title">Clientes</h2>
          </div>
          {can(PERMISSIONS.clientsStore) ? (
            <div className="col-auto">
              <Link
                to="/clientes/nuevo"
                state={withClientList(search)}
                className="btn btn-primary client-new"
              >
                <IconPlus size={18} className="me-2" aria-hidden="true" />
                Nuevo cliente
              </Link>
            </div>
          ) : null}
        </div>
      </div>
      <Card flush>
        <div className="card-body border-bottom">
          <div className="row g-2">
            <SearchField
              className="col-md-8"
              label="Buscar clientes"
              placeholder="Buscá por nombre, documento, teléfono, email o mascota"
              value={text}
              onChange={setText}
              maxLength={SEARCH_MAX_LENGTH}
              busy={query.isFetching && query.data !== undefined}
            />
            <SelectField
              className="col-md-4"
              label="Tipo de cliente"
              hideLabel
              options={KIND_OPTIONS}
              value={search.tipo ?? ''}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: ClientListSearch) => ({
                    ...previous,
                    tipo: isKindParam(value) ? value : undefined,
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
