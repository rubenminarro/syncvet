/** Los clientes como los manda la API (ClientResource y ShowClientResource), con sus nombres en snake_case. */

export type ClientKind = 'person' | 'company';

export interface LabeledValue<T extends string> {
  value: T;
  label: string;
}

/** Un ítem de GET clients (ClientResource). */
export interface Client {
  id: number;
  kind: LabeledValue<ClientKind>;
  document_number: string;
  first_name: string | null;
  last_name: string | null;
  business_name: string | null;
  display_name: string;
  phone: string | null;
  email: string | null;
  address: string | null;
  city: string | null;
  notifications_enabled: boolean;
  notes: string | null;
}

/** Un paciente dentro del detalle del cliente (PatientResource, sin el dueño: el detalle no lo carga). */
export interface ClientPatient {
  id: number;
  code: string;
  name: string;
  species: { id: number; name: string } | null;
  breed: { id: number; name: string } | null;
  sex: LabeledValue<'male' | 'female' | 'unknown'>;
  is_neutered: boolean;
  birth_date: string | null;
  birth_date_estimated: boolean;
  /** Texto ya armado por la API ("3 años", "5 meses"); null sin fecha de nacimiento. */
  age: string | null;
  weight_kg: string | null;
  microchip: string | null;
  is_deceased: boolean;
  has_photo: boolean;
}

/** GET clients/{id} (ShowClientResource): con todos sus pacientes, también los fallecidos. */
export interface ClientDetail extends Client {
  patients: ClientPatient[];
  created_at: string | null;
  updated_at: string | null;
}

/** Los filtros de GET clients, con los nombres de la API (la clave de la consulta). */
export interface ClientListFilters {
  search: string;
  kind: ClientKind | null;
  page: number;
}
