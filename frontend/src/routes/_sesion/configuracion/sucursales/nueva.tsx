import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../../features/auth/permissions';
import { BranchCreatePage } from '../../../../features/branches/BranchFormPages';
import { PERMISSIONS } from '../../../../lib/auth/permissions';

// /configuracion/sucursales/nueva: el alta con su depósito por defecto (gana sobre $id).
export const Route = createFileRoute('/_sesion/configuracion/sucursales/nueva')({
  beforeLoad: requirePermission(PERMISSIONS.branchesStore),
  component: BranchCreatePage,
});
