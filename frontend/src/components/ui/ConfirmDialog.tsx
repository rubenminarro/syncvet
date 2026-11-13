import { useEffect, useId, useRef, type KeyboardEvent, type ReactNode } from 'react';
import { Alert } from './Alert';
import { Button } from './Button';

interface ConfirmDialogProps {
  open: boolean;
  title: string;
  /** El texto de la pregunta, con el nombre del registro. */
  children: ReactNode;
  /** El botón rojo ("Eliminar a Juan Pérez"). */
  confirmLabel: string;
  onConfirm: () => void;
  /** Cancelar, Cerrar, Escape o un clic afuera. */
  onClose: () => void;
  /** Mientras se espera la API: el botón con su indicador y el diálogo no se cierra. */
  busy?: boolean;
  /** El error de la acción (el `message` de la API), dentro del diálogo. */
  error?: string | null;
  /** false: la acción no se puede hacer (un `*_IN_USE`): solo queda "Cerrar". */
  canConfirm?: boolean;
}

// Una lista sin espacios: el test de voseo no la toma por un texto de la interfaz.
const FOCUSABLE = [
  'button:not([disabled])',
  '[href]',
  'input',
  'select',
  'textarea',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

/**
 * Confirmación antes de una acción que no se deshace (el modal de Tabler, sin su JS): role="dialog" con aria-modal, el
 * foco en "Cancelar" al abrir, Tab y Shift+Tab sin salir del diálogo, Escape cierra y, al cerrar, el foco vuelve a donde
 * estaba. El error de negocio se muestra adentro.
 */
export function ConfirmDialog({
  open,
  title,
  children,
  confirmLabel,
  onConfirm,
  onClose,
  busy = false,
  error = null,
  canConfirm = true,
}: ConfirmDialogProps) {
  const titleId = useId();
  const bodyId = useId();
  const dialogRef = useRef<HTMLDivElement>(null);
  const cancelRef = useRef<HTMLButtonElement>(null);

  // Al abrir, el foco en "Cancelar"; al cerrar, de vuelta al botón que lo abrió.
  useEffect(() => {
    if (!open) {
      return;
    }

    const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    cancelRef.current?.focus();

    return () => {
      previous?.focus();
    };
  }, [open]);

  // Sin la acción (llegó un *_IN_USE), el botón rojo desaparece: el foco pasa a "Cerrar".
  useEffect(() => {
    if (open && !canConfirm) {
      cancelRef.current?.focus();
    }
  }, [open, canConfirm]);

  if (!open) {
    return null;
  }

  const close = () => {
    if (!busy) {
      onClose();
    }
  };

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') {
      event.stopPropagation();
      close();

      return;
    }

    if (event.key !== 'Tab' || dialogRef.current === null) {
      return;
    }

    const focusable = Array.from(dialogRef.current.querySelectorAll<HTMLElement>(FOCUSABLE));
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (first === undefined || last === undefined) {
      return;
    }

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };

  return (
    <>
      <div
        className="modal modal-blur fade show d-block confirm-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={bodyId}
        tabIndex={-1}
        onKeyDown={onKeyDown}
        onClick={(event) => {
          if (event.target === event.currentTarget) {
            close();
          }
        }}
      >
        <div
          className="modal-dialog modal-sm modal-dialog-centered"
          role="document"
          ref={dialogRef}
        >
          <div className="modal-content">
            <div className="modal-status bg-danger" />
            <div className="modal-body">
              <h3 className="modal-title mb-2" id={titleId}>
                {title}
              </h3>
              <div id={bodyId} className="text-secondary">
                {children}
              </div>
              {error !== null ? (
                <div className="mt-3 mb-0">
                  <Alert title={error} />
                </div>
              ) : null}
            </div>
            <div className="modal-footer">
              <Button
                ref={cancelRef}
                variant="secondary"
                className="me-auto"
                disabled={busy}
                onClick={close}
              >
                {canConfirm ? 'Cancelar' : 'Cerrar'}
              </Button>
              {canConfirm ? (
                <Button variant="danger" loading={busy} onClick={onConfirm}>
                  {confirmLabel}
                </Button>
              ) : null}
            </div>
          </div>
        </div>
      </div>
      <div className="modal-backdrop fade show" />
    </>
  );
}
