import { afterEach, describe, expect, it, vi } from 'vitest';
import { patientDetail } from '../../test/patients';
import {
  EMPTY_PATIENT_FORM,
  formatWeight,
  PATIENT_MESSAGES,
  patientFormErrors,
  patientFormSchema,
  patientFormToPayload,
  patientToForm,
  type PatientFormValues,
} from './form';

afterEach(() => {
  vi.useRealTimers();
});

const TODAY = '2026-11-14';

const VALID: PatientFormValues = { ...EMPTY_PATIENT_FORM, name: 'Toby', species_id: '1' };

function errorsOf(values: Partial<PatientFormValues>) {
  return patientFormErrors({ ...VALID, ...values }, TODAY);
}

const m = PATIENT_MESSAGES;

describe('patientFormErrors: las reglas de StorePatientRequest, con sus mensajes', () => {
  it('un paciente con solo el nombre y la especie no tiene errores', () => {
    expect(errorsOf({})).toEqual({});
  });

  it('un paciente completo no tiene errores', () => {
    expect(
      errorsOf({
        name: "Toby D'Artagnan Jr.",
        breed_id: '2',
        sex: 'male',
        is_neutered: true,
        birth_date: '2020-02-29',
        birth_date_estimated: true,
        color: 'Marrón, blanco y gris-azulado',
        weight_kg: '12,5',
        microchip: 'ABC985112003456789',
        allergies: 'Penicilina; pulgas (dermatitis).',
        notes: 'Muerde al cortarle las uñas!',
      }),
    ).toEqual({});
  });

  it.each([
    ['name', '', m.name.required],
    ['name', '   ', m.name.required],
    ['name', 'Toby!', m.name.regex],
    ['name', 'Toby_2', m.name.regex],
    ['species_id', '', m.species_id.required],
    ['birth_date', '2023-02-29', m.birth_date.date_format],
    ['birth_date', '2023-13-01', m.birth_date.date_format],
    ['birth_date', '14/11/2026', m.birth_date.date_format],
    ['color', 'Marrón 2', m.color.regex],
    ['color', 'Negro/blanco', m.color.regex],
    ['weight_kg', 'doce', m.weight_kg.numeric],
    ['weight_kg', '12,5,3', m.weight_kg.numeric],
    ['weight_kg', '-3', m.weight_kg.min],
    ['weight_kg', '0', m.weight_kg.min],
    ['weight_kg', '12,555', m.weight_kg.decimal],
    // 1e3 es un número para PHP (numeric pasa) pero no para la regla decimal: el mensaje de la API es el de decimal.
    ['weight_kg', '1e3', m.weight_kg.decimal],
    ['microchip', 'ABC 123', m.microchip.regex],
    ['microchip', 'ABC-123', m.microchip.regex],
  ] as const)('%s = %j: %s', (field, value, message) => {
    expect(errorsOf({ [field]: value })[field]).toBe(message);
  });

  it('el nombre puede tener letras, números, espacios, puntos, guiones y apóstrofos', () => {
    expect(errorsOf({ name: "Ñandú 2do. O'Neil-Peña" })).toEqual({});
  });

  it('la raza y el sexo no tienen reglas de forma (la raza la filtra el selector; el resto lo dice la API)', () => {
    expect(errorsOf({ breed_id: '', sex: 'unknown' })).toEqual({});
  });

  it('el peso con coma o con punto, con signo + o sin ceros adelante, como lo acepta la API', () => {
    for (const weight of ['12,5', '12.5', '12', '0,01', '.5', '+3', '12.', '2000', '2000,00']) {
      expect(errorsOf({ weight_kg: weight }), weight).toEqual({});
    }
  });
});

describe('patientFormErrors: los límites de cada campo (pasa en el límite y falla pasándolo)', () => {
  const chars = (count: number, char = 'a') => char.repeat(count);

  it.each([
    ['name', chars(100), chars(101), m.name.max],
    ['color', chars(80), chars(81), m.color.max],
    ['microchip', chars(30, '9'), chars(31, '9'), m.microchip.max],
    ['allergies', chars(1000), chars(1001), m.allergies.max],
    ['notes', chars(1000), chars(1001), m.notes.max],
    ['weight_kg', '0,01', '0,009', m.weight_kg.min],
    ['weight_kg', '2000', '2000,01', m.weight_kg.max],
    ['weight_kg', '1,25', '1,255', m.weight_kg.decimal],
    ['birth_date', TODAY, '2026-11-15', m.birth_date.before_or_equal],
  ] as const)('%s', (field, atLimit, past, message) => {
    expect(errorsOf({ [field]: atLimit })[field]).toBeUndefined();
    expect(errorsOf({ [field]: past })[field]).toBe(message);
  });

  it('el largo se cuenta en caracteres, como mb_strlen (una letra con tilde o un emoji es uno)', () => {
    expect(errorsOf({ name: 'ñ'.repeat(100) }).name).toBeUndefined();
    expect(errorsOf({ allergies: '🐶'.repeat(1000) }).allergies).toBeUndefined();
    expect(errorsOf({ allergies: '🐶'.repeat(1001) }).allergies).toBe(m.allergies.max);
  });

  it('los espacios de los extremos no cuentan (TrimStrings de la API)', () => {
    expect(errorsOf({ name: `  ${chars(100)}  `, microchip: ' ABC123 ' })).toEqual({});
  });
});

describe('la fecha de nacimiento no puede ser futura en Asunción, con la PC en UTC', () => {
  it('a las 22:30 del 13/11 en Asunción (ya es 14/11 en UTC), el 14/11 es mañana: se rechaza', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-11-14T01:30:00Z'));

    const result = patientFormSchema.safeParse({ ...VALID, birth_date: '2026-11-14' });
    const today = patientFormSchema.safeParse({ ...VALID, birth_date: '2026-11-13' });

    expect(result.success).toBe(false);
    expect(result.error?.issues).toEqual([
      expect.objectContaining({ path: ['birth_date'], message: m.birth_date.before_or_equal }),
    ]);
    expect(today.success).toBe(true);
  });
});

describe('patientFormToPayload: el cuerpo que se manda', () => {
  it('los ids como números, el peso como texto con punto, la fecha tal cual y los vacíos en null', () => {
    expect(
      patientFormToPayload({
        ...VALID,
        name: '  Toby ',
        breed_id: '2',
        birth_date: '2020-02-29',
        weight_kg: ' 12,5 ',
        microchip: ' ABC123 ',
        color: '  ',
        notes: ' Tranquilo. ',
      }),
    ).toEqual({
      name: 'Toby',
      species_id: 1,
      breed_id: 2,
      sex: 'unknown',
      is_neutered: false,
      birth_date: '2020-02-29',
      birth_date_estimated: false,
      color: null,
      weight_kg: '12.5',
      microchip: 'ABC123',
      allergies: null,
      notes: 'Tranquilo.',
    });
  });

  it('"Sin raza", sin fecha y sin peso: null', () => {
    expect(patientFormToPayload(VALID)).toMatchObject({
      breed_id: null,
      birth_date: null,
      weight_kg: null,
    });
  });
});

describe('patientToForm y formatWeight', () => {
  it('los datos guardados como valores del formulario: los ids como texto, el peso con coma y los null vacíos', () => {
    expect(patientToForm(patientDetail({ weight_kg: '12.50', notes: null }))).toEqual({
      name: 'Firulais',
      species_id: '1',
      breed_id: '2',
      sex: 'male',
      is_neutered: false,
      birth_date: '2022-03-10',
      birth_date_estimated: false,
      color: 'Marrón',
      weight_kg: '12,50',
      microchip: '985112003456789',
      allergies: '',
      notes: '',
    });
  });

  it('el peso guardado vuelve a mandarse tal cual vino ("12.50"): la API lo compara con el cast (H5)', () => {
    const form = patientToForm(patientDetail({ weight_kg: '12.50' }));

    expect(patientFormToPayload(form).weight_kg).toBe('12.50');
  });

  it.each([
    ['12.50', '12,5 kg'],
    ['12.00', '12 kg'],
    ['0.05', '0,05 kg'],
    ['2000.00', '2000 kg'],
    [null, ''],
    ['abc', ''],
  ] as const)('formatWeight(%j) = %j', (weight, expected) => {
    expect(formatWeight(weight)).toBe(expected);
  });
});
