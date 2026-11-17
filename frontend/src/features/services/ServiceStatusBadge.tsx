import type { Service } from './types';

/** El estado del servicio ("Activo" o "Inactivo", el `label` de la API), en el listado y en el detalle. */
export function ServiceStatusBadge({ service }: { service: Pick<Service, 'status'> }) {
  return (
    <span
      className={`badge ${service.status.value === 'active' ? 'bg-green-lt' : 'bg-secondary-lt'} service-status`}
    >
      {service.status.label}
    </span>
  );
}
