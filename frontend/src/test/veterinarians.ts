import type { Veterinarian, VeterinarianDetail } from '../features/veterinarians/types';

/** La ficha de `VET_USER` (src/test/users.ts, id 8), como la manda la API (ShowVeterinarianResource). */
export const VET_DETAIL: VeterinarianDetail = {
  id: 4,
  code: 'VET-0001',
  user: { id: 8, name: 'Víctor Acosta', email: 'vet-a@example.com', is_active: true },
  license_number: 'MV-1001',
  specialty: 'Clínica general',
  status: { value: 'active', label: 'Activo' },
  branches: [{ id: 10, code: 'CEN', name: 'Casa central' }],
  commission_percentage: '12.50',
  notes: 'Guardias los sábados.',
  created_at: '2026-11-03T12:00:00.000000Z',
  updated_at: '2026-11-04T15:30:00.000000Z',
};

/** Otra ficha, inactiva y con su usuario inactivo. */
export const OTHER_VET_DETAIL: VeterinarianDetail = {
  id: 5,
  code: 'VET-0002',
  user: { id: 9, name: 'Laura Benítez', email: 'vet-b@example.com', is_active: false },
  license_number: null,
  specialty: null,
  status: { value: 'inactive', label: 'Inactivo' },
  branches: [
    { id: 10, code: 'CEN', name: 'Casa central' },
    { id: 11, code: 'NOR', name: 'Sucursal Norte' },
  ],
  commission_percentage: '0.00',
  notes: null,
  created_at: '2026-11-05T12:00:00.000000Z',
  updated_at: '2026-11-05T12:00:00.000000Z',
};

/** Una ficha del listado (VeterinarianResource): sin la comisión, las notas ni las fechas. */
export function veterinarianListItem(detail: VeterinarianDetail): Veterinarian {
  return {
    id: detail.id,
    code: detail.code,
    user: detail.user,
    license_number: detail.license_number,
    specialty: detail.specialty,
    status: detail.status,
    branches: detail.branches,
  };
}
