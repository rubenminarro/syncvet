import { useQuery, type QueryClient } from '@tanstack/react-query';
import { redirect } from '@tanstack/react-router';
import { useCallback } from 'react';
import { can } from '../../lib/auth/permissions';
import { authKeys, meQueryOptions } from './api';
import type { Me } from './types';

/** `can(permiso)` sobre el `me` de la caché (sin `me`, siempre false). */
export function useCan(): (permission: string) => boolean {
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });

  return useCallback((permission: string) => can(me, permission), [me]);
}

/**
 * El `beforeLoad` de una ruta con permiso: sin él, a /sin-permiso. Corre después del de `_sesion`, que ya dejó `me` en la
 * caché.
 */
export function requirePermission(permission: string) {
  return ({ context }: { context: { queryClient: QueryClient } }) => {
    const me = context.queryClient.getQueryData<Me>(authKeys.me);

    if (!can(me, permission)) {
      throw redirect({ to: '/sin-permiso', replace: true });
    }
  };
}
