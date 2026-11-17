/** Las sucursales y los depósitos como los manda la API (BranchResource, ShowBranchResource y WarehouseResource). */

/** Un ítem de GET branches (BranchResource). */
export interface Branch {
  id: number;
  code: string;
  name: string;
  /** El código de establecimiento de SIFEN: tres dígitos, de 001 a 999. */
  establishment: string;
  address: string | null;
  phone: string | null;
  is_default: boolean;
  is_active: boolean;
}

/** Un depósito (WarehouseResource): no tiene detalle propio. */
export interface Warehouse {
  id: number;
  branch_id: number;
  code: string;
  name: string;
  is_default: boolean;
  is_active: boolean;
}

/** GET branches/{id} (ShowBranchResource): con todos sus depósitos (también los inactivos), el por defecto primero. */
export interface BranchDetail extends Branch {
  warehouses: Warehouse[];
  created_at: string | null;
  updated_at: string | null;
}

/** Los filtros de GET branches, con los nombres de la API (la clave de la consulta). */
export interface BranchListFilters {
  search: string;
  include_inactive: boolean;
  page: number;
}

/** El cuerpo de PATCH branches/{id} con el formulario completo (sin `is_active`: es un botón del detalle). */
export interface BranchPayload {
  code: string;
  name: string;
  establishment: string;
  address: string | null;
  phone: string | null;
  is_default: boolean;
}

/** El cuerpo de POST branches: la sucursal y su depósito por defecto (`warehouse_name` null: la API pone "Principal"). */
export interface CreateBranchPayload extends BranchPayload {
  warehouse_code: string;
  warehouse_name: string | null;
}

/** El cuerpo de POST branches/{id}/warehouses y PATCH warehouses/{id} con el formulario completo. */
export interface WarehousePayload {
  code: string;
  name: string;
  is_default: boolean;
}
