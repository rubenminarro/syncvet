/** El usuario autenticado (MeResource del backend), con los nombres de la API. */

export interface BranchSummary {
  id: number;
  code: string;
  name: string;
}

export interface Me {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  clinic: { id: number; name: string; has_logo: boolean };
  current_branch: BranchSummary | null;
  /** Las sucursales que puede elegir como actual (entrega front-00, B1); el selector es de la 0.2b. */
  branches: BranchSummary[];
  roles: string[];
  permissions: string[];
  veterinarian: { id: number; code: string } | null;
}

/** `data` de POST /api/login. */
export interface LoginResponse {
  token: string;
  token_type: 'Bearer';
  expires_at: string;
  user: Me;
}
