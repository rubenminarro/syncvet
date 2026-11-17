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
import { rolesQueryOptions, userListQueryOptions } from './api';
import { withUserList } from './navigation';
import { isRoleName, SEARCH_MAX_LENGTH, toUserListFilters, type UserListSearch } from './search';
import type { User } from './types';
import { UserInactiveBadge } from './UserInactiveBadge';

/**
 * /configuracion/usuarios (2.2a, solo el administrador): la búsqueda en nombre, apellido y email, el rol, la sucursal,
 * "Incluir los inactivos" y la página van en la URL, como en /clientes.
 */
export function UserListPage() {
  const search = useSearch({ from: '/_sesion/configuracion/usuarios/' });
  const navigate = useNavigate({ from: '/configuracion/usuarios/' });
  const filters = toUserListFilters(search);
  const query = useQuery(userListQueryOptions(filters));
  const roles = useQuery(rolesQueryOptions());
  const branches = useQuery(branchOptionsQueryOptions());
  const can = useCan();

  const [text, setText] = useSearchText(search.buscar, (next) => {
    void navigate({
      search: (previous: UserListSearch) => ({
        ...previous,
        buscar: next === '' ? undefined : next,
        pagina: undefined,
      }),
      replace: true,
    });
  });

  const roleOptions = [
    { value: '', label: 'Todos los roles' },
    ...(roles.data ?? []).map((role) => ({ value: role.name, label: role.label })),
  ];
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

  const columns: Column<User>[] = [
    {
      header: 'Nombre',
      cell: (user) => (
        <Link
          to="/configuracion/usuarios/$id"
          params={{ id: String(user.id) }}
          state={withUserList(search)}
          className="user-link"
        >
          {user.last_name}, {user.first_name}
        </Link>
      ),
    },
    { header: 'Email', cell: (user) => user.email },
    { header: 'Roles', cell: (user) => user.roles.map((role) => role.label).join(', ') },
    {
      header: 'Sucursales',
      cell: (user) => user.branches.map((branch) => branch.code).join(', '),
    },
    { header: 'Estado', cell: (user) => <UserInactiveBadge user={user} /> },
  ];

  const filtered = filters.search !== '' || filters.role !== null || filters.branch_id !== null;
  let content;

  if (query.data !== undefined && !query.isError) {
    content = (
      <DataTable
        columns={columns}
        rows={query.data.items}
        rowKey={(user) => user.id}
        pagination={query.data.pagination}
        onPageChange={(page) => {
          void navigate({
            search: (previous: UserListSearch) => ({
              ...previous,
              pagina: page === 1 ? undefined : page,
            }),
          });
        }}
        empty={
          filtered ? 'No hay usuarios que coincidan con la búsqueda.' : 'Todavía no hay usuarios.'
        }
        firstPageLink={
          <Link
            to="/configuracion/usuarios"
            search={(previous: UserListSearch) => ({ ...previous, pagina: undefined })}
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
          title="No se pudieron cargar los usuarios"
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
    content = <Loading label="Cargando los usuarios…" />;
  }

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/configuracion">Configuración</Link>
            </div>
            <h2 className="page-title">Usuarios</h2>
          </div>
          {can(PERMISSIONS.usersStore) ? (
            <div className="col-auto">
              <Link
                to="/configuracion/usuarios/nuevo"
                state={withUserList(search)}
                className="btn btn-primary user-new"
              >
                <IconPlus size={18} className="me-2" aria-hidden="true" />
                Nuevo usuario
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
              label="Buscar usuarios"
              placeholder="Buscá por nombre, apellido o email"
              value={text}
              onChange={setText}
              maxLength={SEARCH_MAX_LENGTH}
              busy={query.isFetching && query.data !== undefined}
            />
            <SelectField
              className="col-md-4 col-lg-2"
              label="Rol"
              hideLabel
              options={roleOptions}
              value={filters.role ?? ''}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: UserListSearch) => ({
                    ...previous,
                    rol: isRoleName(value) ? value : undefined,
                    pagina: undefined,
                  }),
                });
              }}
            />
            <SelectField
              className="col-md-4 col-lg-3"
              label="Sucursal"
              hideLabel
              options={branchOptions}
              value={filters.branch_id === null ? '' : String(filters.branch_id)}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: UserListSearch) => ({
                    ...previous,
                    sucursal: value === '' ? undefined : Number(value),
                    pagina: undefined,
                  }),
                });
              }}
            />
            <div className="col-md-4 col-lg-2 user-inactive-filter">
              <CheckboxField
                label="Incluir los inactivos"
                checked={filters.include_inactive}
                onChange={(event) => {
                  const checked = event.target.checked;
                  void navigate({
                    search: (previous: UserListSearch) => ({
                      ...previous,
                      inactivos: checked ? 1 : undefined,
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
