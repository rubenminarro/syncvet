import { QueryClientProvider } from '@tanstack/react-query';
import {
  createMemoryHistory,
  createRootRoute,
  createRouter,
  RouterProvider,
} from '@tanstack/react-router';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { ToastProvider } from '../../components/ui';
import { createQueryClient } from '../../lib/api/query';
import { authKeys } from '../auth/api';
import { failure, success } from '../../test/api';
import { pagination } from '../../test/clients';
import { meFor, type Role } from '../../test/roles';
import { ROOM_ONE } from '../../test/schedules';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { BLOCK_MESSAGES } from './blocks';
import { ScheduleBlocks } from './ScheduleBlocks';
import type { AffectedAppointment, ScheduleBlock } from './types';

// Por etiqueta o por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
function field(label: string): HTMLInputElement | HTMLTextAreaElement {
  const found = Array.from(document.querySelectorAll('label')).find(
    (element) => element.textContent.replace('*', '').trim() === label,
  );
  const input = found?.htmlFor ? document.getElementById(found.htmlFor) : null;

  if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
    throw new Error(`Sin el campo ${label}`);
  }

  return input;
}

function type(label: string, value: string) {
  fireEvent.change(field(label), { target: { value } });
}

const fieldError = (label: string) =>
  document.getElementById(`${field(label).id}-error`)?.textContent ?? null;

const dialog = () => document.querySelector<HTMLElement>('[role="dialog"]');

function findDialogButton(label: string): Promise<HTMLElement> {
  return waitFor(() => {
    const found = Array.from(dialog()?.querySelectorAll('button') ?? []).find(
      (element) => element.textContent.trim() === label,
    );

    if (found === undefined) {
      throw new Error(`Sin el botón ${label} en el diálogo`);
    }

    return found;
  });
}

const ADMIN = meFor('administrador');

/** Un bloqueo de una hora del viernes 20/11/2026 (09:00 a 10:00 en Asunción) y otro de dos días enteros. */
const ONE_HOUR: ScheduleBlock = {
  id: 31,
  schedule_id: ROOM_ONE.id,
  starts_at: '2026-11-20T12:00:00.000000Z',
  ends_at: '2026-11-20T13:00:00.000000Z',
  reason: 'Capacitación',
  created_by: { id: 1, name: 'Ana Admin' },
  created_at: '2026-11-18T12:00:00.000000Z',
};

const TWO_DAYS: ScheduleBlock = {
  ...ONE_HOUR,
  id: 32,
  starts_at: '2026-12-24T03:00:00.000000Z',
  ends_at: '2026-12-26T03:00:00.000000Z',
  reason: null,
};

const LUNA: AffectedAppointment = {
  id: 501,
  starts_at: '2026-11-20T12:00:00.000000Z',
  ends_at: '2026-11-20T12:30:00.000000Z',
  status: { value: 'confirmed', label: 'Confirmado' },
  patient: { id: 9, code: 'PAC-0009', name: 'Luna' },
  client: { id: 3, display_name: 'Laura Martínez' },
  contact_name: null,
  contact_phone: null,
};

const CONTACT: AffectedAppointment = {
  ...LUNA,
  id: 502,
  starts_at: '2026-11-20T12:30:00.000000Z',
  ends_at: '2026-11-20T13:00:00.000000Z',
  status: { value: 'pending', label: 'A confirmar' },
  patient: null,
  client: null,
  contact_name: 'Pedro Gómez',
  contact_phone: '+595981100001',
};

function blocksHandler(urls: URL[], current: () => ScheduleBlock[]) {
  return http.get('/api/schedules/:id/blocks', ({ request }) => {
    urls.push(new URL(request.url));
    const items = current();

    return success(items, { meta: pagination({ total: items.length }) });
  });
}

async function openDetail(extra: Parameters<typeof server.use> = [], urls: URL[] = []) {
  server.use(
    ...extra,
    meHandler(ADMIN),
    http.get('/api/schedules/:id', () => success(ROOM_ONE)),
    blocksHandler(urls, () => [ONE_HOUR, TWO_DAYS]),
  );
  const app = renderApp(`/configuracion/agendas/${String(ROOM_ONE.id)}`, {
    session: sessionFor(),
  });
  await screen.findByText('20/11/2026, de 09:00 a 10:00', { selector: '.block-period' });

  return app;
}

async function openNewDialog() {
  fireEvent.click(await screen.findByText('Nuevo bloqueo', { selector: 'button' }));
  await findDialogButton('Crear el bloqueo');
}

function storeHandler(bodies: unknown[], affected: AffectedAppointment[] = []) {
  return http.post('/api/schedules/:id/blocks', async ({ request }) => {
    bodies.push(await request.json());

    return success(
      { ...ONE_HOUR, id: 40, affected_appointments: affected },
      { status: 201, message: 'Bloqueo creado correctamente.' },
    );
  });
}

describe('/configuracion/agendas/$id: la tarjeta "Bloqueos"', () => {
  it('el administrador ve los que terminan desde hoy (sin from ni to), con el período en Asunción, "Nuevo bloqueo" y "Eliminar"', async () => {
    const urls: URL[] = [];
    await openDetail([], urls);

    expect(screen.getByText('Del 24/12/2026 al 25/12/2026, todo el día')).toBeTruthy();
    expect(screen.getByText('Capacitación', { selector: '.block-reason' })).toBeTruthy();
    expect(document.querySelectorAll('.block-delete')).toHaveLength(2);
    expect(screen.getByText('Nuevo bloqueo', { selector: 'button' })).toBeTruthy();

    const sent = urls[0];
    expect(sent?.pathname).toBe('/api/schedules/7/blocks');
    expect(sent?.searchParams.get('from')).toBeNull();
    expect(sent?.searchParams.get('to')).toBeNull();
    expect(sent?.searchParams.get('page')).toBe('1');
    expect(sent?.searchParams.get('per_page')).toBe('20');
  });

  it.each<Role>(['recepcionista', 'veterinario'])(
    '%s (schedule-blocks.index, sin .store ni .destroy): ve la lista sin "Nuevo bloqueo" ni "Eliminar"',
    async (role) => {
      const me = meFor(role);
      expect(me.permissions).toContain('schedule-blocks.index');
      expect(me.permissions).not.toContain('schedule-blocks.store');
      expect(me.permissions).not.toContain('schedule-blocks.destroy');
      server.use(blocksHandler([], () => [ONE_HOUR]));

      // La ruta del detalle es del administrador (schedules.update): la tarjeta, sola, con los permisos reales del rol.
      const queryClient = createQueryClient({ retryDelay: 0 });
      queryClient.setQueryData(authKeys.me, me);
      const router = createRouter({
        routeTree: createRootRoute({ component: () => <ScheduleBlocks schedule={ROOM_ONE} /> }),
        history: createMemoryHistory({ initialEntries: ['/'] }),
      });
      render(
        <QueryClientProvider client={queryClient}>
          <ToastProvider>
            <RouterProvider router={router} />
          </ToastProvider>
        </QueryClientProvider>,
      );

      await screen.findByText('20/11/2026, de 09:00 a 10:00', { selector: '.block-period' });
      expect(screen.queryByText('Nuevo bloqueo', { selector: 'button' })).toBeNull();
      expect(document.querySelector('.block-delete')).toBeNull();
    },
  );

  it('el recepcionista no entra al detalle de la agenda: /sin-permiso', async () => {
    server.use(meHandler(meFor('recepcionista')));
    const { history } = renderApp(`/configuracion/agendas/${String(ROOM_ONE.id)}`, {
      session: sessionFor(),
    });

    await waitFor(() => {
      expect(history.location.pathname).toBe('/sin-permiso');
    });
  });
});

describe('Nuevo bloqueo', () => {
  it('09:00 a 10:00: el cuerpo con -03:00 (la PC en UTC), un solo pedido con doble clic; sin turnos adentro, cierra y vuelve a pedir la lista', async () => {
    const bodies: unknown[] = [];
    const urls: URL[] = [];
    await openDetail([storeHandler(bodies)], urls);
    await openNewDialog();

    type('Fecha desde', '2026-11-20');
    type('Hora desde', '09:00');
    type('Fecha hasta', '2026-11-20');
    type('Hora hasta', '10:00');
    type('Motivo', ' Capacitación ');
    const create = await findDialogButton('Crear el bloqueo');
    fireEvent.click(create);
    fireEvent.click(create);

    await screen.findByText('Bloqueo creado correctamente.');
    expect(bodies).toEqual([
      {
        starts_at: '2026-11-20T09:00:00.000-03:00',
        ends_at: '2026-11-20T10:00:00.000-03:00',
        reason: 'Capacitación',
      },
    ]);
    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    await waitFor(() => {
      expect(urls).toHaveLength(2);
    });
  });

  it('"Todo el día" oculta las horas y manda de las 00:00 de la fecha desde a las 00:00 del día siguiente a la fecha hasta', async () => {
    const bodies: unknown[] = [];
    await openDetail([storeHandler(bodies)]);
    await openNewDialog();

    fireEvent.click(field('Todo el día'));
    expect(() => field('Hora desde')).toThrow();
    type('Fecha desde', '2026-12-24');
    type('Fecha hasta', '2026-12-25');
    fireEvent.click(await findDialogButton('Crear el bloqueo'));

    await screen.findByText('Bloqueo creado correctamente.');
    expect(bodies).toEqual([
      {
        starts_at: '2026-12-24T00:00:00.000-03:00',
        ends_at: '2026-12-26T00:00:00.000-03:00',
        reason: null,
      },
    ]);
  });

  it('con turnos adentro: la API crea el bloqueo y el diálogo pasa a mostrarlos (día y hora en Asunción, quién y el estado), con solo "Cerrar"', async () => {
    await openDetail([storeHandler([], [LUNA, CONTACT])]);
    await openNewDialog();

    type('Hora desde', '09:00');
    type('Hora hasta', '10:00');
    type('Fecha desde', '2026-11-20');
    type('Fecha hasta', '2026-11-20');
    fireEvent.click(await findDialogButton('Crear el bloqueo'));

    await screen.findByText('Bloqueo creado, con turnos adentro');
    const items = Array.from(document.querySelectorAll('.block-affected li')).map((item) =>
      Array.from(item.children)
        .map((line) => line.textContent.trim())
        .join(' | '),
    );
    expect(items).toEqual([
      'Viernes 20/11/2026, 09:00 a 09:30 | Luna, de Laura Martínez · Confirmado',
      'Viernes 20/11/2026, 09:30 a 10:00 | Pedro Gómez, +595981100001 · A confirmar',
    ]);
    expect(dialog()?.textContent).toContain(
      'Estos turnos quedaron dentro del bloqueo: reprogramalos o cancelalos.',
    );
    expect(document.querySelector('.block-form')).toBeNull();

    fireEvent.click(await findDialogButton('Cerrar'));
    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
  });

  it('el fin antes del inicio el mismo día: el mensaje de la API en "Hora hasta", el foco ahí y sin pedido', async () => {
    const bodies: unknown[] = [];
    await openDetail([storeHandler(bodies)]);
    await openNewDialog();

    type('Hora desde', '10:00');
    type('Hora hasta', '09:00');
    fireEvent.click(await findDialogButton('Crear el bloqueo'));

    await waitFor(() => {
      expect(fieldError('Hora hasta')).toBe(BLOCK_MESSAGES.endsBeforeStart);
    });
    expect(document.activeElement).toBe(field('Hora hasta'));
    expect(bodies).toEqual([]);
  });

  it('422: starts_at y ends_at a "Fecha desde" y "Fecha hasta"; otra clave, a la alerta', async () => {
    await openDetail([
      http.post('/api/schedules/:id/blocks', () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', {
          errors: {
            starts_at: ['La hora debe estar en múltiplos de 5 minutos, sin segundos.'],
            ends_at: ['El bloqueo no puede durar más de 366 días.'],
            schedule: ['La agenda no existe.'],
          },
        }),
      ),
    ]);
    await openNewDialog();

    type('Hora desde', '09:00');
    type('Hora hasta', '10:00');
    fireEvent.click(await findDialogButton('Crear el bloqueo'));

    await waitFor(() => {
      expect(fieldError('Fecha desde')).toBe(
        'La hora debe estar en múltiplos de 5 minutos, sin segundos.',
      );
    });
    expect(fieldError('Fecha hasta')).toBe('El bloqueo no puede durar más de 366 días.');
    expect(dialog()?.textContent).toContain('La agenda no existe.');
    await waitFor(() => {
      expect(document.activeElement).toBe(field('Fecha desde'));
    });
  });

  it('409 CONFLICT: el aviso en el diálogo, con el formulario como estaba para probar de nuevo', async () => {
    let calls = 0;
    await openDetail([
      http.post('/api/schedules/:id/blocks', () => {
        calls += 1;

        return calls === 1
          ? failure(409, 'CONFLICT', 'Los datos cambiaron mientras se guardaban.')
          : success(
              { ...ONE_HOUR, affected_appointments: [] },
              { status: 201, message: 'Bloqueo creado correctamente.' },
            );
      }),
    ]);
    await openNewDialog();

    type('Hora desde', '09:00');
    type('Hora hasta', '10:00');
    fireEvent.click(await findDialogButton('Crear el bloqueo'));

    await screen.findByText(/Alguien cambió estos datos/);
    expect(field('Hora desde').value).toBe('09:00');
    fireEvent.click(await findDialogButton('Crear el bloqueo'));
    await screen.findByText('Bloqueo creado correctamente.');
    expect(calls).toBe(2);
  });
});

describe('Eliminar un bloqueo', () => {
  it('con la confirmación: DELETE schedule-blocks/{id}, el aviso y la lista pedida de nuevo', async () => {
    const deleted: string[] = [];
    const urls: URL[] = [];
    await openDetail(
      [
        http.delete('/api/schedule-blocks/:id', ({ params }) => {
          deleted.push(String(params.id));

          return success(null, { message: 'Bloqueo eliminado correctamente.' });
        }),
      ],
      urls,
    );

    fireEvent.click(
      screen.getByLabelText('Eliminar el bloqueo 20/11/2026, de 09:00 a 10:00', {
        selector: 'button',
      }),
    );
    expect(dialog()?.textContent).toContain('La agenda vuelve a recibir turnos en ese horario.');
    fireEvent.click(await findDialogButton('Eliminar el bloqueo'));

    await screen.findByText('Bloqueo eliminado correctamente.');
    expect(deleted).toEqual(['31']);
    await waitFor(() => {
      expect(urls).toHaveLength(2);
    });
  });

  it('404 (otra pestaña ya lo eliminó): el aviso, el diálogo cerrado y la lista pedida de nuevo', async () => {
    const urls: URL[] = [];
    await openDetail(
      [
        http.delete('/api/schedule-blocks/:id', () =>
          failure(404, 'RESOURCE_NOT_FOUND', 'El recurso solicitado no existe.'),
        ),
      ],
      urls,
    );

    fireEvent.click(
      screen.getByLabelText('Eliminar el bloqueo 20/11/2026, de 09:00 a 10:00', {
        selector: 'button',
      }),
    );
    fireEvent.click(await findDialogButton('Eliminar el bloqueo'));

    await screen.findByText('El recurso solicitado no existe.');
    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    await waitFor(() => {
      expect(urls).toHaveLength(2);
    });
  });
});
