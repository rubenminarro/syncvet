import type { Client, ClientDetail, ClientPatient } from '../features/clients/types';
import type { Pagination } from '../lib/api/types';

/**
 * Clientes y pacientes de prueba, como los manda la API (ClientResource, ShowClientResource y PatientResource). Los
 * documentos son los ficticios del DemoSeeder (R-011, H7 del diseño de la F1).
 */

export const PERSON: Client = {
  id: 1,
  kind: { value: 'person', label: 'Persona' },
  document_number: '99100001',
  first_name: 'Laura',
  last_name: 'Martínez',
  business_name: null,
  display_name: 'Laura Martínez',
  phone: '+595981100001',
  email: 'laura.martinez@example.com',
  address: 'Calle Falsa 123',
  city: 'Asunción',
  notifications_enabled: true,
  notes: null,
};

export const COMPANY: Client = {
  id: 3,
  kind: { value: 'company', label: 'Empresa' },
  document_number: '99999903-6',
  first_name: null,
  last_name: null,
  business_name: 'Granja San Jorge S.R.L.',
  display_name: 'Granja San Jorge S.R.L.',
  phone: '+595211100003',
  email: null,
  address: null,
  city: 'San Lorenzo',
  notifications_enabled: false,
  notes: 'Cliente mayorista, con crédito a 30 días.',
};

export function patient(overrides: Partial<ClientPatient> = {}): ClientPatient {
  return {
    id: 1,
    code: 'P-000001',
    name: 'Firulais',
    species: { id: 1, name: 'Canino' },
    breed: { id: 2, name: 'Mestizo' },
    sex: { value: 'male', label: 'Macho' },
    is_neutered: false,
    birth_date: '2022-03-10',
    birth_date_estimated: false,
    age: '4 años',
    weight_kg: '12.50',
    microchip: null,
    is_deceased: false,
    has_photo: false,
    ...overrides,
  };
}

export function detail(client: Client, patients: ClientPatient[] = []): ClientDetail {
  return {
    ...client,
    patients,
    created_at: '2026-11-10T13:30:00.000000Z',
    updated_at: '2026-11-12T18:05:00.000000Z',
  };
}

export function pagination(overrides: Partial<Pagination> = {}): { pagination: Pagination } {
  return { pagination: { total: 2, per_page: 20, current_page: 1, last_page: 1, ...overrides } };
}
