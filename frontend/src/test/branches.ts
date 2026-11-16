import type { Branch, BranchDetail, Warehouse } from '../features/branches/types';

/**
 * Sucursales y depósitos de prueba, como los manda la API (ShowBranchResource y WarehouseResource). La 10 es la sucursal
 * por defecto y la actual de `ME` (src/test/session.tsx).
 */

export function warehouse(overrides: Partial<Warehouse> = {}): Warehouse {
  return {
    id: 100,
    branch_id: 10,
    code: 'CEN-DEP',
    name: 'Principal',
    is_default: true,
    is_active: true,
    ...overrides,
  };
}

export const CENTRAL: BranchDetail = {
  id: 10,
  code: 'CEN',
  name: 'Casa central',
  establishment: '001',
  address: 'Av. Mcal. López 1234',
  phone: '+595211100001',
  is_default: true,
  is_active: true,
  warehouses: [warehouse()],
  created_at: '2026-11-01T12:00:00.000000Z',
  updated_at: '2026-11-02T15:30:00.000000Z',
};

export const NORTH: BranchDetail = {
  id: 11,
  code: 'NOR',
  name: 'Sucursal Norte',
  establishment: '002',
  address: null,
  phone: null,
  is_default: false,
  is_active: true,
  warehouses: [
    warehouse({ id: 110, branch_id: 11, code: 'NOR-DEP', name: 'Principal' }),
    warehouse({ id: 111, branch_id: 11, code: 'NOR-FAR', name: 'Farmacia', is_default: false }),
  ],
  created_at: '2026-11-03T12:00:00.000000Z',
  updated_at: '2026-11-03T12:00:00.000000Z',
};

/** Una sucursal sin depósitos en el listado (BranchResource). */
export function listItem(branch: BranchDetail): Branch {
  return {
    id: branch.id,
    code: branch.code,
    name: branch.name,
    establishment: branch.establishment,
    address: branch.address,
    phone: branch.phone,
    is_default: branch.is_default,
    is_active: branch.is_active,
  };
}
