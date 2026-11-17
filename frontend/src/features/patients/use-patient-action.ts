import { useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useToast } from '../../components/ui';
import { afterPatientGone } from './api';
import { usePatientListSearch } from './navigation';
import type { PatientDetail } from './types';

/** Lo común de las acciones del detalle del paciente (los diálogos de la 1.3b y la foto de la 1.4). */

/** El paciente ya no está (otra persona lo borró): aviso y al listado de pacientes, sin mostrarlo un momento. */
export function usePatientGone(patient: PatientDetail) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = usePatientListSearch();

  return (message: string) => {
    toast.show(message);
    const forgetDetail = afterPatientGone(queryClient, patient);
    void navigate({ to: '/pacientes', search: listSearch, replace: true }).then(forgetDetail);
  };
}
