import type { Patient, PatientDetail, Species } from '../features/patients/types';
import { patient } from './clients';

/**
 * Pacientes y especies de prueba, como los manda la API (PatientResource con el dueño, ShowPatientResource y
 * SpeciesResource con sus razas). Los dueños son los del DemoSeeder.
 */

/** GET species: las activas con sus razas activas. "Ave" no tiene razas. */
export const SPECIES: Species[] = [
  {
    id: 1,
    name: 'Canino',
    is_active: true,
    is_global: true,
    breeds: [
      { id: 2, species_id: 1, name: 'Mestizo', is_active: true, is_global: true },
      { id: 3, species_id: 1, name: 'Labrador Retriever', is_active: true, is_global: true },
    ],
  },
  {
    id: 4,
    name: 'Felino',
    is_active: true,
    is_global: true,
    breeds: [{ id: 5, species_id: 4, name: 'Siamés', is_active: true, is_global: true }],
  },
  { id: 6, name: 'Ave', is_active: true, is_global: false, breeds: [] },
];

export function listPatient(overrides: Partial<Patient> = {}): Patient {
  return {
    ...patient(),
    client: { id: 1, display_name: 'Laura Martínez' },
    ...overrides,
  };
}

export function patientDetail(overrides: Partial<PatientDetail> = {}): PatientDetail {
  return {
    ...patient({ microchip: '985112003456789' }),
    client: {
      id: 1,
      display_name: 'Laura Martínez',
      phone: '+595981100001',
      email: 'laura.martinez@example.com',
      deleted: false,
    },
    color: 'Marrón',
    allergies: null,
    notes: null,
    deceased_at: null,
    created_at: '2026-11-10T13:30:00.000000Z',
    updated_at: '2026-11-12T18:05:00.000000Z',
    ...overrides,
  };
}
