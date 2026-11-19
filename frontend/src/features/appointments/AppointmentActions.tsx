import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useId, useRef, useState, type RefObject } from 'react';
import { Button, ConfirmDialog, TextareaField, useToast } from '../../components/ui';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatTime } from '../../lib/format/dates';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { meQueryOptions } from '../auth/api';
import { useCan } from '../auth/permissions';
import { afterAppointmentSaved, refreshAppointment, runAppointmentTransition } from './api';
import { PatientRequiredLinks } from './AppointmentLinks';
import { appointmentWho } from './grid';
import {
  availableTransitions,
  cancellationReasonError,
  TRANSITIONS,
  transitionFailure,
  type TransitionFailure,
  type TransitionName,
} from './transitions';
import type { Appointment, AppointmentDetail } from './types';

/** Cada cuánto se vuelve a mirar la hora (el ausente se habilita al llegar el inicio): 30 s. */
const NOW_TICK_MS = 30_000;

/** El título de cada diálogo (también el del error de una acción directa). */
const DIALOG_TITLES: Record<TransitionName, string> = {
  confirm: 'Confirmar el turno',
  check_in: 'Fichar el turno',
  attend: 'Marcar atendido',
  no_show: 'Marcar como ausente',
  undo_no_show: 'Deshacer el ausente',
  cancel: 'Cancelar el turno',
};

/** El texto de deshacer el ausente (diseño de la 3.5). */
export const UNDO_NO_SHOW_TEXT = 'El turno vuelve a confirmado.';

/** El botón de deshacer el ausente después del 409 `APPOINTMENT_OVERLAP`, con `appointments.overbook`. */
export const UNDO_AS_OVERBOOK = 'Volver como sobreturno';

/** "Ahora", renovado cada 30 s: el ausente se habilita solo al llegar la hora del turno. */
function useNow(): Date {
  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const timer = window.setInterval(() => {
      setNow(new Date());
    }, NOW_TICK_MS);

    return () => {
      window.clearInterval(timer);
    };
  }, []);

  return now;
}

type Dialog =
  | { kind: 'cancel' }
  | { kind: 'undo' }
  | { kind: 'error'; name: TransitionName; failure: TransitionFailure };

interface Run {
  name: TransitionName;
  body?: { cancellation_reason?: string; overbook?: true };
}

/**
 * Las acciones de estado de un turno (3.5), en el detalle y en el panel de la agenda: las de `transitions.ts` para su estado
 * y el usuario, cada una habilitada o con su motivo a la vista (fichar sin paciente lleva a cargarlo). Confirmar, fichar,
 * atendido y ausente van directo; cancelar pide el motivo y deshacer el ausente, la confirmación. Con el 200: el detalle
 * (`setQueryData`), la agenda, la lista y la cola vueltas a pedir y el aviso (un `role="status"`). Con un error, un diálogo
 * con el mensaje: los de negocio y los 422, solo "Cerrar"; el 409 y la red, "Probá de nuevo" sin perder el motivo; el turno,
 * vuelto a pedir. Si el botón con el foco desaparece (el turno cambió de estado), el foco va a `focusAfter`.
 */
export function AppointmentActions({
  appointment,
  focusAfter,
  onChanged,
  size,
  withPatientLinks = false,
}: {
  appointment: Appointment;
  focusAfter: RefObject<HTMLElement | null>;
  /** El turno nuevo, con el 200 (el panel lo muestra hasta que llega la agenda actualizada). */
  onChanged?: (saved: AppointmentDetail) => void;
  size?: 'sm';
  /** Debajo de "Fichar" sin paciente, los enlaces para cargarlo (el panel; el detalle ya los tiene en su aviso). */
  withPatientLinks?: boolean;
}) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const can = useCan();
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const now = useNow();
  const single = useSingleAction();
  const hintsId = useId();
  const reasonRef = useRef<HTMLTextAreaElement>(null);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [reason, setReason] = useState('');
  const [reasonError, setReasonError] = useState<string | undefined>(undefined);
  const [failure, setFailure] = useState<TransitionFailure | null>(null);
  const [pendingName, setPendingName] = useState<TransitionName | null>(null);
  const canOverbook = can(PERMISSIONS.appointmentsOverbook);
  const available = availableTransitions(appointment, { can, me, now });

  /** Si el botón que tenía el foco ya no está (el turno cambió de estado), el foco no queda en el body. */
  // Después del render que lo saca (no con un setTimeout: en el navegador puede correr antes de ese render; desvío de
  // la 3.5 encontrado en Edge), y después de que ConfirmDialog devuelva el foco al botón que lo abrió, si sigue.
  // En el detalle, el turno nuevo llega por `setQueryData`, que React Query avisa en un render posterior: mientras el foco
  // siga en un botón del grupo, se espera (hasta 3 s); si el usuario lo llevó a otro lado, no se toca.
  const restoreFocus = useRef<number | null>(null);
  const groupRef = useRef<HTMLDivElement>(null);
  const keepFocus = () => {
    restoreFocus.current = Date.now();
  };

  useEffect(() => {
    const since = restoreFocus.current;

    if (since === null) {
      return;
    }

    const active = document.activeElement;

    if (active === null || active === document.body) {
      restoreFocus.current = null;
      focusAfter.current?.focus();
    } else if (!(groupRef.current?.contains(active) ?? false) || Date.now() - since > 3000) {
      restoreFocus.current = null;
    }
  });

  const closeDialog = () => {
    setDialog(null);
    setFailure(null);
    setReasonError(undefined);
    keepFocus();
  };

  const mutation = useMutation({
    mutationFn: ({ name, body }: Run) => {
      const transition = TRANSITIONS.find((item) => item.name === name);

      if (transition === undefined) {
        throw new Error(`Acción desconocida: ${name}`);
      }

      return runAppointmentTransition(appointment.id, transition.path, body);
    },
    onMutate: ({ name }) => {
      setPendingName(name);
      setFailure(null);
    },
    onSuccess: (response) => {
      afterAppointmentSaved(queryClient, response.data);
      onChanged?.(response.data);
      toast.show(response.message, 'success');
      setReason('');
      setDialog(null);
      keepFocus();
    },
    onError: (error, { name }) => {
      const found = transitionFailure(error);

      if (found.refresh || found.gone) {
        refreshAppointment(queryClient, appointment.id);
      }

      if (found.gone) {
        toast.show(found.message);
        setDialog(null);
        keepFocus();

        return;
      }

      if (found.reasonError !== null) {
        setReasonError(found.reasonError);

        return;
      }

      // La acción directa abre su diálogo con el error; cancelar y deshacer el ausente lo muestran en el suyo.
      if (name === 'cancel' || name === 'undo_no_show') {
        setFailure(found);
      } else {
        setDialog({ kind: 'error', name, failure: found });
      }
    },
    onSettled: () => {
      setPendingName(null);
      single.release();
    },
  });

  const run = (next: Run) => {
    if (single.tryStart()) {
      mutation.mutate(next);
    }
  };

  const submitCancel = () => {
    const found = cancellationReasonError(reason);
    setReasonError(found ?? undefined);

    if (found !== null) {
      reasonRef.current?.focus();

      return;
    }

    run({ name: 'cancel', body: { cancellation_reason: reason.trim() } });
  };

  const hints = available.filter((item) => item.disabledReason !== null);
  const sizeClass = size === 'sm' ? 'btn-sm' : '';
  const who = appointmentWho(appointment);

  if (available.length === 0 && dialog === null) {
    return null;
  }

  return (
    <>
      <div
        ref={groupRef}
        className="d-flex gap-2 flex-wrap appointment-transitions"
        role="group"
        aria-label="Cambiar el estado"
      >
        {available.map(({ transition, disabledReason }) => (
          <Button
            key={transition.name}
            variant={transition.name === 'cancel' ? 'danger' : 'secondary'}
            className={`${sizeClass} appointment-transition-${transition.name}`.trim()}
            disabled={disabledReason !== null || (pendingName !== null && !transition.dialog)}
            loading={pendingName === transition.name && !transition.dialog}
            focusableWhenInactive
            aria-describedby={disabledReason === null ? undefined : hintsId}
            onClick={() => {
              if (transition.name === 'cancel') {
                setReason('');
                setReasonError(undefined);
                setFailure(null);
                setDialog({ kind: 'cancel' });
              } else if (transition.name === 'undo_no_show') {
                setFailure(null);
                setDialog({ kind: 'undo' });
              } else {
                run({ name: transition.name });
              }
            }}
          >
            {transition.label}
          </Button>
        ))}
        {hints.length > 0 ? (
          <div id={hintsId} className="small text-secondary appointment-transition-hints">
            {hints.map(({ transition, disabledReason, needsPatient }) => (
              <div key={transition.name}>
                {transition.label}: {disabledReason}
                {needsPatient && withPatientLinks ? (
                  <PatientRequiredLinks appointment={appointment} />
                ) : null}
              </div>
            ))}
          </div>
        ) : null}
      </div>

      {dialog?.kind === 'cancel' ? (
        <ConfirmDialog
          open
          size="md"
          title={DIALOG_TITLES.cancel}
          confirmLabel={failure !== null && !failure.final ? 'Probá de nuevo' : 'Cancelar el turno'}
          cancelLabel="Volver"
          initialFocusRef={reasonRef}
          busy={pendingName === 'cancel'}
          error={failure?.message ?? null}
          canConfirm={failure?.final !== true}
          onConfirm={submitCancel}
          onClose={closeDialog}
        >
          <p>
            ¿Cancelás el turno de las {formatTime(appointment.starts_at)}
            {who === '' ? '' : ` de ${who}`}? El horario queda libre.
          </p>
          <form
            noValidate
            onSubmit={(event) => {
              event.preventDefault();
              submitCancel();
            }}
          >
            <TextareaField
              ref={reasonRef}
              name="cancellation_reason"
              label="Motivo de la cancelación"
              required
              rows={3}
              value={reason}
              error={reasonError}
              onChange={(event) => {
                setReason(event.target.value);
              }}
            />
          </form>
        </ConfirmDialog>
      ) : null}

      {dialog?.kind === 'undo' ? (
        <ConfirmDialog
          open
          tone="primary"
          title={DIALOG_TITLES.undo_no_show}
          confirmLabel={
            failure?.overlap === true
              ? UNDO_AS_OVERBOOK
              : failure !== null && !failure.final
                ? 'Probá de nuevo'
                : 'Deshacer el ausente'
          }
          busy={pendingName === 'undo_no_show'}
          error={failure?.message ?? null}
          canConfirm={failure === null || !failure.final || (failure.overlap && canOverbook)}
          onConfirm={() => {
            run(
              failure?.overlap === true && canOverbook
                ? { name: 'undo_no_show', body: { overbook: true } }
                : { name: 'undo_no_show' },
            );
          }}
          onClose={closeDialog}
        >
          {UNDO_NO_SHOW_TEXT}
        </ConfirmDialog>
      ) : null}

      {dialog?.kind === 'error' ? (
        <ConfirmDialog
          open
          tone="primary"
          title={DIALOG_TITLES[dialog.name]}
          confirmLabel="Probá de nuevo"
          busy={pendingName === dialog.name}
          error={dialog.failure.message}
          canConfirm={!dialog.failure.final}
          onConfirm={() => {
            run({ name: dialog.name });
          }}
          onClose={closeDialog}
        >
          {dialog.failure.needsPatient ? (
            <PatientRequiredLinks appointment={appointment} />
          ) : (
            'No se pudo hacer el cambio.'
          )}
        </ConfirmDialog>
      ) : null}
    </>
  );
}
