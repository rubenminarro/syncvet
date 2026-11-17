import { IconEdit } from '@tabler/icons-react';
import { useQuery, useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment, type ReactNode } from 'react';
import { Card, ErrorPage } from '../../components/ui';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDateTime } from '../../lib/format/dates';
import { meQueryOptions } from '../auth/api';
import { useCan } from '../auth/permissions';
import { userDetailQueryOptions } from './api';
import { useUserListSearch, withUserList } from './navigation';
import { VETERINARIAN_ROLE, type UserDetail } from './types';
import { UserActions } from './UserActions';
import { UserInactiveBadge } from './UserInactiveBadge';

/**
 * /configuracion/usuarios/$id (2.2a): los datos, los roles, las sucursales (con la actual marcada), la ficha de
 * veterinario y "Editar". "(vos)" junto al nombre del usuario de la sesión. Desde la 2.2b, inactivar o reactivar y
 * eliminar (`UserActions`), salvo en el propio usuario. Desde la 2.3, el enlace a la ficha de veterinario y, con el rol y
 * sin ficha, "Crear la ficha de veterinario" (`/veterinarios/nuevo?usuario=`).
 */
export function UserDetailPage({ id }: { id: number }) {
  const { data: user } = useSuspenseQuery(userDetailQueryOptions(id));
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const can = useCan();
  const listSearch = useUserListSearch();
  const isSelf = me?.id === user.id;

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/configuracion">Configuración</Link>
              {' / '}
              <Link to="/configuracion/usuarios" search={listSearch}>
                Usuarios
              </Link>
            </div>
            <h2 className="page-title d-flex gap-2 align-items-center">
              {user.name}
              {isSelf ? <span className="text-secondary fw-normal user-self">(vos)</span> : null}
              <UserInactiveBadge user={user} />
            </h2>
          </div>
          <div className="col-auto d-flex gap-2 flex-wrap user-actions">
            {can(PERMISSIONS.usersUpdate) ? (
              <Link
                to="/configuracion/usuarios/$id/editar"
                params={{ id: String(user.id) }}
                state={withUserList(listSearch)}
                className="btn btn-primary user-edit"
              >
                <IconEdit size={18} className="me-2" aria-hidden="true" />
                Editar
              </Link>
            ) : null}
            <UserActions user={user} isSelf={isSelf} />
          </div>
        </div>
      </div>
      <div className="row row-cards">
        <div className="col-lg-6">
          <UserData user={user} />
        </div>
        <div className="col-lg-6">
          <UserAccess user={user} />
        </div>
      </div>
    </>
  );
}

/** Un dato vacío se muestra con una raya: la fila sigue a la vista. */
function orDash(value: string | null | undefined): string {
  return value === null || value === undefined || value === '' ? '—' : value;
}

function DataList({ rows, className }: { rows: [string, ReactNode][]; className: string }) {
  return (
    <dl className={`row mb-0 ${className}`}>
      {rows.map(([label, value]) => (
        <Fragment key={label}>
          <dt className="col-5">{label}</dt>
          <dd className="col-7 text-break">{value}</dd>
        </Fragment>
      ))}
    </dl>
  );
}

function UserData({ user }: { user: UserDetail }) {
  return (
    <Card title="Datos del usuario">
      <DataList
        className="user-data"
        rows={[
          ['Nombre', user.first_name],
          ['Apellido', user.last_name],
          ['Email', user.email],
          ['Estado', user.is_active ? 'Activo' : 'Inactivado'],
          ['Ficha de veterinario', <VeterinarianRecord user={user} />],
          ['Alta', orDash(formatDateTime(user.created_at))],
          ['Última modificación', orDash(formatDateTime(user.updated_at))],
        ]}
      />
    </Card>
  );
}

/**
 * La ficha de veterinario (2.3): el código, con el enlace a su detalle (`veterinarians.show`); sin ficha, "Sin ficha" y,
 * si el usuario está activo y tiene el rol veterinario, "Crear la ficha de veterinario" (`veterinarians.store`).
 */
function VeterinarianRecord({ user }: { user: UserDetail }) {
  const can = useCan();
  const { veterinarian } = user;

  if (veterinarian !== null) {
    return can(PERMISSIONS.veterinariansShow) ? (
      <Link
        to="/veterinarios/$id"
        params={{ id: String(veterinarian.id) }}
        className="user-veterinarian-link"
      >
        {veterinarian.code}
      </Link>
    ) : (
      veterinarian.code
    );
  }

  const canCreate =
    user.is_active &&
    user.roles.some((role) => role.name === VETERINARIAN_ROLE) &&
    can(PERMISSIONS.veterinariansStore);

  if (!canCreate) {
    return 'Sin ficha';
  }

  return (
    <span className="d-flex flex-wrap gap-2 align-items-center">
      Sin ficha
      <Link
        to="/veterinarios/nuevo"
        search={{ usuario: user.id }}
        className="btn btn-sm btn-outline-primary user-create-veterinarian"
      >
        Crear la ficha de veterinario
      </Link>
    </span>
  );
}

function UserAccess({ user }: { user: UserDetail }) {
  const branches =
    user.branches.length === 0 ? (
      'Ninguna'
    ) : (
      <ul className="list-unstyled mb-0 user-branches">
        {user.branches.map((branch) => (
          <li key={branch.id}>
            {branch.code} · {branch.name}
            {branch.id === user.current_branch_id ? (
              <span className="badge bg-blue-lt ms-2 user-current-branch">Actual</span>
            ) : null}
          </li>
        ))}
      </ul>
    );

  return (
    <Card title="Roles y sucursales">
      <DataList
        className="user-access"
        rows={[
          ['Roles', user.roles.map((role) => role.label).join(', ')],
          ['Sucursales', branches],
        ]}
      />
    </Card>
  );
}

/** La página de no encontrado del detalle y la edición (un id inválido o el 404 de la API), dentro del layout. */
export function UserNotFound() {
  return (
    <ErrorPage
      code="404"
      title="No encontramos este usuario"
      message="El usuario no existe. Buscalo en el listado."
      action={
        <Link to="/configuracion/usuarios" className="btn btn-primary">
          Volver a los usuarios
        </Link>
      }
    />
  );
}
