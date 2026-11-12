import { createMemoryHistory } from '@tanstack/react-router';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { createQueryClient } from '../lib/api/query';
import { App } from './App';

// Las consultas por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
function renderAt(url: string) {
  return render(
    <App
      queryClient={createQueryClient()}
      history={createMemoryHistory({ initialEntries: [url] })}
    />,
  );
}

describe('rutas', () => {
  it('/ muestra el inicio dentro del layout con sesión', async () => {
    renderAt('/');

    expect(await screen.findByText('Inicio', { selector: 'h2' })).toBeInTheDocument();
    const menu = screen.getByText('Inicio', { selector: '.nav-link-title' }).closest('a');
    expect(menu).toHaveAttribute('href', '/');
    expect(menu).toHaveClass('nav-link', 'active');
  });

  it('/ingresar muestra el formulario y lo valida', async () => {
    renderAt('/ingresar');

    expect(await screen.findByText('Ingresá a tu cuenta', { selector: 'h2' })).toBeInTheDocument();

    await userEvent.click(screen.getByText('Ingresar', { selector: 'button' }));

    expect(await screen.findByText('Ingresá un email válido.')).toBeInTheDocument();
    expect(screen.getByText('Ingresá tu contraseña.')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
  });

  it('/sin-permiso se ve', async () => {
    renderAt('/sin-permiso');

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
  });

  it('una URL desconocida muestra no encontrado', async () => {
    renderAt('/no-existe/para-nada');

    expect(await screen.findByText('No encontramos esta página')).toBeInTheDocument();
  });
});
