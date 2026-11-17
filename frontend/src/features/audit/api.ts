import { keepPreviousData, queryOptions } from '@tanstack/react-query';
import { api, getPage } from '../../lib/api/client';
import type { AuditLog, AuditLogDetail, AuditLogListFilters } from './types';

/** Registros por página: fijo, como los demás listados. */
export const AUDIT_LOGS_PER_PAGE = 20;

/**
 * Las claves de la auditoría (diseño de la F2, "Claves de las consultas"): es de la clínica, no depende de la sucursal
 * actual. Es de solo lectura: nada la invalida (`staleTime` de 0, al volver a entrar se pide de nuevo).
 */
export const auditLogKeys = {
  all: ['audit-logs'] as const,
  list: (filters: AuditLogListFilters) => ['audit-logs', 'list', filters] as const,
  detail: (id: number) => ['audit-logs', 'detail', id] as const,
};

/** GET audit-logs: 20 por página, por fecha descendente; mientras llega otra página o filtro, la tabla sigue con la anterior. */
export function auditLogListQueryOptions(filters: AuditLogListFilters) {
  return queryOptions({
    queryKey: auditLogKeys.list(filters),
    queryFn: ({ signal }) =>
      getPage<AuditLog>('audit-logs', {
        signal,
        query: {
          from: filters.from ?? undefined,
          to: filters.to ?? undefined,
          user_id: filters.user_id ?? undefined,
          event: filters.event ?? undefined,
          auditable_type: filters.auditable_type ?? undefined,
          auditable_id: filters.auditable_id ?? undefined,
          search: filters.search,
          page: filters.page,
          per_page: AUDIT_LOGS_PER_PAGE,
        },
      }),
    placeholderData: keepPreviousData,
  });
}

/** GET audit-logs/{id}: suma los valores de antes y de después y el navegador. */
export function auditLogDetailQueryOptions(id: number) {
  return queryOptions({
    queryKey: auditLogKeys.detail(id),
    queryFn: async ({ signal }) =>
      (await api.get<AuditLogDetail>(`audit-logs/${id}`, { signal })).data,
  });
}
