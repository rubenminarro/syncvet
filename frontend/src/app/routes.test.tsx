import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { server } from '../test/server';
import { meHandler, renderApp, sessionFor } from '../test/session';

// Las consultas por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
describe('rutas', () => {
  it('/ muestra el inicio dentro del layout con sesión', async () => {
    server.use(meHandler());
    renderApp('/', { session: sessionFor() });

    expect(await screen.findByText('Inicio', { selector: 'h2' })).toBeInTheDocument();
    const menu = screen.getByText('Inicio', { selector: '.nav-link-title' }).closest('a');
    expect(menu).toHaveAttribute('href', '/');
    expect(menu).toHaveClass('nav-link', 'active');
  });

  it('/ingresar muestra el formulario y lo valida', async () => {
    renderApp('/ingresar');

    expect(await screen.findByText('Ingresá a tu cuenta', { selector: 'h2' })).toBeInTheDocument();

    await userEvent.click(screen.getByText('Ingresar', { selector: 'button' }));

    expect(await screen.findByText('Ingresá un email válido.')).toBeInTheDocument();
    expect(screen.getByText('Ingresá tu contraseña.')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
  });

  it('/sin-permiso se ve', async () => {
    server.use(meHandler());
    renderApp('/sin-permiso', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
  });

  it('una URL desconocida muestra no encontrado', async () => {
    renderApp('/no-existe/para-nada');

    expect(await screen.findByText('No encontramos esta página')).toBeInTheDocument();
  });
});
