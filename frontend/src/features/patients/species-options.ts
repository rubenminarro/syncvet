import type { SelectOption } from '../../components/ui';
import type { Species } from './types';

/** La especie y la raza que el paciente ya tiene (en la edición): se ofrecen aunque estén inactivas. */
export interface CurrentCatalog {
  species: { id: number; name: string } | null;
  breed: { id: number; name: string } | null;
}

const INACTIVE = ' (inactivada)';

/**
 * Las especies del selector: las activas de GET species y, en la edición, la del paciente si ya no está entre ellas (se
 * inactivó), con "(inactivada)": la API la acepta solo si no cambia (backend/CLAUDE.md §6.2).
 */
export function speciesOptions(
  species: readonly Species[],
  current?: CurrentCatalog,
): SelectOption[] {
  const options = species.map((item) => ({ value: String(item.id), label: item.name }));
  const own = current?.species;

  if (own != null && !species.some((item) => item.id === own.id)) {
    options.push({ value: String(own.id), label: `${own.name}${INACTIVE}` });
  }

  return [{ value: '', label: 'Elegí la especie' }, ...options];
}

/**
 * Las razas de la especie elegida ("Sin raza" primero, que se manda como null): las activas de esa especie y, si la especie
 * es la del paciente, su raza aunque se haya inactivado, con "(inactivada)". Sin especie, solo "Sin raza".
 */
export function breedOptions(
  species: readonly Species[],
  speciesId: string,
  current?: CurrentCatalog,
): SelectOption[] {
  const none = { value: '', label: 'Sin raza' };

  if (speciesId === '') {
    return [none];
  }

  const breeds = species.find((item) => String(item.id) === speciesId)?.breeds ?? [];
  const options = breeds.map((breed) => ({ value: String(breed.id), label: breed.name }));
  const own = current?.breed;

  if (
    own != null &&
    current?.species != null &&
    String(current.species.id) === speciesId &&
    !breeds.some((breed) => breed.id === own.id)
  ) {
    options.push({ value: String(own.id), label: `${own.name}${INACTIVE}` });
  }

  return [none, ...options];
}
