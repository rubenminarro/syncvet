import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../features/auth/permissions';
import { SpeciesPage } from '../../features/species/SpeciesPage';
import { PERMISSIONS } from '../../lib/auth/permissions';

// /especies (1.5): el catálogo de especies y razas; lo ven los tres roles (species.index) y lo administran el administrador y
// el veterinario.
export const Route = createFileRoute('/_sesion/especies')({
  beforeLoad: requirePermission(PERMISSIONS.speciesIndex),
  component: SpeciesPage,
});
