import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { loadVeterinarianCreate } from '../../../features/veterinarians/loader';
import { validateVeterinarianCreateSearch } from '../../../features/veterinarians/search';
import { VeterinarianCreatePage } from '../../../features/veterinarians/VeterinarianFormPages';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /veterinarios/nuevo?usuario= (gana sobre $id): el alta, con los usuarios del rol veterinario sin ficha.
export const Route = createFileRoute('/_sesion/veterinarios/nuevo')({
  validateSearch: validateVeterinarianCreateSearch,
  beforeLoad: requirePermission(PERMISSIONS.veterinariansStore),
  loader: loadVeterinarianCreate,
  component: VeterinarianCreatePage,
});
