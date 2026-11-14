import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { useId, useRef, useState } from 'react';
import { ConfirmDialog, DateField, SearchField, TimeField, useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { todayInAppZone } from '../../lib/format/dates';
import { SEARCH_DEBOUNCE_MS } from '../../lib/hooks/use-search-text';
import { useDebouncedValue } from '../../lib/hooks/use-debounced-value';
import { normalizeSearchText } from '../../lib/search-text';
import { useCan } from '../auth/permissions';
import { clientOptionsQueryOptions } from '../clients/api';
import {
  afterPatientGone,
  afterPatientSaved,
  changePatientOwner,
  deletePatient,
  patientKeys,
  setPatientDeceased,
} from './api';
import {
  deceasedFormErrors,
  deceasedToApi,
  deceasedToForm,
  type DeceasedFormValues,
} from './deceased';
import { usePatientListSearch } from './navigation';
import type { PatientDetail } from './types';

/** Los diálogos del detalle del paciente (1.3b): uno abierto a la vez. */
export type PatientDialog = 'deceased' | 'annul' | 'owner' | 'delete';

/** El paciente tiene consultas, aplicaciones o turnos (backend/CLAUDE.md §6.2): se ofrece registrar el fallecimiento. */
const PATIENT_IN_USE = 'PATIENT_IN_USE';

/** Un solo pedido a la vez: un doble clic llega antes de que el botón quede esperando (la regla de la 1.2). */
function useSingleAction() {
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
function usePatientGone(id: number) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = usePatientListSearch();

  return (message: string) => {
    toast.show(message);
    const forgetDetail = afterPatientGone(queryClient, id);
    void navigate({ to: '/pacientes', search: listSearch, replace: true }).then(forgetDetail);
  };
}

/**
 * Qué hace un diálogo con el error de su acción (frontend/CLAUDE.md §6): un 404, al listado; un 409 CONFLICT, el aviso y el
 * paciente pedido otra vez; lo demás (FORBIDDEN, los de negocio, el servidor, la red), el mensaje dentro del diálogo.
 * Devuelve el texto para el diálogo, o null si ya se trató.
 */
function actionError(
  error: unknown,
  { onGone, onConflict }: { onGone: (message: string) => void; onConflict: () => void },
): string | null {
  if (!isApiError(error)) {
    return MESSAGES.programming;
  }

  switch (errorTreatment(error.errorCode)) {
    case 'not-found':
      onGone(error.message);

      return null;
    case 'conflict':
      onConflict();

      return userMessage(error);
    default:
      return userMessage(error);
  }
}

interface DialogProps {
  patient: PatientDetail;
  onClose: () => void;
}

/**
 * Registrar o corregir el fallecimiento (`patients.update`): la fecha y la hora de pared de Asunción (por defecto, ahora;
 * al corregir, la guardada), no futuras ni anteriores al nacimiento. Se manda solo `deceased_at`, con `-03:00`.
 */
export function DeceasedDialog({ patient, onClose }: DialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const onGone = usePatientGone(patient.id);
  const single = useSingleAction();
  const correcting = patient.deceased_at !== null;
  const [values, setValues] = useState<DeceasedFormValues>(() =>
    deceasedToForm(patient.deceased_at ?? Date.now()),
  );
  const [errors, setErrors] = useState<Partial<Record<keyof DeceasedFormValues, string>>>({});
  const [alert, setAlert] = useState<string | null>(null);
  const dateRef = useRef<HTMLInputElement>(null);
  const timeRef = useRef<HTMLInputElement>(null);

  const mutation = useMutation({
    mutationFn: (deceasedAt: string) => setPatientDeceased(patient.id, deceasedAt),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterPatientSaved(queryClient, response.data);
      toast.show(response.message, 'success');
      onClose();
    },
    onError: (error) => {
      // La fecha rechazada por la API (futura para su reloj, anterior al nacimiento) va al campo de la fecha.
      const fieldError = isApiError(error) ? error.fieldErrors().deceased_at?.join(' ') : undefined;

      if (fieldError !== undefined) {
        setErrors({ date: fieldError });
        dateRef.current?.focus();

        return;
      }

      setAlert(
        actionError(error, {
          onGone,
          onConflict: () => {
            void queryClient.invalidateQueries({ queryKey: patientKeys.detail(patient.id) });
          },
        }),
      );
    },
  });

  const submit = () => {
    // Una fecha o una hora a medio escribir: el campo da '' (validity.badInput), que el control toma como inválido.
    const found = deceasedFormErrors(values, { birthDate: patient.birth_date });
    setErrors(found);

    if (found.date !== undefined) {
      dateRef.current?.focus();

      return;
    }

    if (found.time !== undefined) {
      timeRef.current?.focus();

      return;
    }

    if (single.tryStart()) {
      mutation.mutate(deceasedToApi(values));
    }
  };

  return (
    <ConfirmDialog
      open
      size="md"
      tone="primary"
      title={correcting ? 'Corregir la fecha de fallecimiento' : 'Registrar el fallecimiento'}
      confirmLabel={correcting ? 'Guardar la fecha' : 'Registrar el fallecimiento'}
      busy={mutation.isPending}
      error={alert}
      onConfirm={submit}
      onClose={onClose}
    >
      <p>
        {correcting ? 'La fecha y la hora en que falleció ' : '¿Cuándo falleció '}
        <strong>{patient.name}</strong>
        {correcting ? '.' : '?'} Hora de Asunción.
      </p>
      <form
        className="row deceased-form"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          submit();
        }}
      >
        <div className="col-sm-7">
          <DateField
            ref={dateRef}
            name="deceased_date"
            label="Fecha"
            required
            max={todayInAppZone()}
            value={values.date}
            error={errors.date}
            onChange={(event) => {
              setValues((current) => ({ ...current, date: event.target.value }));
            }}
          />
        </div>
        <div className="col-sm-5">
          <TimeField
            ref={timeRef}
            name="deceased_time"
            label="Hora"
            required
            value={values.time}
            error={errors.time}
            onChange={(event) => {
              setValues((current) => ({ ...current, time: event.target.value }));
            }}
          />
        </div>
      </form>
    </ConfirmDialog>
  );
}

/** Anular el fallecimiento (`patients.update`, pregunta 10 del diseño): con confirmación, manda `deceased_at: null`. */
export function AnnulDeceasedDialog({ patient, onClose }: DialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const onGone = usePatientGone(patient.id);
  const single = useSingleAction();
  const [alert, setAlert] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () => setPatientDeceased(patient.id, null),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterPatientSaved(queryClient, response.data);
      toast.show(response.message, 'success');
      onClose();
    },
    onError: (error) => {
      setAlert(
        actionError(error, {
          onGone,
          onConflict: () => {
            void queryClient.invalidateQueries({ queryKey: patientKeys.detail(patient.id) });
          },
        }),
      );
    },
  });

  return (
    <ConfirmDialog
      open
      title="Anular el fallecimiento"
      confirmLabel="Anular el fallecimiento"
      busy={mutation.isPending}
      error={alert}
      onConfirm={() => {
        if (single.tryStart()) {
          mutation.mutate();
        }
      }}
      onClose={onClose}
    >
      ¿Querés anular el fallecimiento de <strong>{patient.name}</strong>? Va a figurar de nuevo como
      activo en los listados.
    </ConfirmDialog>
  );
}

/**
 * Cambiar de dueño (`patients.change-owner`: administrador y recepcionista): un buscador de clientes con la espera de 300 ms
 * (`GET clients?search=…&per_page=10`), sin el dueño actual. Un fallecido también cambia de dueño.
 */
export function ChangeOwnerDialog({ patient, onClose }: DialogProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const onGone = usePatientGone(patient.id);
  const single = useSingleAction();
  const groupName = useId();
  const [text, setText] = useState('');
  const search = useDebouncedValue(normalizeSearchText(text), SEARCH_DEBOUNCE_MS);
  const [selected, setSelected] = useState<number | null>(null);
  const [alert, setAlert] = useState<string | null>(null);
  const currentOwnerId = patient.client?.id ?? null;

  const options = useQuery({
    ...clientOptionsQueryOptions(search),
    enabled: search !== '',
    placeholderData: keepPreviousData,
  });
  // El dueño actual no se ofrece (la API respondería PATIENT_SAME_OWNER).
  const clients =
    search === '' ? [] : (options.data ?? []).filter((client) => client.id !== currentOwnerId);
  const chosen = clients.find((client) => client.id === selected) ?? null;

  const mutation = useMutation({
    mutationFn: (clientId: number) => changePatientOwner(patient.id, clientId),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      // Los detalles de los dos clientes listan sus pacientes: afterPatientSaved invalida ['clients'] entero.
      afterPatientSaved(queryClient, response.data);
      toast.show(response.message, 'success');
      onClose();
    },
    onError: (error) => {
      // 422 en client_id (el cliente se eliminó mientras tanto) o PATIENT_SAME_OWNER: el mensaje, en el diálogo.
      const fieldError = isApiError(error) ? error.fieldErrors().client_id?.join(' ') : undefined;

      if (fieldError !== undefined) {
        setAlert(fieldError);
        void queryClient.invalidateQueries({ queryKey: ['clients', 'options'] });

        return;
      }

      setAlert(
        actionError(error, {
          onGone,
          onConflict: () => {
            void queryClient.invalidateQueries({ queryKey: patientKeys.detail(patient.id) });
          },
        }),
      );
    },
  });

  let status: string;

  if (search === '') {
    status = 'Escribí el nombre, el documento o el teléfono del cliente.';
  } else if (options.isError) {
    status = isApiError(options.error) ? userMessage(options.error) : MESSAGES.programming;
  } else if (options.data === undefined) {
    status = 'Buscando…';
  } else if (clients.length === 0) {
    status = 'No hay otros clientes con esa búsqueda.';
  } else {
    status = '';
  }

  return (
    <ConfirmDialog
      open
      size="md"
      tone="primary"
      title="Cambiar de dueño"
      confirmLabel={chosen === null ? 'Cambiar de dueño' : `Pasar a ${chosen.display_name}`}
      confirmDisabled={chosen === null}
      busy={mutation.isPending}
      error={alert}
      onConfirm={() => {
        if (chosen !== null && single.tryStart()) {
          mutation.mutate(chosen.id);
        }
      }}
      onClose={onClose}
    >
      <p>
        <strong>{patient.name}</strong> es de{' '}
        <strong>{patient.client?.display_name ?? 'nadie'}</strong>. Elegí el dueño nuevo.
      </p>
      <SearchField
        className="mb-2 owner-search"
        label="Buscar el cliente"
        placeholder="Buscá por nombre, documento o teléfono"
        value={text}
        busy={options.isFetching}
        onChange={(next) => {
          setText(next);
          setSelected(null);
        }}
      />
      {status !== '' ? <p className="text-secondary mb-0 owner-status">{status}</p> : null}
      {clients.length > 0 ? (
        <div className="list-group owner-options" role="radiogroup" aria-label="Clientes">
          {clients.map((client) => (
            <label key={client.id} className="list-group-item d-flex gap-2 align-items-start">
              <input
                className="form-check-input mt-1"
                type="radio"
                name={groupName}
                value={client.id}
                checked={selected === client.id}
                onChange={() => {
                  setSelected(client.id);
                }}
              />
              <span>
                <span className="d-block">{client.display_name}</span>
                <small className="text-secondary">
                  {client.document_number}
                  {client.phone !== null ? ` · ${client.phone}` : ''}
                </small>
              </span>
            </label>
          ))}
        </div>
      ) : null}
    </ConfirmDialog>
  );
}

/**
 * La baja (`patients.destroy`, solo el administrador), con confirmación. Con PATIENT_IN_USE, el mensaje y, si no está
 * fallecido y se puede editar, "Registrar el fallecimiento" (cierra este diálogo y abre el otro). Con el 200, al detalle del
 * dueño (o al listado, si no hay dueño para mostrar).
 */
export function DeletePatientDialog({
  patient,
  onClose,
  onRegisterDeceased,
}: DialogProps & { onRegisterDeceased: () => void }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const can = useCan();
  const listSearch = usePatientListSearch();
  const onGone = usePatientGone(patient.id);
  const single = useSingleAction();
  const [alert, setAlert] = useState<string | null>(null);
  const [inUse, setInUse] = useState(false);
  const owner = patient.client;
  const toOwner = owner !== null && !owner.deleted && can(PERMISSIONS.clientsShow);
  const offerDeceased = inUse && !patient.is_deceased && can(PERMISSIONS.patientsUpdate);

  const mutation = useMutation({
    mutationFn: () => deletePatient(patient.id),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      toast.show(response.message, 'success');
      const forgetDetail = afterPatientGone(queryClient, patient.id);
      const leave = toOwner
        ? navigate({ to: '/clientes/$id', params: { id: String(owner.id) }, replace: true })
        : navigate({ to: '/pacientes', search: listSearch, replace: true });
      void leave.then(forgetDetail);
    },
    onError: (error) => {
      if (isApiError(error) && error.errorCode === PATIENT_IN_USE) {
        setInUse(true);
        setAlert(error.message);

        return;
      }

      setAlert(
        actionError(error, {
          onGone,
          onConflict: () => {
            void queryClient.invalidateQueries({ queryKey: patientKeys.detail(patient.id) });
          },
        }),
      );
    },
  });

  return (
    <ConfirmDialog
      open
      tone={offerDeceased ? 'primary' : 'danger'}
      title="Eliminar el paciente"
      confirmLabel={offerDeceased ? 'Registrar el fallecimiento' : `Eliminar a ${patient.name}`}
      busy={mutation.isPending}
      error={alert}
      canConfirm={!inUse || offerDeceased}
      onConfirm={() => {
        if (offerDeceased) {
          onRegisterDeceased();

          return;
        }

        if (single.tryStart()) {
          mutation.mutate();
        }
      }}
      onClose={onClose}
    >
      ¿Querés eliminar a <strong>{patient.name}</strong> ({patient.code})? No se puede deshacer.
    </ConfirmDialog>
  );
}
