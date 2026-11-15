import { queryOptions, type QueryClient } from '@tanstack/react-query';
import { api } from '../../lib/api/client';
import { patientKeys, speciesKeys } from '../patients/api';
import type { Breed, Species } from '../patients/types';

/**
 * El catálogo de especies y razas (1.5). Las claves son las de `speciesKeys` (patients/api.ts), que ya usan los selectores
 * del paciente: el catálogo es de la clínica, no de la sucursal.
 */

/**
 * GET species con sus razas. Quien administra el catálogo (`species.store`, `.update` o `.destroy`) también ve las inactivas
 * (`include_inactive=1`); el recepcionista, solo las activas.
 */
export function catalogQueryOptions(includeInactive: boolean) {
  return queryOptions({
    queryKey: speciesKeys.list({ include_inactive: includeInactive }),
    queryFn: async ({ signal }) =>
      (
        await api.get<Species[]>('species', {
          signal,
          query: { include_inactive: includeInactive ? 1 : null },
        })
      ).data,
  });
}

export interface CatalogPayload {
  name?: string;
  is_active?: boolean;
}

/** POST species (201, la especie con sus razas). */
export function createSpecies(payload: CatalogPayload) {
  return api.post<Species>('species', payload);
}

/** PATCH species/{id}: el nombre o `is_active` (200). Una global responde 403 CATALOG_GLOBAL_NOT_EDITABLE. */
export function updateSpecies(id: number, payload: CatalogPayload) {
  return api.patch<Species>(`species/${String(id)}`, payload);
}

/** DELETE species/{id} (200, sin datos); en uso, 409 SPECIES_IN_USE. */
export function deleteSpecies(id: number) {
  return api.delete<null>(`species/${String(id)}`);
}

/** POST species/{species}/breeds (201): la especie sale de la URL; también en una especie global. */
export function createBreed(speciesId: number, payload: CatalogPayload) {
  return api.post<Breed>(`species/${String(speciesId)}/breeds`, payload);
}

/** PATCH species/{species}/breeds/{breed}: el nombre o `is_active` (200); la raza no cambia de especie. */
export function updateBreed(breed: Pick<Breed, 'id' | 'species_id'>, payload: CatalogPayload) {
  return api.patch<Breed>(
    `species/${String(breed.species_id)}/breeds/${String(breed.id)}`,
    payload,
  );
}

/** DELETE species/{species}/breeds/{breed} (200, sin datos); en uso, 409 BREED_IN_USE. */
export function deleteBreed(breed: Pick<Breed, 'id' | 'species_id'>) {
  return api.delete<null>(`species/${String(breed.species_id)}/breeds/${String(breed.id)}`);
}

/**
 * Después de cualquier cambio del catálogo (diseño de la F1, "Invalidaciones"): el catálogo se vuelve a pedir (está a la
 * vista) y las consultas de pacientes quedan viejas (muestran el nombre de la especie y la raza), sin pedirse hasta mostrarse.
 */
export function afterCatalogChanged(queryClient: QueryClient) {
  void queryClient.invalidateQueries({ queryKey: speciesKeys.all });
  void queryClient.invalidateQueries({ queryKey: patientKeys.all, refetchType: 'none' });
}
