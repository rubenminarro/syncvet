import { z } from 'zod';
import { PERSON_NAME_PATTERN } from '../../lib/person-name';
import {
  ROLE_NAMES,
  type CreateUserPayload,
  type RoleName,
  type UserDetail,
  type UserPayload,
} from './types';
import { trimLikeApi } from '../../lib/format/text';

/**
 * El formulario de alta y edición de usuarios (2.2a). Las reglas de forma de StoreUserRequest y UpdateUserRequest, con los
 * mensajes de UserMessages y UserPassword (backend); lo que necesita la base o la red (el email en uso en toda la base, el
 * DNS, la contraseña filtrada, el último administrador, la ficha de veterinario) lo dice la API.
 */

/** Cómo se usa el formulario: el alta, la edición de otro usuario o la del propio (sin contraseña: se cambia en /perfil). */
export type UserFormMode = 'create' | 'edit-other' | 'edit-self';

export const NAME_MAX = 100;
/** `UserMessages::EMAIL_MAX`: deja lugar al sufijo de baja. */
export const EMAIL_MAX = 150;
/** `Password::defaults()` (R-009): al menos 12 caracteres. */
export const PASSWORD_MIN = 12;

/** Los mensajes de UserMessages y UserPassword (backend), tal cual. */
export const USER_MESSAGES = {
  first_name: {
    required: 'El nombre es obligatorio.',
    min: 'El nombre debe tener al menos 2 caracteres.',
    max: 'El nombre no debe tener más de 100 caracteres.',
    regex: 'El nombre solo puede contener letras y espacios, y apóstrofos o guiones entre letras.',
  },
  last_name: {
    required: 'El apellido es obligatorio.',
    min: 'El apellido debe tener al menos 2 caracteres.',
    max: 'El apellido no debe tener más de 100 caracteres.',
    regex:
      'El apellido solo puede contener letras y espacios, y apóstrofos o guiones entre letras.',
  },
  email: {
    required: 'El email es obligatorio.',
    ascii: 'El email no puede tener acentos ni caracteres especiales.',
    email: 'El email debe tener el formato correcto.',
    max: 'El email no debe tener más de 150 caracteres.',
  },
  password: {
    required: 'Ingresá la contraseña.',
    min: 'La contraseña debe tener al menos 12 caracteres.',
    mixed: 'La contraseña debe tener mayúsculas y minúsculas.',
    symbols: 'La contraseña debe tener al menos un símbolo.',
    numbers: 'La contraseña debe tener al menos un número.',
  },
  roles: { required: 'Elegí al menos un rol.' },
  branch_ids: { required: 'Elegí al menos una sucursal.' },
} as const;

/** La repetición de la contraseña: solo en el navegador (la API no la recibe), como en /perfil. */
export const CONFIRMATION_MESSAGES = {
  required: 'Repetí la contraseña.',
  mismatch: 'Las dos contraseñas no coinciden.',
} as const;

// Las expresiones de la API (PHP con /u). Las de la contraseña, las de Illuminate\Validation\Rules\Password.
/** `ascii` de Laravel: solo caracteres de 7 bits (un carácter de más de 7 bits lo rechaza). */
const NON_ASCII = /[^\p{ASCII}]/u;
/** Solo la forma mínima del email (como en clientes): el formato completo (RFC) y el DNS los valida la API. */
const EMAIL_SHAPE = /^[^\s@]+@[^\s@]+$/;
const MIXED_CASE = /(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})/u;
const SYMBOL = /\p{Z}|\p{S}|\p{P}/u;
const NUMBER = /\p{N}/u;

/** El largo en caracteres, como `mb_strlen` de PHP (no en unidades de UTF-16). */
function length(value: string): number {
  return Array.from(value).length;
}

/** El email como se manda: sin los espacios de los extremos y en minúsculas (como lo guarda la API). */
export function normalizeEmail(value: string): string {
  return trimLikeApi(value).toLowerCase();
}

export interface UserFormValues {
  first_name: string;
  last_name: string;
  email: string;
  roles: RoleName[];
  branch_ids: number[];
  /** Solo en la edición de otro usuario: "Cambiar la contraseña" muestra los dos campos (pregunta 6 del diseño). */
  change_password: boolean;
  password: string;
  password_confirmation: string;
}

/** Los campos del formulario, para `applyValidationErrors` (`branch_ids.0` va a `branch_ids`; `roles.0`, a `roles`). */
export const USER_FORM_FIELDS = [
  'first_name',
  'last_name',
  'email',
  'roles',
  'branch_ids',
  'password',
] as const satisfies readonly (keyof UserFormValues)[];

export const EMPTY_USER_FORM: UserFormValues = {
  first_name: '',
  last_name: '',
  email: '',
  roles: [],
  branch_ids: [],
  change_password: false,
  password: '',
  password_confirmation: '',
};

/** Los datos guardados de un usuario, como valores del formulario de edición (sin contraseña). */
export function userToForm(user: UserDetail): UserFormValues {
  return {
    ...EMPTY_USER_FORM,
    first_name: user.first_name,
    last_name: user.last_name,
    email: user.email,
    roles: ROLE_NAMES.filter((name) => user.roles.some((role) => role.name === name)),
    branch_ids: user.branches.map((branch) => branch.id),
  };
}

function nameError(value: string, messages: (typeof USER_MESSAGES)['first_name' | 'last_name']) {
  const name = trimLikeApi(value);

  if (name === '') {
    return messages.required;
  }

  if (length(name) < 2) {
    return messages.min;
  }

  if (length(name) > NAME_MAX) {
    return messages.max;
  }

  return PERSON_NAME_PATTERN.test(name) ? null : messages.regex;
}

/** El email, en el orden de las reglas de la API (`ascii`, `email`, `max`). */
function emailError(value: string): string | null {
  const m = USER_MESSAGES.email;
  const email = normalizeEmail(value);

  if (email === '') {
    return m.required;
  }

  if (NON_ASCII.test(email)) {
    return m.ascii;
  }

  if (!EMAIL_SHAPE.test(email)) {
    return m.email;
  }

  return email.length > EMAIL_MAX ? m.max : null;
}

/**
 * La contraseña con `Password::defaults()` (sin la consulta de filtraciones, que es de la API), en el orden de Laravel: el
 * largo, mayúsculas y minúsculas, un símbolo y un número. Se manda tal cual (sin recortar los espacios: cuentan como
 * símbolo).
 */
export function passwordError(password: string): string | null {
  const m = USER_MESSAGES.password;

  if (password === '') {
    return m.required;
  }

  if (length(password) < PASSWORD_MIN) {
    return m.min;
  }

  if (!MIXED_CASE.test(password)) {
    return m.mixed;
  }

  if (!SYMBOL.test(password)) {
    return m.symbols;
  }

  return NUMBER.test(password) ? null : m.numbers;
}

/** ¿El formulario manda la contraseña? En el alta, siempre; al editar a otro, con "Cambiar la contraseña"; a sí mismo, nunca. */
export function sendsPassword(values: UserFormValues, mode: UserFormMode): boolean {
  return mode === 'create' || (mode === 'edit-other' && values.change_password);
}

/** Los errores de cada campo; vacío si se puede mandar. */
export function userFormErrors(
  values: UserFormValues,
  mode: UserFormMode,
): Partial<Record<keyof UserFormValues, string>> {
  const errors: Partial<Record<keyof UserFormValues, string | null>> = {
    first_name: nameError(values.first_name, USER_MESSAGES.first_name),
    last_name: nameError(values.last_name, USER_MESSAGES.last_name),
    email: emailError(values.email),
    roles: values.roles.length === 0 ? USER_MESSAGES.roles.required : null,
    branch_ids: values.branch_ids.length === 0 ? USER_MESSAGES.branch_ids.required : null,
  };

  if (sendsPassword(values, mode)) {
    errors.password = passwordError(values.password);

    if (values.password_confirmation === '') {
      errors.password_confirmation = CONFIRMATION_MESSAGES.required;
    } else if (values.password_confirmation !== values.password) {
      errors.password_confirmation = CONFIRMATION_MESSAGES.mismatch;
    }
  }

  return Object.fromEntries(
    Object.entries(errors).filter((entry): entry is [string, string] => entry[1] !== null),
  );
}

const userFields = {
  first_name: z.string(),
  last_name: z.string(),
  email: z.string(),
  roles: z.array(z.enum(ROLE_NAMES)),
  branch_ids: z.array(z.number()),
  change_password: z.boolean(),
  password: z.string(),
  password_confirmation: z.string(),
};

/** El esquema de React Hook Form: cada error de `userFormErrors` en su campo. */
export function userFormSchema(mode: UserFormMode) {
  return z.object(userFields).superRefine((values, context) => {
    for (const [field, message] of Object.entries(userFormErrors(values, mode))) {
      context.addIssue({ code: 'custom', path: [field], message });
    }
  });
}

/**
 * El cuerpo de la edición: el formulario completo, los nombres sin los espacios de los extremos y el email en minúsculas.
 * La clave `password` va solo si se cambia la de otro usuario: la API rechaza hasta un `null` (H3 del diseño; excepción a
 * "los opcionales vacíos van en null").
 */
export function userFormToPayload(values: UserFormValues, mode: UserFormMode): UserPayload {
  const payload: UserPayload = {
    first_name: trimLikeApi(values.first_name),
    last_name: trimLikeApi(values.last_name),
    email: normalizeEmail(values.email),
    roles: [...values.roles],
    branch_ids: [...values.branch_ids],
  };

  if (sendsPassword(values, mode)) {
    payload.password = values.password;
  }

  return payload;
}

/** El cuerpo del alta: con la contraseña. */
export function userFormToCreatePayload(values: UserFormValues): CreateUserPayload {
  return { ...userFormToPayload(values, 'create'), password: values.password };
}
