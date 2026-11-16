import { describe, expect, it } from 'vitest';
import { SPECIES } from '../../test/patients';
import { breedOptions, speciesOptions } from './species-options';

describe('speciesOptions', () => {
  it('las activas, con "Elegí la especie" primero', () => {
    expect(speciesOptions(SPECIES).map((option) => option.label)).toEqual([
      'Elegí la especie',
      'Canino',
      'Felino',
      'Ave',
    ]);
  });

  it('en la edición, la especie inactiva del paciente se suma con "(inactivada)"; una activa no se repite', () => {
    const inactive = speciesOptions(SPECIES, { species: { id: 9, name: 'Reptil' }, breed: null });
    const active = speciesOptions(SPECIES, { species: { id: 1, name: 'Canino' }, breed: null });

    expect(inactive.at(-1)).toEqual({ value: '9', label: 'Reptil (inactivada)' });
    expect(active).toHaveLength(4);
  });
});

describe('breedOptions', () => {
  it('las razas de la especie elegida, con "Sin raza" primero', () => {
    expect(breedOptions(SPECIES, '4').map((option) => option.label)).toEqual([
      'Sin raza',
      'Siamés',
    ]);
  });

  it('sin especie, o una especie sin razas: solo "Sin raza"', () => {
    expect(breedOptions(SPECIES, '')).toEqual([{ value: '', label: 'Sin raza' }]);
    expect(breedOptions(SPECIES, '6')).toEqual([{ value: '', label: 'Sin raza' }]);
  });

  it('la raza inactiva del paciente, solo en su especie', () => {
    const current = { species: { id: 1, name: 'Canino' }, breed: { id: 8, name: 'Dogo' } };

    expect(breedOptions(SPECIES, '1', current).at(-1)).toEqual({
      value: '8',
      label: 'Dogo (inactivada)',
    });
    expect(breedOptions(SPECIES, '4', current).map((option) => option.value)).toEqual(['', '5']);
  });

  it('una especie inactiva del paciente: su raza se ofrece (la especie no viene en GET species)', () => {
    const current = { species: { id: 9, name: 'Reptil' }, breed: { id: 10, name: 'Iguana verde' } };

    expect(breedOptions(SPECIES, '9', current)).toEqual([
      { value: '', label: 'Sin raza' },
      { value: '10', label: 'Iguana verde (inactivada)' },
    ]);
  });
});
