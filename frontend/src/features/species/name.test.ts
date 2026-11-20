import { describe, expect, it } from 'vitest';
import { nameMessages, parseCatalogName, type CatalogKind } from './name';

const KINDS: [CatalogKind, number][] = [
  ['species', 60],
  ['breed', 80],
];

describe('parseCatalogName: las reglas de StoreSpeciesRequest y StoreBreedRequest', () => {
  it.each(KINDS)('%s: vacío o solo espacios es obligatorio', (kind) => {
    expect(parseCatalogName(kind, '')).toEqual({ ok: false, error: nameMessages(kind).required });
    expect(parseCatalogName(kind, '   ')).toEqual({
      ok: false,
      error: nameMessages(kind).required,
    });
  });

  it.each(KINDS)(
    '%s: 2 caracteres pasan, 1 no (sin contar los espacios de los extremos)',
    (kind) => {
      expect(parseCatalogName(kind, ' Ñu ')).toEqual({ ok: true, name: 'Ñu' });
      expect(parseCatalogName(kind, ' A ')).toEqual({ ok: false, error: nameMessages(kind).min });
    },
  );

  it.each(KINDS)('%s: el máximo en caracteres pasa, uno más no', (kind, max) => {
    expect(parseCatalogName(kind, 'Á'.repeat(max))).toEqual({ ok: true, name: 'Á'.repeat(max) });
    expect(parseCatalogName(kind, 'Á'.repeat(max + 1))).toEqual({
      ok: false,
      error: nameMessages(kind).max,
    });
  });

  it('el largo es en caracteres, como mb_strlen: una letra fuera del plano básico cuenta 1', () => {
    expect(parseCatalogName('species', '𝒜'.repeat(60)).ok).toBe(true);
  });

  it.each([
    'Pastor Alemán',
    'Cuarto de Milla',
    'Shih Tzu',
    "Jack Russell's",
    'Raza (mini)',
    'Tipo-2',
    'Dr. Who',
  ])('"%s" es un nombre válido', (name) => {
    expect(parseCatalogName('breed', name)).toEqual({ ok: true, name });
  });

  it.each(['--', '12', '()', 'Perro/Gato', 'Gato!', 'Ave_rara'])(
    '"%s": al menos una letra y solo los caracteres permitidos',
    (name) => {
      expect(parseCatalogName('species', name)).toEqual({
        ok: false,
        error: nameMessages('species').pattern,
      });
    },
  );

  it('los mensajes son los de la API', () => {
    expect(nameMessages('species')).toEqual({
      required: 'El nombre de la especie es obligatorio.',
      min: 'El nombre de la especie debe tener al menos 2 caracteres.',
      max: 'El nombre de la especie no debe tener más de 60 caracteres.',
      pattern:
        'El nombre de la especie debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.',
    });
    expect(nameMessages('breed').max).toBe(
      'El nombre de la raza no debe tener más de 80 caracteres.',
    );
  });
});

describe('deuda 28: los invisibles de los bordes se recortan como en la API (trimLikeApi)', () => {
  it('"\\u200BA" tiene un solo carácter para la API', () => {
    expect(parseCatalogName('species', '\u200BA')).toEqual({
      ok: false,
      error: nameMessages('species').min,
    });
  });
});
