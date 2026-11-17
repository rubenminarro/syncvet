/** Los usuarios como los manda la API (UserResource, ShowUserResource y RoleResource). */

/** Los roles de la clínica (`App\Enums\UserRole`): una lista fija, también la del filtro de la URL. */
export const ROLE_NAMES = ['administrador', 'recepcionista', 'veterinario'] as const;

export type RoleName = (typeof ROLE_NAMES)[number];

/** El rol de la sesión que se pierde al quitárselo a uno mismo (pregunta 7 del diseño de la F2). */
export const ADMIN_ROLE: RoleName = 'administrador';

/** El rol que puede tener ficha de veterinario (backend/CLAUDE.md §6.6). */
export const VETERINARIAN_ROLE: RoleName = 'veterinario';

/** Un ítem de GET roles y de `roles` del usuario (RoleResource): el nombre y su etiqueta en castellano. */
export interface Role {
  name: RoleName;
  label: string;
}

/** Una sucursal del usuario, ordenadas por código. */
export interface UserBranch {
  id: number;
  code: string;
  name: string;
}

/** La ficha de veterinario del usuario: null sin ficha o con la ficha eliminada. */
export interface UserVeterinarian {
  id: number;
  code: string;
}

/** Un ítem de GET users (UserResource); desde front-02, con la ficha de veterinario. */
export interface User {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  roles: Role[];
  branches: UserBranch[];
  current_branch_id: number | null;
  is_active: boolean;
  veterinarian: UserVeterinarian | null;
}

/** GET users/{id} (ShowUserResource): suma las fechas. */
export interface UserDetail extends User {
  created_at: string | null;
  updated_at: string | null;
}

/** Los filtros de GET users, con los nombres de la API (la clave de la consulta). */
export interface UserListFilters {
  search: string;
  role: RoleName | null;
  branch_id: number | null;
  include_inactive: boolean;
  page: number;
}

/** El cuerpo de PATCH users/{id}: el formulario completo; `password`, solo si se cambia (H3 del diseño). */
export interface UserPayload {
  first_name: string;
  last_name: string;
  email: string;
  roles: RoleName[];
  branch_ids: number[];
  password?: string;
}

/** El cuerpo de POST users: con la contraseña, obligatoria. */
export interface CreateUserPayload extends UserPayload {
  password: string;
}
