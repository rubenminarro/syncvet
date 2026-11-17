/** Los servicios como los manda la API (ServiceResource y ShowServiceResource). */

/**
 * Las categorías (`App\Enums\ServiceCategory`, sin endpoint: H2 del diseño de la F2), con el texto de `label()`. Un test
 * lee el enum del backend y falla si la lista o los textos cambian.
 */
export const SERVICE_CATEGORIES = [
  { value: 'consultation', label: 'Consulta' },
  { value: 'vaccination', label: 'Vacunación' },
  { value: 'surgery', label: 'Cirugía' },
  { value: 'laboratory', label: 'Laboratorio' },
  { value: 'imaging', label: 'Imágenes' },
  { value: 'hospitalization', label: 'Internación' },
  { value: 'grooming', label: 'Peluquería' },
  { value: 'other', label: 'Otro' },
] as const;

export type ServiceCategory = (typeof SERVICE_CATEGORIES)[number]['value'];

export const SERVICE_CATEGORY_VALUES: readonly ServiceCategory[] = SERVICE_CATEGORIES.map(
  (category) => category.value,
);

/** Las tasas de IVA (`App\Enums\TaxRate`): número en el cuerpo y en la respuesta. */
export const TAX_RATES = [0, 5, 10] as const;

export type TaxRate = (typeof TAX_RATES)[number];

/** Los estados del servicio (`App\Enums\ServiceStatus`). */
export type ServiceStatus = 'active' | 'inactive';

/** Un ítem de GET services (ServiceResource). */
export interface Service {
  id: number;
  code: string;
  name: string;
  category: { value: ServiceCategory; label: string };
  duration_minutes: number;
  /** Texto con dos decimales ("150000.00", BCMath): nunca un número de coma flotante. */
  standard_price: string;
  tax_rate: TaxRate;
  status: { value: ServiceStatus; label: string };
}

/** GET services/{id} (ShowServiceResource): suma la descripción y las fechas. */
export interface ServiceDetail extends Service {
  description: string | null;
  created_at: string | null;
  updated_at: string | null;
}

/** Los filtros de GET services, con los nombres de la API (la clave de la consulta). */
export interface ServiceListFilters {
  search: string;
  category: ServiceCategory | null;
  /** `inactive` para "Inactivos"; null para "Activos" (lo de la API sin filtro) o "Todos". */
  status: ServiceStatus | null;
  /** "Todos": activos e inactivos (nunca los eliminados). */
  include_inactive: boolean;
  page: number;
}

/** El cuerpo del alta y de la edición. El estado no va (se cambia con su botón; el alta queda `active`). */
export interface ServicePayload {
  name: string;
  category: ServiceCategory;
  duration_minutes: number;
  /** Texto de solo dígitos ("150000"): nunca un número ni con decimales (`WholeAmount`). */
  standard_price: string;
  tax_rate: TaxRate;
  description: string | null;
}
