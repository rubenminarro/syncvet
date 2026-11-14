import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { PatientNotFound } from '../../../features/patients/PatientDetailPage';
import { PatientEditPage } from '../../../features/patients/PatientFormPages';
import { loadPatient } from '../../../features/patients/loader';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /pacientes/$id/editar (con `$id_`, fuera del detalle: no se dibuja adentro de él). El mismo loader del detalle.
export const Route = createFileRoute('/_sesion/pacientes/$id_/editar')({
  beforeLoad: requirePermission(PERMISSIONS.patientsUpdate),
  loader: loadPatient,
  notFoundComponent: PatientNotFound,
  component: PatientEditRoute,
});

function PatientEditRoute() {
  const { id } = Route.useLoaderData();

  return <PatientEditPage id={id} />;
}
