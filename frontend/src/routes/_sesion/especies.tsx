import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../features/auth/permissions';
import { validateSpeciesListSearch } from '../../features/species/search';
import { SpeciesPage } from '../../features/species/SpeciesPage';
import { PERMISSIONS } from '../../lib/auth/permissions';

// /especies?inactivas=1 (1.5): el catálogo de especies y razas; lo ven los tres roles (species.index) y lo administran el
// administrador y el veterinario. Por defecto, solo las activas; un valor inválido vuelve al valor por defecto.
export const Route = createFileRoute('/_sesion/especies')({
  validateSearch: validateSpeciesListSearch,
  beforeLoad: requirePermission(PERMISSIONS.speciesIndex),
  component: SpeciesPage,
});
