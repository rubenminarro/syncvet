import { useEffect, useEffectEvent, useId, useRef, type ReactNode } from 'react';
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
  /** El botón de la acción inactivo (todavía falta elegir algo, como el dueño nuevo). */
  confirmDisabled?: boolean;
  /** danger (por defecto): borrar o anular, en rojo; primary: una acción que no borra (registrar, cambiar de dueño). */
  tone?: 'danger' | 'primary';
  /** sm (por defecto): una pregunta; md: con campos o una lista (el fallecimiento, el dueño nuevo). */
  size?: 'sm' | 'md';
}

// Una lista sin espacios: el test de voseo no la toma por un texto de la interfaz. Los botones inactivos del diálogo usan
// aria-disabled (siguen en la lista): el foco no se pierde mientras se espera la API.
const FOCUSABLE = [
  'button:not([disabled])',
  '[href]',
  'input',
  'select',
  'textarea',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

/**
 * Confirmación antes de una acción que no se deshace (el modal de Tabler, sin su JS): role="dialog" con aria-modal y el
 * foco en "Cancelar" al abrir. Mientras está abierto (revisión de la 1.2): las teclas se escuchan en `document` (Escape
 * cierra y Tab y Shift+Tab recorren solo el diálogo, esté el foco donde esté), el foco vuelve a "Cancelar" si sale del
 * diálogo o al terminar la espera, los botones inactivos no sueltan el foco (aria-disabled) y el fondo no se desplaza. Al
 * cerrar, el foco vuelve a donde estaba. El error de negocio se muestra adentro.
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
  confirmDisabled = false,
  tone = 'danger',
  size = 'sm',
}: ConfirmDialogProps) {
  const titleId = useId();
  const bodyId = useId();
  const rootRef = useRef<HTMLDivElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const cancelRef = useRef<HTMLButtonElement>(null);
  const wasBusy = useRef(busy);

  const close = () => {
    if (!busy) {
      onClose();
    }
  };

  const onDocumentKeyDown = useEffectEvent((event: KeyboardEvent) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();

      return;
    }

    if (event.key !== 'Tab') {
      return;
    }

    // Tab siempre lo mueve el diálogo: desde un botón, el siguiente (o el primero después del último); desde afuera o
    // desde el contenedor (un clic en el texto), el primero, o el último con Shift.
    event.preventDefault();
    const focusable = Array.from(dialogRef.current?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? []);

    if (focusable.length === 0) {
      return;
    }

    const index =
      document.activeElement instanceof HTMLElement
        ? focusable.indexOf(document.activeElement)
        : -1;
    let next: number;

    if (event.shiftKey) {
      next = index <= 0 ? focusable.length - 1 : index - 1;
    } else {
      next = index === -1 || index === focusable.length - 1 ? 0 : index + 1;
    }

    focusable[next]?.focus();
  });

  // El foco salió del diálogo (un programa, un lector de pantalla, el fondo): de vuelta a "Cancelar".
  const onDocumentFocusIn = useEffectEvent((event: FocusEvent) => {
    if (
      rootRef.current !== null &&
      event.target instanceof Node &&
      !rootRef.current.contains(event.target)
    ) {
      cancelRef.current?.focus();
    }
  });

  // Al abrir: el foco en "Cancelar", las teclas y el foco escuchados en document y el fondo sin desplazamiento. Al cerrar,
  // todo vuelve como estaba y el foco, al botón que lo abrió.
  useEffect(() => {
    if (!open) {
      return;
    }

    const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const overflow = document.body.style.overflow;
    const keydown = (event: KeyboardEvent) => {
      onDocumentKeyDown(event);
    };
    const focusin = (event: FocusEvent) => {
      onDocumentFocusIn(event);
    };

    cancelRef.current?.focus();
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', keydown);
    document.addEventListener('focusin', focusin);

    return () => {
      document.removeEventListener('keydown', keydown);
      document.removeEventListener('focusin', focusin);
      document.body.style.overflow = overflow;
      previous?.focus();
    };
  }, [open]);

  // Al terminar la espera (con un error: la acción no se hizo), el foco vuelve a "Cancelar" (o "Cerrar"). También si el
  // botón rojo desaparece (un *_IN_USE).
  useEffect(() => {
    if (open && ((wasBusy.current && !busy) || !canConfirm)) {
      cancelRef.current?.focus();
    }

    wasBusy.current = busy;
  }, [open, busy, canConfirm]);

  if (!open) {
    return null;
  }

  return (
    <>
      <div
        ref={rootRef}
        className="modal modal-blur fade show d-block confirm-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={bodyId}
        tabIndex={-1}
        onClick={(event) => {
          if (event.target === event.currentTarget) {
            close();
          }
        }}
      >
        <div
          className={`modal-dialog${size === 'sm' ? ' modal-sm' : ''} modal-dialog-centered`}
          role="document"
          ref={dialogRef}
        >
          <div className="modal-content">
            <div className={`modal-status bg-${tone}`} />
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
                focusableWhenInactive
                onClick={close}
              >
                {canConfirm ? 'Cancelar' : 'Cerrar'}
              </Button>
              {canConfirm ? (
                <Button
                  variant={tone}
                  loading={busy}
                  disabled={confirmDisabled}
                  focusableWhenInactive
                  onClick={onConfirm}
                >
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
