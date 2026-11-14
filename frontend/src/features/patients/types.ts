import type { ClientPatient, LabeledValue } from '../clients/types';

/** Los pacientes como los manda la API (PatientResource y ShowPatientResource), con sus nombres en snake_case. */

export type PatientSex = 'male' | 'female' | 'unknown';

/** Un ítem de GET patients: PatientResource con el dueño (front-01, R-065). */
export interface Patient extends ClientPatient {
  client: { id: number; display_name: string } | null;
}

/** GET patients/{id} (ShowPatientResource): el dueño más completo y los datos que el listado no trae. */
export interface PatientDetail extends ClientPatient {
  client: {
    id: number;
    display_name: string;
    phone: string | null;
    email: string | null;
    /** El dueño se eliminó (la relación es withTrashed): se muestra igual. */
    deleted: boolean;
  } | null;
  color: string | null;
  allergies: string | null;
  notes: string | null;
  /** UTC con `Z`, o null. */
  deceased_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

/** Una raza de GET species (BreedResource). */
export interface Breed {
  id: number;
  species_id: number;
  name: string;
  is_active: boolean;
  is_global: boolean;
}

/** Una especie de GET species (SpeciesResource), con sus razas (activas, sin include_inactive). */
export interface Species {
  id: number;
  name: string;
  is_active: boolean;
  is_global: boolean;
  breeds: Breed[];
}

/** Los filtros de GET patients, con los nombres de la API (la clave de la consulta). */
export interface PatientListFilters {
  search: string;
  species_id: number | null;
  include_deceased: boolean;
  page: number;
}

/**
 * El cuerpo de POST clients/{client}/patients y PATCH patients/{id}: el formulario completo (la edición también, la API lo
 * admite y compara). Los opcionales vacíos van en null; el peso, como texto con punto ("12.5").
 */
export interface PatientPayload {
  name: string;
  species_id: number;
  breed_id: number | null;
  sex: PatientSex;
  is_neutered: boolean;
  birth_date: string | null;
  birth_date_estimated: boolean;
  color: string | null;
  weight_kg: string | null;
  microchip: string | null;
  allergies: string | null;
  notes: string | null;
}

export type { LabeledValue };
