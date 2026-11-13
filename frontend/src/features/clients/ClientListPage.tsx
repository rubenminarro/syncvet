import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useSearch } from '@tanstack/react-router';
import { useEffect, useEffectEvent, useState } from 'react';
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
import { useDebouncedValue } from '../../lib/hooks/use-debounced-value';
import { useCan } from '../auth/permissions';
import { clientListQueryOptions } from './api';
import {
  normalizeSearchText,
  SEARCH_MAX_LENGTH,
  toListFilters,
  type ClientListSearch,
  type KindParam,
} from './search';
import type { Client } from './types';

/** La espera al escribir antes de buscar (diseño de la F1, "Espera al escribir"). */
export const SEARCH_DEBOUNCE_MS = 300;

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
  const [text, setText] = useState(search.buscar ?? '');
  const [urlText, setUrlText] = useState(search.buscar);
  const debounced = useDebouncedValue(text, SEARCH_DEBOUNCE_MS);

  // La URL cambió por otro lado ("Atrás", un enlace): el campo muestra lo de la URL.
  if (search.buscar !== urlText) {
    setUrlText(search.buscar);
    setText(search.buscar ?? '');
  }

  const applySearch = useEffectEvent((value: string) => {
    const next = normalizeSearchText(value);

    if (next === (search.buscar ?? '')) {
      return;
    }

    void navigate({
      search: (previous: ClientListSearch) => ({
        ...previous,
        buscar: next === '' ? undefined : next,
        pagina: undefined,
      }),
      replace: true,
    });
  });

  useEffect(() => {
    applySearch(debounced);
  }, [debounced]);

  const showLink = can(PERMISSIONS.clientsShow);
  const columns: Column<Client>[] = [
    {
      header: 'Nombre',
      cell: (client) =>
        showLink ? (
          <Link to="/clientes/$id" params={{ id: String(client.id) }} className="client-link">
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
        <h2 className="page-title">Clientes</h2>
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
