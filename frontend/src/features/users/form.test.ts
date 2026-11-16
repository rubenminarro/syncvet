import { describe, expect, it } from 'vitest';
import {
  CONFIRMATION_MESSAGES,
  EMPTY_USER_FORM,
  passwordError,
  USER_MESSAGES,
  userFormErrors,
  userFormToCreatePayload,
  userFormToPayload,
  type UserFormValues,
} from './form';

const PASSWORD = 'Clave-Segura-2026';

const VALID: UserFormValues = {
  ...EMPTY_USER_FORM,
  first_name: 'Rosa',
  last_name: 'Benítez',
  email: 'rosa@example.com',
  roles: ['recepcionista'],
  branch_ids: [10],
  password: PASSWORD,
  password_confirmation: PASSWORD,
};

const m = USER_MESSAGES;

describe('passwordError: Password::defaults() en el borde de cada regla', () => {
  it.each([
    ['vacía', '', m.password.required],
    ['11 caracteres', 'Abcdefgh-1x', m.password.min],
    ['12 caracteres', 'Abcdefgh-1xy', null],
    ['12 caracteres con un emoji (cuenta como uno)', 'Abcdefgh-1😀y', null],
    ['11 caracteres con un emoji (en UTF-16 serían 12)', 'Abcdefgh-1😀', m.password.min],
    ['sin mayúscula', 'abcdefgh-1xy', m.password.mixed],
    ['sin minúscula', 'ABCDEFGH-1XY', m.password.mixed],
    ['sin símbolo', 'Abcdefgh11xy', m.password.symbols],
    ['un espacio cuenta como símbolo', 'Abcdefgh 1xy', null],
    ['sin número', 'Abcdefgh-xyz', m.password.numbers],
    ['con letras acentuadas', 'Ñandutí-2026x', null],
  ] as const)('%s', (_case, password, expected) => {
    expect(passwordError(password)).toBe(expected);
  });
});

describe('userFormErrors', () => {
  it('el formulario válido pasa en los tres modos', () => {
    expect(userFormErrors(VALID, 'create')).toEqual({});
    expect(userFormErrors(VALID, 'edit-other')).toEqual({});
    expect(userFormErrors(VALID, 'edit-self')).toEqual({});
  });

  it.each([
    ["D'Alessandro", null],
    ['Ana-María', null],
    ['Ana María', null],
    ['Ab', null],
    ['A', m.first_name.min],
    ['A'.repeat(100), null],
    ['A'.repeat(101), m.first_name.max],
    ["'Ana", m.first_name.regex],
    ['Ana-', m.first_name.regex],
    ['Ana--María', m.first_name.regex],
    ['Ana3', m.first_name.regex],
    ['   ', m.first_name.required],
  ] as const)('el nombre %j', (value, expected) => {
    expect(userFormErrors({ ...VALID, first_name: value }, 'create').first_name).toBe(
      expected ?? undefined,
    );
  });

  it.each([
    ['', m.email.required],
    ['rosa', m.email.email],
    ['rosa@', m.email.email],
    ['josé@example.com', m.email.ascii],
    [`${'a'.repeat(138)}@example.com`, null],
    [`${'a'.repeat(139)}@example.com`, m.email.max],
    ['  ROSA@Example.com ', null],
  ] as const)('el email %j', (value, expected) => {
    expect(userFormErrors({ ...VALID, email: value }, 'create').email).toBe(expected ?? undefined);
  });

  it('roles y sucursales: al menos uno', () => {
    expect(userFormErrors({ ...VALID, roles: [], branch_ids: [] }, 'edit-other')).toEqual({
      roles: m.roles.required,
      branch_ids: m.branch_ids.required,
    });
  });

  it('la contraseña: obligatoria en el alta, con la repetición', () => {
    expect(userFormErrors({ ...VALID, password: '', password_confirmation: '' }, 'create')).toEqual(
      {
        password: m.password.required,
        password_confirmation: CONFIRMATION_MESSAGES.required,
      },
    );
    expect(userFormErrors({ ...VALID, password_confirmation: `${PASSWORD}x` }, 'create')).toEqual({
      password_confirmation: CONFIRMATION_MESSAGES.mismatch,
    });
  });

  it('al editar a otro, solo con "Cambiar la contraseña"; al editarse a sí mismo, nunca', () => {
    const empty = { ...VALID, password: '', password_confirmation: '' };

    expect(userFormErrors(empty, 'edit-other')).toEqual({});
    expect(userFormErrors({ ...empty, change_password: true }, 'edit-other')).toEqual({
      password: m.password.required,
      password_confirmation: CONFIRMATION_MESSAGES.required,
    });
    expect(userFormErrors({ ...empty, change_password: true }, 'edit-self')).toEqual({});
  });
});

describe('el cuerpo', () => {
  it('el alta: los nombres recortados, el email en minúsculas y la contraseña tal cual', () => {
    expect(
      userFormToCreatePayload({
        ...VALID,
        first_name: ' Rosa ',
        email: ' Rosa@Example.COM ',
        roles: ['recepcionista', 'veterinario'],
        branch_ids: [10, 11],
        password: ` ${PASSWORD} `,
        password_confirmation: ` ${PASSWORD} `,
      }),
    ).toEqual({
      first_name: 'Rosa',
      last_name: 'Benítez',
      email: 'rosa@example.com',
      roles: ['recepcionista', 'veterinario'],
      branch_ids: [10, 11],
      password: ` ${PASSWORD} `,
    });
  });

  it('la edición sin "Cambiar la contraseña" no lleva la clave password (ni null)', () => {
    const payload = userFormToPayload(VALID, 'edit-other');

    expect(payload).not.toHaveProperty('password');
    expect(Object.keys(payload)).toEqual([
      'first_name',
      'last_name',
      'email',
      'roles',
      'branch_ids',
    ]);
  });

  it('con "Cambiar la contraseña", la lleva; el propio usuario, nunca', () => {
    const changing = { ...VALID, change_password: true };

    expect(userFormToPayload(changing, 'edit-other').password).toBe(PASSWORD);
    expect(userFormToPayload(changing, 'edit-self')).not.toHaveProperty('password');
  });
});
