import { z } from 'zod';
import { normalizePhone, PHONE_MESSAGE, PHONE_PATTERN } from '../../lib/phone';
import type {
  Branch,
  BranchPayload,
  CreateBranchPayload,
  Warehouse,
  WarehousePayload,
} from './types';

/**
 * Los formularios de la sucursal (con su depósito por defecto en el alta) y del depósito (2.1). Las reglas de forma de
 * StoreBranchRequest, UpdateBranchRequest, StoreWarehouseRequest y UpdateWarehouseRequest, con los mensajes de
 * BranchMessages; lo que necesita la base (el código, el nombre o el establecimiento en uso, el establecimiento con
 * facturas) lo dice la API.
 */

// Las expresiones de la API (PHP con /u), con \pL y \pN escritos \p{L} y \p{N}.
/** `BranchMessages::CODE_REGEX`, después de pasar el código a mayúsculas. */
const CODE_PATTERN = /^[A-Z0-9-]+$/;
/** `CatalogMessages::NAME_REGEX`: al menos una letra; letras, números, espacios, puntos, guiones, apóstrofos y paréntesis. */
const NAME_PATTERN = /^(?=[^\p{L}]*\p{L})[\p{L}\p{N}\s.\-'()]+$/u;
/** `BranchMessages::ESTABLISHMENT_REGEX`: tres dígitos, de 001 a 999. */
const ESTABLISHMENT_PATTERN = /^(?!000)\d{3}$/;
/** `BranchMessages::ADDRESS_REGEX` (la de clientes). */
const ADDRESS_PATTERN = /^[\p{L}\p{N}\s.,#\-/°º&()']+$/u;

export const CODE_MAX = 20;
export const NAME_MAX = 100;

function codeMessages(subject: string) {
  return {
    required: `${subject} es obligatorio.`,
    max: `${subject} no debe tener más de 20 caracteres.`,
    regex: `${subject} solo puede contener letras sin acentos, números y guiones.`,
  };
}

function nameMessages(subject: string) {
  return {
    required: `${subject} es obligatorio.`,
    min: `${subject} debe tener al menos 2 caracteres.`,
    max: `${subject} no debe tener más de 100 caracteres.`,
    regex: `${subject} debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.`,
  };
}

/** Los mensajes de BranchMessages (backend), tal cual. */
export const BRANCH_MESSAGES = {
  code: codeMessages('El código de la sucursal'),
  name: nameMessages('El nombre de la sucursal'),
  establishment: {
    required: 'El código de establecimiento es obligatorio.',
    regex: 'El código de establecimiento debe tener tres dígitos, de 001 a 999.',
  },
  address: {
    max: 'La dirección no debe tener más de 255 caracteres.',
    regex: 'La dirección contiene caracteres no permitidos.',
  },
  phone: { regex: PHONE_MESSAGE },
  warehouse_code: codeMessages('El código del depósito'),
  warehouse_name: nameMessages('El nombre del depósito'),
} as const;

/** Los del depósito (`BranchMessages::warehouse()`): los mismos textos, con `code` y `name`. */
export const WAREHOUSE_MESSAGES = {
  code: BRANCH_MESSAGES.warehouse_code,
  name: BRANCH_MESSAGES.warehouse_name,
} as const;

/** El largo en caracteres, como `mb_strlen` de PHP (no en unidades de UTF-16). */
function length(value: string): number {
  return Array.from(value).length;
}

/**
 * El código como lo guarda la API: sin los espacios de los extremos y en mayúsculas. Solo las letras ASCII, como
 * `strtoupper` de PHP 8 ("ß" o "ñ" no cambian y la regla los rechaza, como la API).
 */
export function normalizeCode(value: string): string {
  return value.trim().replace(/[a-z]/g, (letter) => letter.toUpperCase());
}

function codeError(value: string, messages: ReturnType<typeof codeMessages>): string | null {
  const code = normalizeCode(value);

  if (code === '') {
    return messages.required;
  }

  if (length(code) > CODE_MAX) {
    return messages.max;
  }

  return CODE_PATTERN.test(code) ? null : messages.regex;
}

/** El nombre (sin los espacios de los extremos); `optional`: vacío pasa (el del depósito en el alta de la sucursal). */
function nameError(
  value: string,
  messages: ReturnType<typeof nameMessages>,
  { optional = false } = {},
): string | null {
  const name = value.trim();

  if (name === '') {
    return optional ? null : messages.required;
  }

  if (length(name) < 2) {
    return messages.min;
  }

  if (length(name) > NAME_MAX) {
    return messages.max;
  }

  return NAME_PATTERN.test(name) ? null : messages.regex;
}

/** Un texto opcional: sin los espacios de los extremos, y null si queda vacío. */
function optionalText(value: string): string | null {
  const trimmed = value.trim();

  return trimmed === '' ? null : trimmed;
}

function withoutNulls<K extends string>(errors: Partial<Record<K, string | null>>) {
  return Object.fromEntries(
    Object.entries(errors).filter((entry): entry is [string, string] => entry[1] !== null),
  ) as Partial<Record<K, string>>;
}

// ---------------------------------------------------------------------------------------------------------------------
// La sucursal
// ---------------------------------------------------------------------------------------------------------------------

export interface BranchFormValues {
  code: string;
  name: string;
  establishment: string;
  address: string;
  phone: string;
  is_default: boolean;
  /** Solo en el alta: el depósito por defecto que crea la API. */
  warehouse_code: string;
  warehouse_name: string;
}

/** Los campos del formulario, para `applyValidationErrors` (las claves de `errors` de la API). */
export const BRANCH_FORM_FIELDS = [
  'code',
  'name',
  'establishment',
  'address',
  'phone',
  'is_default',
  'warehouse_code',
  'warehouse_name',
] as const satisfies readonly (keyof BranchFormValues)[];

export const EMPTY_BRANCH_FORM: BranchFormValues = {
  code: '',
  name: '',
  establishment: '',
  address: '',
  phone: '',
  is_default: false,
  warehouse_code: '',
  warehouse_name: '',
};

/** Los datos guardados de una sucursal, como valores del formulario de edición (null → vacío). */
export function branchToForm(branch: Branch): BranchFormValues {
  return {
    ...EMPTY_BRANCH_FORM,
    code: branch.code,
    name: branch.name,
    establishment: branch.establishment,
    address: branch.address ?? '',
    phone: branch.phone ?? '',
    is_default: branch.is_default,
  };
}

/** Los errores de cada campo; vacío si se puede mandar. `creating`: el alta, con los campos del depósito. */
export function branchFormErrors(
  values: BranchFormValues,
  { creating }: { creating: boolean },
): Partial<Record<keyof BranchFormValues, string>> {
  const m = BRANCH_MESSAGES;
  const establishment = values.establishment.trim();
  const address = values.address.trim();
  const phone = normalizePhone(values.phone);
  let addressError: string | null = null;

  if (address !== '') {
    if (length(address) > 255) {
      addressError = m.address.max;
    } else if (!ADDRESS_PATTERN.test(address)) {
      addressError = m.address.regex;
    }
  }

  const errors: Partial<Record<keyof BranchFormValues, string | null>> = {
    code: codeError(values.code, m.code),
    name: nameError(values.name, m.name),
    establishment:
      establishment === ''
        ? m.establishment.required
        : ESTABLISHMENT_PATTERN.test(establishment)
          ? null
          : m.establishment.regex,
    address: addressError,
    phone: phone === '' || PHONE_PATTERN.test(phone) ? null : m.phone.regex,
  };

  if (creating) {
    errors.warehouse_code = codeError(values.warehouse_code, m.warehouse_code);
    errors.warehouse_name = nameError(values.warehouse_name, m.warehouse_name, { optional: true });
  }

  return withoutNulls(errors);
}

const branchFields = {
  code: z.string(),
  name: z.string(),
  establishment: z.string(),
  address: z.string(),
  phone: z.string(),
  is_default: z.boolean(),
  warehouse_code: z.string(),
  warehouse_name: z.string(),
};

/** El esquema de React Hook Form: cada error de `branchFormErrors` en su campo. */
export function branchFormSchema({ creating }: { creating: boolean }) {
  return z.object(branchFields).superRefine((values, context) => {
    for (const [field, message] of Object.entries(branchFormErrors(values, { creating }))) {
      context.addIssue({ code: 'custom', path: [field], message });
    }
  });
}

/**
 * El cuerpo de la edición: el formulario completo, con el código en mayúsculas, el teléfono sin espacios ni guiones y los
 * opcionales vacíos en null. `wasDefault`: la por defecto manda siempre `is_default: true` (con `false`, la API responde 422;
 * H4 del diseño).
 */
export function branchFormToPayload(
  values: BranchFormValues,
  { wasDefault = false }: { wasDefault?: boolean } = {},
): BranchPayload {
  return {
    code: normalizeCode(values.code),
    name: values.name.trim(),
    establishment: values.establishment.trim(),
    address: optionalText(values.address),
    phone: optionalText(normalizePhone(values.phone)),
    is_default: wasDefault || values.is_default,
  };
}

/** El cuerpo del alta: la sucursal y su depósito por defecto (`warehouse_name` vacío en null: la API pone "Principal"). */
export function branchFormToCreatePayload(values: BranchFormValues): CreateBranchPayload {
  return {
    ...branchFormToPayload(values),
    warehouse_code: normalizeCode(values.warehouse_code),
    warehouse_name: optionalText(values.warehouse_name),
  };
}

// ---------------------------------------------------------------------------------------------------------------------
// El depósito
// ---------------------------------------------------------------------------------------------------------------------

export interface WarehouseFormValues {
  code: string;
  name: string;
  is_default: boolean;
}

export const EMPTY_WAREHOUSE_FORM: WarehouseFormValues = { code: '', name: '', is_default: false };

export function warehouseToForm(warehouse: Warehouse): WarehouseFormValues {
  return { code: warehouse.code, name: warehouse.name, is_default: warehouse.is_default };
}

export function warehouseFormErrors(
  values: WarehouseFormValues,
): Partial<Record<keyof WarehouseFormValues, string>> {
  return withoutNulls({
    code: codeError(values.code, WAREHOUSE_MESSAGES.code),
    name: nameError(values.name, WAREHOUSE_MESSAGES.name),
  });
}

/** El cuerpo del alta y la edición del depósito; el por defecto manda siempre `is_default: true` (H4). */
export function warehouseFormToPayload(
  values: WarehouseFormValues,
  { wasDefault = false }: { wasDefault?: boolean } = {},
): WarehousePayload {
  return {
    code: normalizeCode(values.code),
    name: values.name.trim(),
    is_default: wasDefault || values.is_default,
  };
}
