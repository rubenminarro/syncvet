import type { Role, User, UserDetail } from '../features/users/types';

/** Los roles de GET roles (RoleResource), como los manda la API. */
export const ROLES_RESPONSE: Role[] = [
  { name: 'administrador', label: 'Administrador' },
  { name: 'recepcionista', label: 'Recepcionista' },
  { name: 'veterinario', label: 'Veterinario' },
];

const LABELS: Record<Role['name'], string> = {
  administrador: 'Administrador',
  recepcionista: 'Recepcionista',
  veterinario: 'Veterinario',
};

const role = (name: Role['name']): Role => ({ name, label: LABELS[name] });

/** El usuario de la sesión de `ME` (src/test/session.tsx, id 3): administrador, con la sucursal 10. */
export const ADMIN_USER: UserDetail = {
  id: 3,
  first_name: 'Ana',
  last_name: 'Gómez',
  name: 'Ana Gómez',
  email: 'admin-a@example.com',
  roles: [role('administrador')],
  branches: [{ id: 10, code: 'CEN', name: 'Casa central' }],
  current_branch_id: 10,
  is_active: true,
  veterinarian: null,
  created_at: '2026-11-01T12:00:00.000000Z',
  updated_at: '2026-11-02T15:30:00.000000Z',
};

/** Otro usuario: recepcionista con las sucursales 10 y 11 (la 11 es la actual). */
export const RECEPTION_USER: UserDetail = {
  id: 7,
  first_name: 'Rosa',
  last_name: 'Benítez',
  name: 'Rosa Benítez',
  email: 'recepcion-a@example.com',
  roles: [role('recepcionista')],
  branches: [
    { id: 10, code: 'CEN', name: 'Casa central' },
    { id: 11, code: 'NOR', name: 'Sucursal Norte' },
  ],
  current_branch_id: 11,
  is_active: true,
  veterinarian: null,
  created_at: '2026-11-03T12:00:00.000000Z',
  updated_at: '2026-11-03T12:00:00.000000Z',
};

/** Un veterinario con ficha. */
export const VET_USER: UserDetail = {
  id: 8,
  first_name: 'Víctor',
  last_name: 'Acosta',
  name: 'Víctor Acosta',
  email: 'vet-a@example.com',
  roles: [role('veterinario')],
  branches: [{ id: 10, code: 'CEN', name: 'Casa central' }],
  current_branch_id: 10,
  is_active: true,
  veterinarian: { id: 4, code: 'VET-0001' },
  created_at: '2026-11-03T12:00:00.000000Z',
  updated_at: '2026-11-03T12:00:00.000000Z',
};

export { role as testRole };

/** Un usuario del listado (UserResource): con la ficha (front-02), sin las fechas. */
export function userListItem(user: UserDetail): User {
  return {
    id: user.id,
    first_name: user.first_name,
    last_name: user.last_name,
    name: user.name,
    email: user.email,
    roles: user.roles,
    branches: user.branches,
    current_branch_id: user.current_branch_id,
    is_active: user.is_active,
    veterinarian: user.veterinarian,
  };
}
