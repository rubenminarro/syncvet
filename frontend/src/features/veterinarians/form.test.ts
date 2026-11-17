import { describe, expect, it } from 'vitest';
import {
  EMPTY_VETERINARIAN_FORM,
  formatCommission,
  veterinarianFormErrors,
  veterinarianFormToCreatePayload,
  veterinarianFormToPayload,
  veterinarianToForm,
  VETERINARIAN_MESSAGES as M,
  type VeterinarianFormValues,
} from './form';
import type { VeterinarianDetail } from './types';

const values = (overrides: Partial<VeterinarianFormValues> = {}): VeterinarianFormValues => ({
  ...EMPTY_VETERINARIAN_FORM,
  user_id: '8',
  ...overrides,
});

const errorsOf = (overrides: Partial<VeterinarianFormValues>, withUser = true) =>
  veterinarianFormErrors(values(overrides), withUser);

describe('las reglas de VeterinarianMessages, en su borde', () => {
  it('el formulario mínimo del alta (solo el usuario) pasa', () => {
    expect(errorsOf({})).toEqual({});
  });

  it('el usuario es obligatorio en el alta, no en la edición', () => {
    expect(errorsOf({ user_id: '' }).user_id).toBe(M.user_id.required);
    expect(errorsOf({ user_id: '' }, false).user_id).toBeUndefined();
  });

  it('la matrícula: 30 caracteres pasan, 31 no (sin contar los espacios de los extremos)', () => {
    expect(errorsOf({ license_number: ` ${'M'.repeat(30)} ` }).license_number).toBeUndefined();
    expect(errorsOf({ license_number: 'M'.repeat(31) }).license_number).toBe(M.license_number.max);
  });

  it('la especialidad: 100 caracteres pasan (contados como la API, un emoji es uno), 101 no', () => {
    expect(errorsOf({ specialty: '🐾'.repeat(100) }).specialty).toBeUndefined();
    expect(errorsOf({ specialty: 'a'.repeat(101) }).specialty).toBe(M.specialty.max);
  });

  it('las notas: 1000 pasan, 1001 no', () => {
    expect(errorsOf({ notes: 'a'.repeat(1000) }).notes).toBeUndefined();
    expect(errorsOf({ notes: 'a'.repeat(1001) }).notes).toBe(M.notes.max);
  });

  it.each([[''], ['0'], ['100'], ['12,5'], ['12.50'], ['0,01'], [' 99,99 ']])(
    'la comisión %s pasa',
    (commission) => {
      expect(errorsOf({ commission_percentage: commission }).commission_percentage).toBeUndefined();
    },
  );

  it.each([
    ['100,01', M.commission_percentage.between],
    ['-0,01', M.commission_percentage.between],
    ['12,555', M.commission_percentage.decimal],
    ['doce', M.commission_percentage.numeric],
    ['12,5,1', M.commission_percentage.numeric],
  ])('la comisión %s: %s', (commission, message) => {
    expect(errorsOf({ commission_percentage: commission }).commission_percentage).toBe(message);
  });
});

describe('el cuerpo que se manda', () => {
  it('la matrícula en mayúsculas y sin espacios, la comisión con punto y los vacíos en null', () => {
    expect(
      veterinarianFormToCreatePayload(
        values({ license_number: '  mv-1001 ', commission_percentage: '12,5', specialty: ' ' }),
      ),
    ).toEqual({
      user_id: 8,
      license_number: 'MV-1001',
      specialty: null,
      commission_percentage: '12.5',
      notes: null,
    });
  });

  it('la comisión vacía va como 0', () => {
    expect(veterinarianFormToPayload(values()).commission_percentage).toBe('0');
  });

  it('la edición no lleva user_id, code ni status', () => {
    const payload = veterinarianFormToPayload(values({ notes: ' Guardia ' }));

    expect(Object.keys(payload).sort()).toEqual([
      'commission_percentage',
      'license_number',
      'notes',
      'specialty',
    ]);
    expect(payload.notes).toBe('Guardia');
  });
});

describe('la comisión en pantalla', () => {
  it('del detalle al formulario, con coma', () => {
    const detail = {
      user: { id: 8 },
      license_number: null,
      specialty: null,
      commission_percentage: '12.50',
      notes: null,
    } as unknown as VeterinarianDetail;

    expect(veterinarianToForm(detail)).toEqual({
      user_id: '8',
      license_number: '',
      specialty: '',
      commission_percentage: '12,50',
      notes: '',
    });
  });

  it.each([
    ['12.50', '12,5 %'],
    ['0.00', '0 %'],
    ['100.00', '100 %'],
    ['7.05', '7,05 %'],
    [null, ''],
    ['x', ''],
  ])('%s se muestra "%s"', (commission, text) => {
    expect(formatCommission(commission)).toBe(text);
  });
});
