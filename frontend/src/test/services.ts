import type { Service, ServiceDetail } from '../features/services/types';

/** Un servicio como lo manda la API (ShowServiceResource): el precio como texto con dos decimales. */
export const VACCINE: ServiceDetail = {
  id: 3,
  code: 'SRV-0001',
  name: 'Vacuna 5+1',
  category: { value: 'vaccination', label: 'Vacunación' },
  duration_minutes: 30,
  standard_price: '150000.00',
  tax_rate: 10,
  status: { value: 'active', label: 'Activo' },
  description: 'Séxtuple para perros.',
  created_at: '2026-11-03T12:00:00.000000Z',
  updated_at: '2026-11-04T15:30:00.000000Z',
};

/** Otro, inactivo, con un precio largo y sin descripción. */
export const SURGERY: ServiceDetail = {
  id: 4,
  code: 'SRV-0002',
  name: 'Castración macho/hembra',
  category: { value: 'surgery', label: 'Cirugía' },
  duration_minutes: 120,
  standard_price: '999999999999.00',
  tax_rate: 5,
  status: { value: 'inactive', label: 'Inactivo' },
  description: null,
  created_at: '2026-11-05T12:00:00.000000Z',
  updated_at: '2026-11-05T12:00:00.000000Z',
};

/** Un ítem del listado (ServiceResource): sin la descripción ni las fechas. */
export function serviceListItem(detail: ServiceDetail): Service {
  return {
    id: detail.id,
    code: detail.code,
    name: detail.name,
    category: detail.category,
    duration_minutes: detail.duration_minutes,
    standard_price: detail.standard_price,
    tax_rate: detail.tax_rate,
    status: detail.status,
  };
}
