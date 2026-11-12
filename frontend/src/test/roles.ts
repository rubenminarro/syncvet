import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import type { Me } from '../features/auth/types';
import { ME } from './session';

/**
 * Los permisos de los tres roles reales del seeder, leídos tal cual del fixture del backend
 * (backend/tests/Fixtures/clinic-roles.json, el que controla ClinicRolesTest): no se copian ni se inventan.
 */
export const ROLE_PERMISSIONS = JSON.parse(
  readFileSync(
    join(process.cwd(), '..', 'backend', 'tests', 'Fixtures', 'clinic-roles.json'),
    'utf8',
  ),
) as Record<'administrador' | 'recepcionista' | 'veterinario', string[]>;

export type Role = keyof typeof ROLE_PERMISSIONS;

export const ROLES: Role[] = ['administrador', 'recepcionista', 'veterinario'];

/** `me` de un usuario con ese rol y sus permisos reales. */
export function meFor(role: Role, overrides: Partial<Me> = {}): Me {
  return { ...ME, roles: [role], permissions: ROLE_PERMISSIONS[role], ...overrides };
}
