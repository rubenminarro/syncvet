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
  type Column,
} from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSearchText } from '../../lib/hooks/use-search-text';
import { useCan } from '../auth/permissions';
import { branchListQueryOptions } from './api';
import { BranchBadges } from './BranchBadges';
import { withBranchList } from './navigation';
import { SEARCH_MAX_LENGTH, toBranchListFilters, type BranchListSearch } from './search';
import type { Branch } from './types';

/**
 * /configuracion/sucursales (2.1, solo el administrador): la búsqueda en código y nombre, "Incluir las inactivas" y la página
 * van en la URL, como en /clientes.
 */
export function BranchListPage() {
  const search = useSearch({ from: '/_sesion/configuracion/sucursales/' });
  const navigate = useNavigate({ from: '/configuracion/sucursales/' });
  const filters = toBranchListFilters(search);
  const query = useQuery(branchListQueryOptions(filters));
  const can = useCan();

  const [text, setText] = useSearchText(search.buscar, (next) => {
    void navigate({
      search: (previous: BranchListSearch) => ({
        ...previous,
        buscar: next === '' ? undefined : next,
        pagina: undefined,
      }),
      replace: true,
    });
  });

  const columns: Column<Branch>[] = [
    { header: 'Código', cell: (branch) => branch.code },
    {
      header: 'Nombre',
      cell: (branch) => (
        <Link
          to="/configuracion/sucursales/$id"
          params={{ id: String(branch.id) }}
          state={withBranchList(search)}
          className="branch-link"
        >
          {branch.name}
        </Link>
      ),
    },
    { header: 'Establecimiento', cell: (branch) => branch.establishment },
    { header: 'Teléfono', cell: (branch) => branch.phone ?? '' },
    { header: 'Estado', cell: (branch) => <BranchBadges item={branch} feminine /> },
  ];

  let content;

  if (query.data !== undefined && !query.isError) {
    content = (
      <DataTable
        columns={columns}
        rows={query.data.items}
        rowKey={(branch) => branch.id}
        pagination={query.data.pagination}
        onPageChange={(page) => {
          void navigate({
            search: (previous: BranchListSearch) => ({
              ...previous,
              pagina: page === 1 ? undefined : page,
            }),
          });
        }}
        empty={
          filters.search === ''
            ? 'Todavía no hay sucursales.'
            : 'No hay sucursales que coincidan con la búsqueda.'
        }
        firstPageLink={
          <Link
            to="/configuracion/sucursales"
            search={(previous: BranchListSearch) => ({ ...previous, pagina: undefined })}
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
          title="No se pudieron cargar las sucursales"
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
    content = <Loading label="Cargando las sucursales…" />;
  }

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/configuracion">Configuración</Link>
            </div>
            <h2 className="page-title">Sucursales</h2>
          </div>
          {can(PERMISSIONS.branchesStore) ? (
            <div className="col-auto">
              <Link
                to="/configuracion/sucursales/nueva"
                state={withBranchList(search)}
                className="btn btn-primary branch-new"
              >
                <IconPlus size={18} className="me-2" aria-hidden="true" />
                Nueva sucursal
              </Link>
            </div>
          ) : null}
        </div>
      </div>
      <Card flush>
        <div className="card-body border-bottom">
          <div className="row g-2 align-items-center">
            <SearchField
              className="col-md-8"
              label="Buscar sucursales"
              placeholder="Buscá por código o nombre"
              value={text}
              onChange={setText}
              maxLength={SEARCH_MAX_LENGTH}
              busy={query.isFetching && query.data !== undefined}
            />
            <div className="col-md-4 branch-inactive-filter">
              <CheckboxField
                label="Incluir las inactivas"
                checked={filters.include_inactive}
                onChange={(event) => {
                  const checked = event.target.checked;
                  void navigate({
                    search: (previous: BranchListSearch) => ({
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
