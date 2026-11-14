import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { PatientDetailPage, PatientNotFound } from '../../../features/patients/PatientDetailPage';
import { loadPatient } from '../../../features/patients/loader';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /pacientes/$id: un id que no es un entero positivo y el 404 de la API (inexistente o de otra clínica) son la página de no
// encontrado; un 403 FORBIDDEN, la de sin permiso (RouteErrorPage); una falla de red o un 5xx, la de error con "Reintentar".
export const Route = createFileRoute('/_sesion/pacientes/$id')({
  beforeLoad: requirePermission(PERMISSIONS.patientsShow),
  loader: loadPatient,
  notFoundComponent: PatientNotFound,
  component: PatientDetailRoute,
});

function PatientDetailRoute() {
  const { id } = Route.useLoaderData();

  return <PatientDetailPage id={id} />;
}
