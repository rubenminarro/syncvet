import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { PatientListPage } from '../../../features/patients/PatientListPage';
import { validatePatientListSearch } from '../../../features/patients/search';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /pacientes?buscar=&especie=&fallecidos=&pagina=: la búsqueda va en la URL; un valor inválido vuelve al valor por defecto.
export const Route = createFileRoute('/_sesion/pacientes/')({
  validateSearch: validatePatientListSearch,
  beforeLoad: requirePermission(PERMISSIONS.patientsIndex),
  component: PatientListPage,
});
