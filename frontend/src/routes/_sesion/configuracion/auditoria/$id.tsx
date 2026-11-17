import { createFileRoute } from '@tanstack/react-router';
import { AuditDetailPage, AuditLogNotFound } from '../../../../features/audit/AuditDetailPage';
import { loadAuditLog } from '../../../../features/audit/loader';
import { requirePermission } from '../../../../features/auth/permissions';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/auditoria/$id (2.6): un id inválido y el 404 de la API son la página de no encontrado, dentro del layout.
export const Route = createFileRoute('/_sesion/configuracion/auditoria/$id')({
  beforeLoad: requirePermission(PERMISSIONS.auditLogsShow),
  loader: loadAuditLog,
  notFoundComponent: AuditLogNotFound,
  component: AuditDetailRoute,
});

function AuditDetailRoute() {
  const { id } = Route.useLoaderData();

  // key: otro id es otra pantalla.
  return <AuditDetailPage key={id} id={id} />;
}
