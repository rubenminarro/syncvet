import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vitest';
import { meFor } from '../test/roles';
import { server } from '../test/server';
import { meHandler, renderApp, sessionFor } from '../test/session';
import { COLLAPSED_MENU_QUERY } from './MainMenu';

/**
 * El menú plegable (2.5, desvío 10 de la 2.4): jsdom no tiene CSS ni medidas, así que el ancho de la pantalla se simula con
 * `matchMedia` (la consulta del menú responde `matches` según el "celular" del test).
 */

let narrow = false;
const listeners = new Set<() => void>();

function useScreen(isNarrow: boolean) {
  narrow = isNarrow;
  Object.defineProperty(window, 'matchMedia', {
    configurable: true,
    value: (query: string) => ({
      get matches() {
        return query === COLLAPSED_MENU_QUERY && narrow;
      },
      media: query,
      addEventListener: (_: string, listener: () => void) => listeners.add(listener),
      removeEventListener: (_: string, listener: () => void) => listeners.delete(listener),
    }),
  });
}

function resize(isNarrow: boolean) {
  narrow = isNarrow;
  act(() => {
    for (const listener of listeners) {
      listener();
    }
  });
}

afterEach(() => {
  listeners.clear();
  Reflect.deleteProperty(window, 'matchMedia');
});

const toggle = () => document.querySelector<HTMLButtonElement>('.main-menu-toggle');
const list = () => document.querySelector<HTMLUListElement>('.main-menu-list');
const menuLink = (label: string) =>
  screen.getByText(label, { selector: '.main-menu-list .nav-link-title' });

async function openHome() {
  server.use(meHandler(meFor('administrador')));
  const rendered = renderApp('/', { session: sessionFor() });
  await screen.findByText('Inicio', { selector: 'h2' });

  return rendered;
}

describe('el menú principal (2.5)', () => {
  it('en una pantalla ancha, la barra de siempre, sin el botón', async () => {
    useScreen(false);
    await openHome();

    expect(toggle()).toBeNull();
    expect(list()).not.toHaveAttribute('hidden');
    expect(list()).toHaveClass('flex-row');
    expect(menuLink('Configuración')).toBeInTheDocument();
  });

  it('al ancho de un celular, plegado detrás de "Menú", con aria-expanded y aria-controls', async () => {
    useScreen(true);
    await openHome();

    const button = toggle();
    expect(button).toHaveTextContent('Menú');
    expect(button).toHaveAttribute('aria-expanded', 'false');
    expect(button).toHaveAttribute('aria-controls', list()?.id);
    expect(list()).toHaveAttribute('hidden');
  });

  it('se abre con el teclado; Escape lo cierra y el foco vuelve al botón', async () => {
    useScreen(true);
    await openHome();
    const user = userEvent.setup();
    const button = toggle();

    button?.focus();
    await user.keyboard('{Enter}');

    expect(button).toHaveAttribute('aria-expanded', 'true');
    expect(list()).not.toHaveAttribute('hidden');
    expect(list()).toHaveClass('flex-column');

    // El foco entra a la lista con Tab, y Escape desde un ítem cierra el menú.
    await user.tab();
    expect(document.activeElement?.closest('.main-menu-list')).not.toBeNull();
    await user.keyboard('{Escape}');

    expect(button).toHaveAttribute('aria-expanded', 'false');
    expect(list()).toHaveAttribute('hidden');
    expect(button).toHaveFocus();

    // También con Espacio, y otra vez Escape con el foco en el botón.
    await user.keyboard(' ');
    expect(button).toHaveAttribute('aria-expanded', 'true');
    await user.keyboard('{Escape}');
    expect(button).toHaveAttribute('aria-expanded', 'false');
    expect(button).toHaveFocus();
  });

  it('elegir un ítem navega y cierra el menú', async () => {
    useScreen(true);
    const { history } = await openHome();

    const button = toggle();

    if (button === null) {
      throw new Error('Sin el botón del menú');
    }

    fireEvent.click(button);
    fireEvent.click(menuLink('Configuración'));

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion');
    });
    expect(toggle()).toHaveAttribute('aria-expanded', 'false');
    expect(list()).toHaveAttribute('hidden');
  });

  it('al ensanchar la pantalla (girar el celular), vuelve la barra sin el botón', async () => {
    useScreen(true);
    await openHome();
    expect(list()).toHaveAttribute('hidden');

    resize(false);

    expect(toggle()).toBeNull();
    expect(list()).not.toHaveAttribute('hidden');
  });

  it('abierto, se cierra al navegar sin elegir un ítem (el enlace de la marca o "Atrás") (revisión de la 2.5)', async () => {
    useScreen(true);
    const { history } = await openHome();
    const button = toggle();

    if (button === null) {
      throw new Error('Sin el botón del menú');
    }

    act(() => {
      history.push('/configuracion');
    });
    await screen.findByText('Configuración', { selector: 'h2' });
    fireEvent.click(button);
    expect(button).toHaveAttribute('aria-expanded', 'true');

    act(() => {
      history.back();
    });

    await waitFor(() => {
      expect(history.location.pathname).toBe('/');
    });
    expect(toggle()).toHaveAttribute('aria-expanded', 'false');
    expect(list()).toHaveAttribute('hidden');
  });

  it('al plegarse con el foco en un ítem, el foco pasa al botón; al volver a ensanchar y achicar, cerrado (revisión de la 2.5)', async () => {
    useScreen(false);
    await openHome();

    menuLink('Configuración').closest('a')?.focus();
    resize(true);

    const button = toggle();

    if (button === null) {
      throw new Error('Sin el botón del menú');
    }

    expect(button).toHaveFocus();
    expect(list()).toHaveAttribute('hidden');

    fireEvent.click(button);
    expect(button).toHaveAttribute('aria-expanded', 'true');
    resize(false);
    resize(true);

    expect(toggle()).toHaveAttribute('aria-expanded', 'false');
    expect(list()).toHaveAttribute('hidden');
  });
});
