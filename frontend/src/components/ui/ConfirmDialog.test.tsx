import { fireEvent, render, screen } from '@testing-library/react';
import { StrictMode, useRef, useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { ConfirmDialog } from './ConfirmDialog';

function Harness({
  onConfirm = vi.fn(),
  onClose,
  busy = false,
  error = null,
  canConfirm = true,
  confirmDisabled = false,
  fieldInvalid,
}: {
  onConfirm?: () => void;
  onClose?: () => void;
  busy?: boolean;
  error?: string | null;
  canConfirm?: boolean;
  confirmDisabled?: boolean;
  /** Con un valor, el diálogo lleva un campo; true: con error (aria-invalid, como TextField). */
  fieldInvalid?: boolean;
}) {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        type="button"
        onClick={() => {
          setOpen(true);
        }}
      >
        Abrir
      </button>
      <ConfirmDialog
        open={open}
        title="Eliminar el cliente"
        confirmLabel="Eliminar a Laura Martínez"
        onConfirm={onConfirm}
        onClose={() => {
          onClose?.();
          setOpen(false);
        }}
        busy={busy}
        error={error}
        canConfirm={canConfirm}
        confirmDisabled={confirmDisabled}
      >
        ¿Querés eliminar a Laura Martínez?
        {fieldInvalid === undefined ? null : (
          <input name="campo" aria-label="Campo" aria-invalid={fieldInvalid ? true : undefined} />
        )}
      </ConfirmDialog>
    </>
  );
}

const dialog = () => document.querySelector('[role="dialog"]');

const FORBIDDEN = 'No tenés permiso para esta acción.';

function element(selector: string): HTMLElement {
  const node = document.querySelector(selector);

  if (!(node instanceof HTMLElement)) {
    throw new Error(`Falta ${selector}.`);
  }

  return node;
}

function required(selector: string): Element {
  const node = document.querySelector(selector);

  if (node === null) {
    throw new Error(`Falta ${selector}.`);
  }

  return node;
}
const button = (text: string) => screen.getByText(text, { selector: 'button' });

function open() {
  const opener = button('Abrir');
  opener.focus();
  fireEvent.click(opener);

  return opener;
}

describe('ConfirmDialog', () => {
  it('es un diálogo modal con su título y su texto, y el foco empieza en "Cancelar"', () => {
    render(<Harness />);
    expect(dialog()).toBeNull();
    open();

    const node = dialog();
    expect(node).toHaveAttribute('aria-modal', 'true');
    expect(node).toHaveAccessibleName('Eliminar el cliente');
    expect(node).toHaveAccessibleDescription('¿Querés eliminar a Laura Martínez?');
    expect(document.activeElement).toBe(button('Cancelar'));
    expect(button('Eliminar a Laura Martínez')).toHaveClass('btn-danger');
  });

  it('"Cancelar" cierra sin confirmar y el foco vuelve al botón que lo abrió', () => {
    const onConfirm = vi.fn();
    render(<Harness onConfirm={onConfirm} />);
    const opener = open();

    fireEvent.click(button('Cancelar'));

    expect(dialog()).toBeNull();
    expect(onConfirm).not.toHaveBeenCalled();
    expect(document.activeElement).toBe(opener);
  });

  it('Escape y el clic afuera cierran', () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    open();
    fireEvent.keyDown(button('Cancelar'), { key: 'Escape' });
    expect(dialog()).toBeNull();

    open();
    fireEvent.click(required('.modal'));
    expect(dialog()).toBeNull();
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('Tab y Shift+Tab no salen del diálogo', () => {
    render(<Harness />);
    open();
    const cancel = button('Cancelar');
    const confirm = button('Eliminar a Laura Martínez');

    confirm.focus();
    fireEvent.keyDown(confirm, { key: 'Tab' });
    expect(document.activeElement).toBe(cancel);

    fireEvent.keyDown(cancel, { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(confirm);
  });

  it('el botón rojo confirma', () => {
    const onConfirm = vi.fn();
    render(<Harness onConfirm={onConfirm} />);
    open();
    fireEvent.click(button('Eliminar a Laura Martínez'));

    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it('mientras espera la API, no se cierra ni con Escape', () => {
    const onClose = vi.fn();
    render(<Harness busy onClose={onClose} />);
    open();
    fireEvent.keyDown(required('[role="dialog"]'), { key: 'Escape' });

    expect(dialog()).not.toBeNull();
    expect(onClose).not.toHaveBeenCalled();
    // aria-disabled, no disabled: el botón no suelta el foco mientras espera.
    expect(button('Eliminar a Laura Martínez')).toHaveAttribute('aria-disabled', 'true');
    expect(button('Eliminar a Laura Martínez')).not.toBeDisabled();
  });

  it('sin la acción (un *_IN_USE): el error adentro y solo "Cerrar"', () => {
    render(<Harness canConfirm={false} error="El cliente tiene pacientes." />);
    open();

    expect(screen.getByText('El cliente tiene pacientes.')).toBeInTheDocument();
    expect(screen.queryByText('Eliminar a Laura Martínez')).toBeNull();
    expect(document.activeElement).toBe(button('Cerrar'));
  });

  // Revisión de la 1.2, caso A: un clic en el texto deja el foco en el contenedor del diálogo; Shift+Tab lo sacaba al botón
  // de la página (detrás del fondo) y, desde ahí, Escape ya no cerraba.
  it('caso A: desde el contenedor (un clic en el texto), Shift+Tab va al último botón, Tab al primero y Escape cierra', () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    open();
    const container = element('.modal');

    container.focus();
    fireEvent.keyDown(container, { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(button('Eliminar a Laura Martínez'));

    container.focus();
    fireEvent.keyDown(container, { key: 'Tab' });
    expect(document.activeElement).toBe(button('Cancelar'));

    container.focus();
    fireEvent.keyDown(container, { key: 'Escape' });
    expect(dialog()).toBeNull();
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('caso A: si el foco sale del diálogo vuelve a "Cancelar", y las teclas se escuchan en document', () => {
    render(<Harness />);
    const opener = open();

    opener.focus();
    expect(document.activeElement).toBe(button('Cancelar'));

    // Con el foco en body (fuera del diálogo), Tab y Escape también llegan.
    button('Cancelar').blur();
    expect(document.activeElement).toBe(document.body);
    fireEvent.keyDown(document.body, { key: 'Tab' });
    expect(document.activeElement).toBe(button('Cancelar'));
    fireEvent.keyDown(document.body, { key: 'Escape' });
    expect(dialog()).toBeNull();
  });

  // Caso B: el botón rojo se deshabilitaba mientras esperaba la API y el foco caía en body; después de un error (un 403),
  // ni Escape ni Tab hacían nada.
  it('caso B: mientras espera, el botón rojo conserva el foco y no confirma otra vez; al terminar, el foco vuelve a "Cancelar"', () => {
    const onConfirm = vi.fn();
    const { rerender } = render(<Harness onConfirm={onConfirm} />);
    open();
    button('Eliminar a Laura Martínez').focus();
    fireEvent.click(button('Eliminar a Laura Martínez'));
    expect(onConfirm).toHaveBeenCalledTimes(1);

    rerender(<Harness onConfirm={onConfirm} busy />);
    expect(document.activeElement).toBe(button('Eliminar a Laura Martínez'));
    fireEvent.click(button('Eliminar a Laura Martínez'));
    fireEvent.keyDown(document.body, { key: 'Escape' });
    expect(onConfirm).toHaveBeenCalledTimes(1);
    expect(dialog()).not.toBeNull();

    // La API respondió con un error: la acción no se hizo y el diálogo sigue.
    rerender(<Harness onConfirm={onConfirm} error={FORBIDDEN} />);
    expect(screen.getByText(FORBIDDEN)).toBeInTheDocument();
    expect(document.activeElement).toBe(button('Cancelar'));
    fireEvent.keyDown(document.body, { key: 'Escape' });
    expect(dialog()).toBeNull();
  });

  it('al terminar la espera con un campo con error (un 422 de la API), el foco va al campo; sin error, a "Cancelar"', () => {
    const { rerender } = render(<Harness fieldInvalid={false} />);
    open();
    fireEvent.click(button('Eliminar a Laura Martínez'));
    rerender(<Harness fieldInvalid={false} busy />);

    rerender(<Harness fieldInvalid />);
    expect(document.activeElement).toBe(element('input[name="campo"]'));

    // Otra vez, ahora sin error en el campo (un 403): a "Cancelar".
    fireEvent.click(button('Eliminar a Laura Martínez'));
    rerender(<Harness fieldInvalid={false} busy />);
    rerender(<Harness fieldInvalid={false} error={FORBIDDEN} />);
    expect(document.activeElement).toBe(button('Cancelar'));
  });

  it('confirmDisabled: el botón de la acción queda inactivo (aria-disabled) sin soltar el foco y no confirma', () => {
    const onConfirm = vi.fn();
    const { rerender } = render(<Harness onConfirm={onConfirm} confirmDisabled />);
    open();

    expect(button('Eliminar a Laura Martínez')).toHaveAttribute('aria-disabled', 'true');
    expect(button('Eliminar a Laura Martínez')).not.toBeDisabled();
    fireEvent.click(button('Eliminar a Laura Martínez'));
    expect(onConfirm).not.toHaveBeenCalled();

    rerender(<Harness onConfirm={onConfirm} />);
    expect(button('Eliminar a Laura Martínez')).not.toHaveAttribute('aria-disabled');
    fireEvent.click(button('Eliminar a Laura Martínez'));
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it('el fondo no se desplaza mientras está abierto; al cerrar, vuelve como estaba', () => {
    document.body.style.overflow = 'auto';
    render(<Harness />);
    open();
    expect(document.body.style.overflow).toBe('hidden');

    fireEvent.click(button('Cancelar'));
    expect(document.body.style.overflow).toBe('auto');
    document.body.style.overflow = '';
  });
});

describe('ConfirmDialog: el foco inicial y el botón de cierre propios (3.5)', () => {
  function WithField() {
    const [open, setOpen] = useState(false);
    const fieldRef = useRef<HTMLTextAreaElement>(null);

    return (
      <StrictMode>
        <button
          type="button"
          onClick={() => {
            setOpen(true);
          }}
        >
          Abrir
        </button>
        <ConfirmDialog
          open={open}
          title="Cancelar el turno"
          confirmLabel="Cancelar el turno"
          cancelLabel="Volver"
          initialFocusRef={fieldRef}
          onConfirm={vi.fn()}
          onClose={() => {
            setOpen(false);
          }}
        >
          <textarea ref={fieldRef} aria-label="Motivo" />
        </ConfirmDialog>
      </StrictMode>
    );
  }

  it('con initialFocusRef, el foco va al campo (también en StrictMode); "Volver" en lugar de "Cancelar"', () => {
    render(<WithField />);
    fireEvent.click(screen.getByText('Abrir'));

    expect(document.activeElement).toBe(screen.getByLabelText('Motivo'));
    expect(screen.getByText('Volver')).toBeTruthy();
    expect(screen.queryByText('Cancelar')).toBeNull();
  });
});
