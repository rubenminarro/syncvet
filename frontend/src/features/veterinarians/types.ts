/** Los veterinarios como los manda la API (VeterinarianResource y ShowVeterinarianResource). */

/** Los estados de la ficha (`App\Enums\VeterinarianStatus`). */
export const VETERINARIAN_STATUSES = ['active', 'inactive'] as const;

export type VeterinarianStatus = (typeof VETERINARIAN_STATUSES)[number];

/** Una sucursal del veterinario: las de su usuario (P5), ordenadas por código. */
export interface VeterinarianBranch {
  id: number;
  code: string;
  name: string;
}

/** Un ítem de GET veterinarians (VeterinarianResource): sin la comisión ni las notas (R-037). */
export interface Veterinarian {
  id: number;
  code: string;
  user: { id: number; name: string; email: string; is_active: boolean };
  license_number: string | null;
  specialty: string | null;
  status: { value: VeterinarianStatus; label: string };
  branches: VeterinarianBranch[];
}

/** GET veterinarians/{id} (ShowVeterinarianResource), solo del administrador: la comisión ("12.50"), las notas y las fechas. */
export interface VeterinarianDetail extends Veterinarian {
  commission_percentage: string;
  notes: string | null;
  created_at: string | null;
  updated_at: string | null;
}

/** Los filtros de GET veterinarians, con los nombres de la API (la clave de la consulta). */
export interface VeterinarianListFilters {
  search: string;
  /** `inactive` para "Inactivos"; null para "Activos" (lo de la API sin filtro) o "Todos". */
  status: VeterinarianStatus | null;
  /** "Todos": activos e inactivos (nunca los eliminados). */
  include_inactive: boolean;
  branch_id: number | null;
  page: number;
}

/** Los datos de la ficha que se editan (PATCH veterinarians/{id}): ni el usuario ni el código. */
export interface VeterinarianPayload {
  license_number: string | null;
  specialty: string | null;
  /** Texto con punto decimal ("12.5"), como el peso del paciente. */
  commission_percentage: string;
  notes: string | null;
}

/** POST veterinarians: con el usuario. El estado no va (la API pone `active`). */
export interface CreateVeterinarianPayload extends VeterinarianPayload {
  user_id: number;
}
