import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { VACCINE } from '../../test/services';
import {
  EMPTY_SERVICE_FORM,
  normalizePrice,
  priceToForm,
  SERVICE_MESSAGES,
  serviceFormErrors,
  serviceFormToPayload,
  serviceToForm,
  wholeAmount,
  type ServiceFormValues,
} from './form';
import { SERVICE_CATEGORIES } from './types';

const M = SERVICE_MESSAGES;

const values = (overrides: Partial<ServiceFormValues> = {}): ServiceFormValues => ({
  ...EMPTY_SERVICE_FORM,
  name: 'Consulta general',
  category: 'consultation',
  standard_price: '150.000',
  ...overrides,
});

const errorsOf = (overrides: Partial<ServiceFormValues>) => serviceFormErrors(values(overrides));

describe('las reglas del servicio (ServiceMessages y WholeAmount)', () => {
  it('el formulario completo pasa; el vacío pide nombre, categoría y precio', () => {
    expect(serviceFormErrors(values())).toEqual({});
    expect(serviceFormErrors(EMPTY_SERVICE_FORM)).toEqual({
      name: M.name.required,
      category: M.category.required,
      standard_price: M.standard_price.required,
    });
  });

  it.each([
    ['Vacuna 5+1'],
    ['Castración macho/hembra'],
    ["Control 50% (2.ª dosis), d'Artagnan - perro"],
    ['Ñu'],
    ['  Consulta  '],
    ['a'.repeat(120)],
  ])('el nombre %j pasa', (name) => {
    expect(errorsOf({ name }).name).toBeUndefined();
  });

  it.each([
    ['', M.name.required],
    ['   ', M.name.required],
    ['a', M.name.min],
    ['a'.repeat(121), M.name.max],
    ['a//b', M.name.regex],
    ['123', M.name.regex],
    ['Vacuna #1', M.name.regex],
    ['Vacuna: anual', M.name.regex],
  ])('el nombre %j: %s', (name, message) => {
    expect(errorsOf({ name }).name).toBe(message);
  });

  it('el largo del nombre se cuenta en caracteres, como mb_strlen: 120 con acentos pasan', () => {
    expect(errorsOf({ name: `${'á'.repeat(119)}ñ` }).name).toBeUndefined();
  });

  it.each([['5'], ['30'], ['720'], ['+30'], [' 60 ']])('la duración %j pasa', (duration) => {
    expect(errorsOf({ duration_minutes: duration }).duration_minutes).toBeUndefined();
  });

  it.each([
    ['', M.duration_minutes.required],
    ['4', M.duration_minutes.between],
    ['0', M.duration_minutes.between],
    ['-5', M.duration_minutes.between],
    ['722', M.duration_minutes.between],
    ['725', M.duration_minutes.between],
    ['7', M.duration_minutes.multiple_of],
    ['718', M.duration_minutes.multiple_of],
    ['30.5', M.duration_minutes.integer],
    ['30,0', M.duration_minutes.integer],
    ['treinta', M.duration_minutes.integer],
  ])('la duración %j: %s', (duration, message) => {
    expect(errorsOf({ duration_minutes: duration }).duration_minutes).toBe(message);
  });

  it.each([
    ['150.000', '150000'],
    ['150000', '150000'],
    ['0', '0'],
    ['12.500.000.000', '12500000000'],
    ['999.999.999.999', '999999999999'],
    ['999999999999', '999999999999'],
    ['007', '7'],
    [' 150.000 ', '150000'],
  ])('el precio %j pasa y se manda %j', (price, sent) => {
    expect(errorsOf({ standard_price: price }).standard_price).toBeUndefined();
    expect(serviceFormToPayload(values({ standard_price: price })).standard_price).toBe(sent);
  });

  it.each([
    ['150000,50'],
    ['150.000,00'],
    ['150000.00'],
    ['1500.00'],
    ['-1'],
    ['+1'],
    ['1e5'],
    ['1.0000'],
    ['15.00.000'],
    ['1 000'],
    ['Gs. 150.000'],
    ['1.000.000.000.000'],
    ['1000000000000'],
  ])('el precio %j se rechaza con el mensaje de WholeAmount', (price) => {
    expect(errorsOf({ standard_price: price }).standard_price).toBe(M.standard_price.whole);
  });

  it('los ceros de adelante no cuentan para los 12 dígitos, como la API', () => {
    expect(wholeAmount(normalizePrice('0000999999999999'))).toBe('999999999999');
  });

  it.each([['0'], ['5'], ['10']])('el IVA %s pasa; otro, no', (rate) => {
    expect(errorsOf({ tax_rate: rate }).tax_rate).toBeUndefined();
    expect(errorsOf({ tax_rate: '21' }).tax_rate).toBe(M.tax_rate.required);
  });

  it('la descripción: 1000 caracteres pasan, 1001 no', () => {
    expect(errorsOf({ description: 'x'.repeat(1000) }).description).toBeUndefined();
    expect(errorsOf({ description: 'x'.repeat(1001) }).description).toBe(M.description.max);
  });

  it('una categoría desconocida se rechaza', () => {
    expect(errorsOf({ category: 'dentistry' }).category).toBe(M.category.required);
  });
});

describe('el cuerpo que se manda', () => {
  it('el precio como texto de solo dígitos, la duración y el IVA como enteros, la descripción vacía en null', () => {
    const payload = serviceFormToPayload(
      values({
        name: '  Vacuna 5+1 ',
        category: 'vaccination',
        standard_price: '150.000',
        duration_minutes: '45',
        tax_rate: '5',
        description: '   ',
      }),
    );

    expect(payload).toEqual({
      name: 'Vacuna 5+1',
      category: 'vaccination',
      duration_minutes: 45,
      standard_price: '150000',
      tax_rate: 5,
      description: null,
    });
    expect(typeof payload.standard_price).toBe('string');
    expect(typeof payload.tax_rate).toBe('number');
    expect(Object.keys(payload)).not.toContain('code');
    expect(Object.keys(payload)).not.toContain('status');
  });
});

describe('del detalle al formulario', () => {
  it.each([
    ['150000.00', '150.000'],
    ['0.00', '0'],
    ['999999999999.00', '999.999.999.999'],
    ['1500', '1.500'],
    ['1500.50', '1500.50'],
  ])('el precio %j se muestra %j (como texto, sin pasar por un número)', (price, shown) => {
    expect(priceToForm(price)).toBe(shown);
  });

  it('el servicio guardado como valores del formulario', () => {
    expect(serviceToForm(VACCINE)).toEqual({
      name: 'Vacuna 5+1',
      category: 'vaccination',
      duration_minutes: '30',
      standard_price: '150.000',
      tax_rate: '10',
      description: 'Séxtuple para perros.',
    });
  });
});

describe('las categorías (H2: sin endpoint)', () => {
  it('son las de App\\Enums\\ServiceCategory, en su orden y con el texto de label()', () => {
    const php = readFileSync(
      join(process.cwd(), '..', 'backend', 'app', 'Enums', 'ServiceCategory.php'),
      'utf8',
    );
    const cases = new Map(
      Array.from(php.matchAll(/case (\w+) = '([a-z_]+)';/g), (match) => [match[1], match[2]]),
    );
    const labels = new Map(
      Array.from(php.matchAll(/self::(\w+) => '([^']+)'/g), (match) => [match[1], match[2]]),
    );
    const backend = Array.from(cases, ([name, value]) => ({ value, label: labels.get(name) }));

    expect(backend.length).toBeGreaterThan(0);
    expect(SERVICE_CATEGORIES.map((category) => ({ ...category }))).toEqual(backend);
  });
});
