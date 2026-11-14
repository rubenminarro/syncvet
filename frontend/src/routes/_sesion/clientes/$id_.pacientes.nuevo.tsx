import { createFileRoute } from '@tanstack/react-router';
import { requirePermission } from '../../../features/auth/permissions';
import { ClientNotFound } from '../../../features/clients/ClientDetailPage';
import { loadClient } from '../../../features/clients/loader';
import { PatientCreatePage } from '../../../features/patients/PatientFormPages';
import { PERMISSIONS } from '../../../lib/auth/permissions';

// /clientes/$id/pacientes/nuevo: el alta de un paciente desde su dueño (pregunta 7 del diseño), fuera del detalle del
// cliente (`$id_`). El loader del detalle del cliente: un cliente inexistente es la página de no encontrado.
export const Route = createFileRoute('/_sesion/clientes/$id_/pacientes/nuevo')({
  beforeLoad: requirePermission(PERMISSIONS.patientsStore),
  loader: loadClient,
  notFoundComponent: ClientNotFound,
  component: PatientCreateRoute,
});

function PatientCreateRoute() {
  const { id } = Route.useLoaderData();

  return <PatientCreatePage clientId={id} />;
}
