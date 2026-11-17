import { createFileRoute } from '@tanstack/react-router';
import { AuditListPage } from '../../../../features/audit/AuditListPage';
import { validateAuditListSearch } from '../../../../features/audit/search';
import { requirePermission } from '../../../../features/auth/permissions';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/auditoria?desde=&hasta=&usuario=&evento=&entidad=&registro=&buscar=&pagina= (2.6): solo el administrador.
export const Route = createFileRoute('/_sesion/configuracion/auditoria/')({
  validateSearch: validateAuditListSearch,
  beforeLoad: requirePermission(PERMISSIONS.auditLogsIndex),
  component: AuditListPage,
});
