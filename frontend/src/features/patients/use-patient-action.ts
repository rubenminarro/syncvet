import { useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useRef } from 'react';
import { useToast } from '../../components/ui';
import { afterPatientGone } from './api';
import { usePatientListSearch } from './navigation';
import type { PatientDetail } from './types';

/** Lo común de las acciones del detalle del paciente (los diálogos de la 1.3b y la foto de la 1.4). */

/** Un solo pedido a la vez: un doble clic llega antes de que el botón quede esperando (la regla de la 1.2). */
export function useSingleAction() {
  const inFlight = useRef(false);

  return {
    tryStart: () => {
      if (inFlight.current) {
        return false;
      }

      inFlight.current = true;

      return true;
    },
    release: () => {
      inFlight.current = false;
    },
  };
}

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
