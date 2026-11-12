import type { QueryClient } from '@tanstack/react-query';

/**
 * Convención de las claves de TanStack Query (frontend/CLAUDE.md §5):
 *
 * - `['me']`: el usuario de la sesión.
 * - Las consultas cuyos datos dependen de la sucursal actual (la cola del día, los turnos, la caja…) empiezan con
 *   `branchKey(idDeLaSucursal, …)`: `['branch', 10, 'appointments', 'queue', { date }]`. Llevan la sucursal en la clave, así
 *   que no se mezclan los datos de dos sucursales, y el cambio de sucursal invalida solo esas (`invalidateBranchQueries`).
 * - Las demás (catálogos, clientes, pacientes: son de la clínica) llevan su módulo primero (`['clients', …]`) y no se tocan
 *   al cambiar de sucursal.
 */
export const BRANCH_SCOPE = 'branch';

/** La clave de una consulta que depende de la sucursal: la sucursal (o null, sin sucursal actual) va en la clave. */
export function branchKey<const T extends readonly unknown[]>(branchId: number | null, ...rest: T) {
  return [BRANCH_SCOPE, branchId, ...rest] as const;
}

/** Invalida las consultas que dependen de la sucursal (de cualquier sucursal: las de la anterior quedan viejas). */
export function invalidateBranchQueries(queryClient: QueryClient): Promise<void> {
  return queryClient.invalidateQueries({ queryKey: [BRANCH_SCOPE] });
}
