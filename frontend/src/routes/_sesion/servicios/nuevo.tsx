import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { ServiceCreatePage } from '../../../features/services/ServiceFormPages';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /servicios/nuevo (gana sobre $id): solo el administrador.
export const Route = createFileRoute('/_sesion/servicios/nuevo')({
  beforeLoad: requirePermission(PERMISSIONS.servicesStore),
  component: ServiceCreatePage,
});
