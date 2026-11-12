import { describe, expect, it } from 'vitest';
import { MENU, visibleMenu } from '../../app/menu';
import { ROLE_PERMISSIONS, ROLES, type Role } from '../../test/roles';
import { can, PERMISSIONS } from './permissions';

const meOf = (role: Role) => ({ permissions: ROLE_PERMISSIONS[role] });

describe('can', () => {
  it('el fixture del backend trae los tres roles con sus permisos', () => {
    expect(Object.keys(ROLE_PERMISSIONS).sort()).toEqual(ROLES);
    expect(ROLE_PERMISSIONS.recepcionista).toContain('clients.index');
  });

  it.each([
    ['administrador', true],
    ['recepcionista', false],
    ['veterinario', false],
  ] as const)('%s, system-settings.update: %s', (role, expected) => {
    expect(can(meOf(role), PERMISSIONS.settingsUpdate)).toBe(expected);
  });

  it('sin usuario, nunca', () => {
    expect(can(null, PERMISSIONS.settingsUpdate)).toBe(false);
    expect(can(undefined, 'clients.index')).toBe(false);
  });

  it('busca el permiso exacto, no un prefijo', () => {
    expect(can({ permissions: ['system-settings.update'] }, 'system-settings')).toBe(false);
    expect(can({ permissions: ['system-settings'] }, 'system-settings.update')).toBe(false);
  });
});

describe('visibleMenu', () => {
  const labels = (role: Role) =>
    visibleMenu(MENU, (permission) => can(meOf(role), permission)).map((item) => item.label);

  it.each([
    ['administrador', ['Inicio', 'Configuración']],
    ['recepcionista', ['Inicio']],
    ['veterinario', ['Inicio']],
  ] as const)('%s ve %j', (role, expected) => {
    expect(labels(role)).toEqual(expected);
  });
});
