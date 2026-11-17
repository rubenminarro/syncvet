import { IconEdit } from '@tabler/icons-react';
import { useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment, type ReactNode } from 'react';
import { Card, ErrorPage } from '../../components/ui';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDateTime } from '../../lib/format/dates';
import { useCan } from '../auth/permissions';
import { veterinarianDetailQueryOptions } from './api';
import { formatCommission } from './form';
import { useVeterinarianListSearch, withVeterinarianList } from './navigation';
import type { VeterinarianDetail } from './types';
import { VeterinarianActions } from './VeterinarianActions';
import { VeterinarianBadges } from './VeterinarianBadges';

/**
 * /veterinarios/$id (2.3, solo el administrador): el código, el usuario (con el enlace a su detalle), la matrícula, la
 * especialidad, la comisión ("12,5 %"), las sucursales, el estado, las notas y las fechas; "Editar", inactivar o activar y
 * eliminar (`VeterinarianActions`).
 */
export function VeterinarianDetailPage({ id }: { id: number }) {
  const { data: veterinarian } = useSuspenseQuery(veterinarianDetailQueryOptions(id));
  const can = useCan();
  const listSearch = useVeterinarianListSearch();

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/veterinarios" search={listSearch}>
                Veterinarios
              </Link>
            </div>
            <h2 className="page-title d-flex gap-2 align-items-center flex-wrap">
              <span className="veterinarian-title">
                {veterinarian.code} · {veterinarian.user.name}
              </span>
              <VeterinarianBadges veterinarian={veterinarian} />
            </h2>
          </div>
          <div className="col-auto d-flex gap-2 flex-wrap veterinarian-actions">
            {can(PERMISSIONS.veterinariansUpdate) ? (
              <Link
                to="/veterinarios/$id/editar"
                params={{ id: String(veterinarian.id) }}
                state={withVeterinarianList(listSearch)}
                className="btn btn-primary veterinarian-edit"
              >
                <IconEdit size={18} className="me-2" aria-hidden="true" />
                Editar
              </Link>
            ) : null}
            <VeterinarianActions veterinarian={veterinarian} />
          </div>
        </div>
      </div>
      <div className="row row-cards">
        <div className="col-lg-6">
          <VeterinarianData veterinarian={veterinarian} />
        </div>
        <div className="col-lg-6">
          <VeterinarianUser veterinarian={veterinarian} />
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

function VeterinarianData({ veterinarian }: { veterinarian: VeterinarianDetail }) {
  return (
    <Card title="Datos de la ficha">
      <DataList
        className="veterinarian-data"
        rows={[
          ['Código', veterinarian.code],
          ['Matrícula', orDash(veterinarian.license_number)],
          ['Especialidad', orDash(veterinarian.specialty)],
          ['Comisión', orDash(formatCommission(veterinarian.commission_percentage))],
          ['Estado', veterinarian.status.label],
          ['Notas', orDash(veterinarian.notes)],
          ['Alta', orDash(formatDateTime(veterinarian.created_at))],
          ['Última modificación', orDash(formatDateTime(veterinarian.updated_at))],
        ]}
      />
    </Card>
  );
}

function VeterinarianUser({ veterinarian }: { veterinarian: VeterinarianDetail }) {
  const can = useCan();
  const { user } = veterinarian;

  return (
    <Card title="Usuario">
      <DataList
        className="veterinarian-user"
        rows={[
          [
            'Nombre',
            can(PERMISSIONS.usersShow) ? (
              <Link
                to="/configuracion/usuarios/$id"
                params={{ id: String(user.id) }}
                className="veterinarian-user-link"
              >
                {user.name}
              </Link>
            ) : (
              user.name
            ),
          ],
          ['Email', user.email],
          ['Estado del usuario', user.is_active ? 'Activo' : 'Usuario inactivo'],
          [
            'Sucursales',
            veterinarian.branches.length === 0
              ? 'Ninguna'
              : veterinarian.branches.map((branch) => `${branch.code} · ${branch.name}`).join(', '),
          ],
        ]}
      />
    </Card>
  );
}

/** La página de no encontrado del detalle y la edición (un id inválido o el 404 de la API), dentro del layout. */
export function VeterinarianNotFound() {
  return (
    <ErrorPage
      code="404"
      title="No encontramos este veterinario"
      message="El veterinario no existe. Buscalo en el listado."
      action={
        <Link to="/veterinarios" className="btn btn-primary">
          Volver a los veterinarios
        </Link>
      }
    />
  );
}
