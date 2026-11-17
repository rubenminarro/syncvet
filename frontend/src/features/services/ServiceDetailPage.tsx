import { IconEdit } from '@tabler/icons-react';
import { useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment, type ReactNode } from 'react';
import { Card, ErrorPage } from '../../components/ui';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDateTime } from '../../lib/format/dates';
import { formatGuaranies } from '../../lib/format/money';
import { useCan } from '../auth/permissions';
import { serviceDetailQueryOptions } from './api';
import { formatDuration, formatTaxRate } from './form';
import { useServiceListSearch, withServiceList } from './navigation';
import { ServiceActions } from './ServiceActions';
import { ServiceStatusBadge } from './ServiceStatusBadge';
import type { ServiceDetail } from './types';

/**
 * /servicios/$id (2.4, los tres roles): el código, el nombre, la categoría, la duración, el precio, el IVA, el estado, la
 * descripción y las fechas; "Editar", inactivar o activar y eliminar (`ServiceActions`), solo con su permiso (el
 * administrador).
 */
export function ServiceDetailPage({ id }: { id: number }) {
  const { data: service } = useSuspenseQuery(serviceDetailQueryOptions(id));
  const can = useCan();
  const listSearch = useServiceListSearch();

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/servicios" search={listSearch}>
                Servicios
              </Link>
            </div>
            <h2 className="page-title d-flex gap-2 align-items-center flex-wrap">
              <span className="service-title">
                {service.code} · {service.name}
              </span>
              <ServiceStatusBadge service={service} />
            </h2>
          </div>
          <div className="col-auto d-flex gap-2 flex-wrap service-actions">
            {can(PERMISSIONS.servicesUpdate) ? (
              <Link
                to="/servicios/$id/editar"
                params={{ id: String(service.id) }}
                state={withServiceList(listSearch)}
                className="btn btn-primary service-edit"
              >
                <IconEdit size={18} className="me-2" aria-hidden="true" />
                Editar
              </Link>
            ) : null}
            <ServiceActions service={service} />
          </div>
        </div>
      </div>
      <div className="row row-cards">
        <div className="col-lg-8">
          <ServiceData service={service} />
        </div>
      </div>
    </>
  );
}

/** Un dato vacío se muestra con una raya: la fila sigue a la vista. */
function orDash(value: string | null | undefined): string {
  return value === null || value === undefined || value === '' ? '—' : value;
}

function ServiceData({ service }: { service: ServiceDetail }) {
  const rows: [string, ReactNode][] = [
    ['Código', service.code],
    ['Nombre', service.name],
    ['Categoría', service.category.label],
    ['Duración', formatDuration(service.duration_minutes)],
    ['Precio', orDash(formatGuaranies(service.standard_price))],
    ['IVA', formatTaxRate(service.tax_rate)],
    ['Estado', service.status.label],
    ['Descripción', orDash(service.description)],
    ['Alta', orDash(formatDateTime(service.created_at))],
    ['Última modificación', orDash(formatDateTime(service.updated_at))],
  ];

  return (
    <Card title="Datos del servicio">
      <dl className="row mb-0 service-data">
        {rows.map(([label, value]) => (
          <Fragment key={label}>
            <dt className="col-5 col-md-4">{label}</dt>
            <dd className="col-7 col-md-8 text-break">{value}</dd>
          </Fragment>
        ))}
      </dl>
    </Card>
  );
}

/** La página de no encontrado del detalle y la edición (un id inválido o el 404 de la API), dentro del layout. */
export function ServiceNotFound() {
  return (
    <ErrorPage
      code="404"
      title="No encontramos este servicio"
      message="El servicio no existe. Buscalo en el listado."
      action={
        <Link to="/servicios" className="btn btn-primary">
          Volver a los servicios
        </Link>
      }
    />
  );
}
