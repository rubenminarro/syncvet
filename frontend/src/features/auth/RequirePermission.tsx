import type { ReactNode } from 'react';
import { useCan } from './permissions';

/** Muestra sus hijos solo si el usuario tiene el permiso; si no, `fallback` (nada, por defecto). */
export function RequirePermission({
  permission,
  children,
  fallback = null,
}: {
  permission: string;
  children: ReactNode;
  fallback?: ReactNode;
}) {
  const allowed = useCan();

  return <>{allowed(permission) ? children : fallback}</>;
}
