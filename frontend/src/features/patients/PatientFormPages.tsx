import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { useRef, useState } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import { afterClientGone, clientDetailQueryOptions } from '../clients/api';
import { useClientListSearch, withClientList } from '../clients/navigation';
import {
  afterPatientSaved,
  createPatient,
  patientDetailQueryOptions,
  patientKeys,
  speciesQueryOptions,
  updatePatient,
} from './api';
import {
  EMPTY_PATIENT_FORM,
  PATIENT_FORM_FIELDS,
  patientFormToPayload,
  patientToForm,
  type PatientFormValues,
} from './form';
import { usePatientListSearch, withPatientList } from './navigation';
import { PatientForm } from './PatientForm';

/** La raza no es de la especie elegida (backend/CLAUDE.md §6.2): va al campo de la raza, no a la alerta. */
const BREED_SPECIES_MISMATCH = 'PATIENT_BREED_SPECIES_MISMATCH';

interface SaveErrorContext {
  form: UseFormReturn<PatientFormValues>;
  setAlert: (message: string | null) => void;
  /** Un 404 en la acción: el paciente (o el dueño, en el alta) ya no está. */
  onGone: (message: string) => void;
  onConflict?: () => void;
}

/** Qué hace el formulario con cada error de la API (frontend/CLAUDE.md §6 y la tabla de la F1). */
function handleSaveError(error: unknown, context: SaveErrorContext) {
  if (!isApiError(error)) {
    context.setAlert(MESSAGES.programming);

    return;
  }

  if (error.errorCode === BREED_SPECIES_MISMATCH) {
    context.setAlert(null);
    context.form.setError(
      'breed_id',
      { type: 'server', message: error.message },
      { shouldFocus: true },
    );

    return;
  }

  switch (errorTreatment(error.errorCode)) {
    case 'validation': {
      const others = applyValidationErrors(error, context.form.setError, PATIENT_FORM_FIELDS) ?? [];
      context.setAlert(others.length > 0 ? others.join(' ') : null);
      break;
    }
    case 'not-found':
      context.onGone(error.message);
      break;
    case 'conflict':
      context.setAlert(userMessage(error));
      context.onConflict?.();
      break;
    default:
      // FORBIDDEN, PATIENT_DECEASED y los demás de negocio, el servidor y la red: el mensaje arriba y el formulario como
      // estaba.
      context.setAlert(userMessage(error));
  }
}

/** Un solo envío a la vez: dos handleSubmit seguidos llegan antes de que el botón se deshabilite (la regla de la 1.2). */
function useSingleSubmit() {
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

/**
 * /clientes/$id/pacientes/nuevo: el alta desde el detalle del cliente (el dueño sale de la URL; pregunta 7 del diseño). Con
 * el 201, al detalle del paciente nuevo; "Cancelar" vuelve al cliente.
 */
export function PatientCreatePage({ clientId }: { clientId: number }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const clientList = useClientListSearch();
  const { data: client } = useSuspenseQuery(clientDetailQueryOptions(clientId));
  const { data: species } = useSuspenseQuery(speciesQueryOptions());
  const [alert, setAlert] = useState<string | null>(null);
  const single = useSingleSubmit();

  const mutation = useMutation({
    mutationFn: (values: PatientFormValues) =>
      createPatient(clientId, patientFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterPatientSaved(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/pacientes/$id',
        params: { id: String(response.data.id) },
        replace: true,
      });
    },
  });

  // El dueño ya no está (otra persona lo borró): aviso y al listado de clientes.
  const onGone = (message: string) => {
    toast.show(message);
    const forgetDetail = afterClientGone(queryClient, clientId);
    void navigate({ to: '/clientes', search: clientList, replace: true }).then(forgetDetail);
  };

  return (
    <>
      <div className="page-header mb-3">
        <div className="page-pretitle">
          <Link to="/clientes" search={clientList}>
            Clientes
          </Link>
          {' / '}
          <Link
            to="/clientes/$id"
            params={{ id: String(clientId) }}
            state={withClientList(clientList)}
          >
            {client.display_name}
          </Link>
        </div>
        <h2 className="page-title">Nuevo paciente</h2>
      </div>
      <PatientForm
        title="Datos del paciente"
        defaultValues={EMPTY_PATIENT_FORM}
        species={species}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (!single.tryStart()) {
            return;
          }

          mutation.mutate(values, {
            onError: (error) => {
              handleSaveError(error, { form, setAlert, onGone });
            },
          });
        }}
        cancel={
          <Link
            to="/clientes/$id"
            params={{ id: String(clientId) }}
            state={withClientList(clientList)}
            replace
            className="btn btn-link patient-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}

/** /pacientes/$id/editar: la edición, con el formulario completo; con el 200, de vuelta al detalle. */
export function PatientEditPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = usePatientListSearch();
  const { data: patient } = useSuspenseQuery(patientDetailQueryOptions(id));
  const { data: species } = useSuspenseQuery(speciesQueryOptions());
  const [alert, setAlert] = useState<string | null>(null);
  const single = useSingleSubmit();

  const mutation = useMutation({
    mutationFn: (values: PatientFormValues) => updatePatient(id, patientFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterPatientSaved(queryClient, response.data);
      toast.show(response.message, 'success');
      void navigate({
        to: '/pacientes/$id',
        params: { id: String(id) },
        replace: true,
        state: withPatientList(listSearch),
      });
    },
  });

  // El paciente ya no está: aviso y al listado (que se vacía, para no mostrarlo un momento).
  const onGone = (message: string) => {
    toast.show(message);
    void queryClient.resetQueries({ queryKey: patientKeys.lists });
    void navigate({ to: '/pacientes', search: listSearch, replace: true }).then(() => {
      queryClient.removeQueries({ queryKey: patientKeys.detail(id), exact: true });
    });
  };

  return (
    <>
      <div className="page-header mb-3">
        <div className="page-pretitle">
          <Link to="/pacientes" search={listSearch}>
            Pacientes
          </Link>
          {' / '}
          <Link to="/pacientes/$id" params={{ id: String(id) }} state={withPatientList(listSearch)}>
            {patient.name}
          </Link>
        </div>
        <h2 className="page-title">Editar el paciente</h2>
      </div>
      <PatientForm
        title="Datos del paciente"
        defaultValues={patientToForm(patient)}
        species={species}
        current={{ species: patient.species, breed: patient.breed }}
        alert={alert}
        saving={mutation.isPending}
        onSubmit={(values, form) => {
          if (!single.tryStart()) {
            return;
          }

          mutation.mutate(values, {
            onError: (error) => {
              handleSaveError(error, {
                form,
                setAlert,
                onGone,
                onConflict: () => {
                  void queryClient.invalidateQueries({ queryKey: patientKeys.detail(id) });
                },
              });
            },
          });
        }}
        cancel={
          <Link
            to="/pacientes/$id"
            params={{ id: String(id) }}
            state={withPatientList(listSearch)}
            replace
            className="btn btn-link patient-cancel"
          >
            Cancelar
          </Link>
        }
      />
    </>
  );
}
