import { describe, expect, it } from 'vitest';
import {
  BRANCH_MESSAGES,
  branchFormErrors,
  branchFormToCreatePayload,
  branchFormToPayload,
  EMPTY_BRANCH_FORM,
  normalizeCode,
  WAREHOUSE_MESSAGES,
  warehouseFormErrors,
  warehouseFormToPayload,
  type BranchFormValues,
} from './form';

const VALID: BranchFormValues = {
  ...EMPTY_BRANCH_FORM,
  code: 'SUC-2',
  name: 'Sucursal Norte',
  establishment: '002',
  warehouse_code: 'DEP-2',
};

function errorsOf(values: Partial<BranchFormValues>, creating = true) {
  return branchFormErrors({ ...VALID, ...values }, { creating });
}

describe('branchFormErrors (las reglas de StoreBranchRequest con los mensajes de BranchMessages)', () => {
  it('un formulario válido no tiene errores', () => {
    expect(errorsOf({})).toEqual({});
  });

  it.each([
    ['', BRANCH_MESSAGES.code.required],
    ['   ', BRANCH_MESSAGES.code.required],
    ['A'.repeat(20), null],
    ['A'.repeat(21), BRANCH_MESSAGES.code.max],
    ['suc-2', null],
    ['  suc-2  ', null],
    ['SUC 2', BRANCH_MESSAGES.code.regex],
    ['SUC_2', BRANCH_MESSAGES.code.regex],
    ['SUCÑ', BRANCH_MESSAGES.code.regex],
    ['straße', BRANCH_MESSAGES.code.regex],
  ])('el código %j', (code, expected) => {
    expect(errorsOf({ code }).code ?? null).toBe(expected);
  });

  it.each([
    ['', BRANCH_MESSAGES.name.required],
    ['A', BRANCH_MESSAGES.name.min],
    ['Ab', null],
    ['a'.repeat(100), null],
    ['a'.repeat(101), BRANCH_MESSAGES.name.max],
    ['😀'.repeat(50), BRANCH_MESSAGES.name.regex],
    ['123', BRANCH_MESSAGES.name.regex],
    ['Sucursal (Centro) 2', null],
    ['San Lorenzo / Norte', BRANCH_MESSAGES.name.regex],
  ])('el nombre %j', (name, expected) => {
    expect(errorsOf({ name }).name ?? null).toBe(expected);
  });

  it('el largo del nombre en caracteres, no en unidades de UTF-16 (como mb_strlen)', () => {
    // 100 letras fuera del plano básico: 200 unidades de UTF-16, 100 caracteres.
    expect(errorsOf({ name: '𝒜'.repeat(100) }).name).toBeUndefined();
    expect(errorsOf({ name: '𝒜'.repeat(101) }).name).toBe(BRANCH_MESSAGES.name.max);
  });

  it.each([
    ['', BRANCH_MESSAGES.establishment.required],
    ['001', null],
    ['999', null],
    ['000', BRANCH_MESSAGES.establishment.regex],
    ['01', BRANCH_MESSAGES.establishment.regex],
    ['0001', BRANCH_MESSAGES.establishment.regex],
    ['1a2', BRANCH_MESSAGES.establishment.regex],
    [' 002 ', null],
  ])('el establecimiento %j', (establishment, expected) => {
    expect(errorsOf({ establishment }).establishment ?? null).toBe(expected);
  });

  it.each([
    ['', null],
    ['Av. Mcal. López 1234, piso 2º', null],
    ['a'.repeat(255), null],
    ['a'.repeat(256), BRANCH_MESSAGES.address.max],
    ['Calle <b>', BRANCH_MESSAGES.address.regex],
  ])('la dirección %j', (address, expected) => {
    expect(errorsOf({ address }).address ?? null).toBe(expected);
  });

  it.each([
    ['', null],
    ['+595211100001', null],
    ['0981 123-456', BRANCH_MESSAGES.phone.regex],
    ['981 123 456', null],
    ['1234567', BRANCH_MESSAGES.phone.regex],
    ['123456789012345', null],
    ['1234567890123456', BRANCH_MESSAGES.phone.regex],
  ])('el teléfono %j', (phone, expected) => {
    expect(errorsOf({ phone }).phone ?? null).toBe(expected);
  });

  it('el depósito en el alta: código obligatorio; el nombre, opcional y con sus reglas', () => {
    expect(errorsOf({ warehouse_code: '' }).warehouse_code).toBe(
      BRANCH_MESSAGES.warehouse_code.required,
    );
    expect(errorsOf({ warehouse_name: '' }).warehouse_name).toBeUndefined();
    expect(errorsOf({ warehouse_name: 'A' }).warehouse_name).toBe(
      BRANCH_MESSAGES.warehouse_name.min,
    );
    expect(BRANCH_MESSAGES.warehouse_code.required).toBe('El código del depósito es obligatorio.');
  });

  it('la edición no controla los campos del depósito', () => {
    expect(errorsOf({ warehouse_code: '' }, false)).toEqual({});
  });
});

describe('los cuerpos de la sucursal', () => {
  it('el alta: los códigos en mayúsculas, el teléfono limpio, los opcionales vacíos en null', () => {
    expect(
      branchFormToCreatePayload({
        ...VALID,
        code: ' suc-2 ',
        name: ' Sucursal Norte ',
        phone: '+595 21 110-0001',
        warehouse_code: 'dep-2',
      }),
    ).toEqual({
      code: 'SUC-2',
      name: 'Sucursal Norte',
      establishment: '002',
      address: null,
      phone: '+595211100001',
      is_default: false,
      warehouse_code: 'DEP-2',
      warehouse_name: null,
    });
  });

  it('la por defecto manda siempre is_default: true (H4)', () => {
    expect(
      branchFormToPayload({ ...VALID, is_default: false }, { wasDefault: true }).is_default,
    ).toBe(true);
    expect(branchFormToPayload({ ...VALID, is_default: false }).is_default).toBe(false);
    expect(branchFormToPayload({ ...VALID, is_default: true }).is_default).toBe(true);
  });

  it('normalizeCode pasa a mayúsculas solo las letras ASCII, como strtoupper de PHP', () => {
    expect(normalizeCode(' cen-1 ')).toBe('CEN-1');
    expect(normalizeCode('ñandú')).toBe('ñANDú');
  });
});

describe('el depósito', () => {
  it('las reglas con los mensajes del depósito', () => {
    expect(warehouseFormErrors({ code: '', name: '', is_default: false })).toEqual({
      code: WAREHOUSE_MESSAGES.code.required,
      name: WAREHOUSE_MESSAGES.name.required,
    });
    expect(warehouseFormErrors({ code: 'dep-2', name: 'Farmacia', is_default: false })).toEqual({});
  });

  it('el cuerpo: el código en mayúsculas y el por defecto siempre con is_default: true', () => {
    expect(
      warehouseFormToPayload({ code: ' dep-2 ', name: ' Farmacia ', is_default: false }),
    ).toEqual({ code: 'DEP-2', name: 'Farmacia', is_default: false });
    expect(
      warehouseFormToPayload(
        { code: 'DEP', name: 'Principal', is_default: false },
        { wasDefault: true },
      ).is_default,
    ).toBe(true);
  });
});
