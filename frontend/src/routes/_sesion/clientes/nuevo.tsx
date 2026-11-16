import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { ClientCreatePage } from '../../../features/clients/ClientFormPages';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /clientes/nuevo: el alta (gana sobre /clientes/$id, que no la confunde con un id).
export const Route = createFileRoute('/_sesion/clientes/nuevo')({
  beforeLoad: requirePermission(PERMISSIONS.clientsStore),
  component: ClientCreatePage,
});
