import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { delay, http } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { failure, success } from '../../test/api';
import { COMPANY, detail, pagination, PERSON } from '../../test/clients';
import { patientDetail } from '../../test/patients';
import { meFor, ROLES, type Role } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { Client } from '../clients/types';
import { DECEASED_MESSAGES } from './deceased';
import type { PatientDetail } from './types';

afterEach(() => {
  vi.useRealTimers();
});

// La suite corre en UTC: 2026-11-14T01:30Z son las 22:30 del 13 en Asunción (UTC-3).
const NOW = new Date('2026-11-14T01:30:00Z');

const DECEASED = patientDetail({
  is_deceased: true,
  deceased_at: '2026-11-12T13:30:00.000000Z',
});

const OTHER: Client = {
  ...PERSON,
  id: 2,
  display_name: 'Carlos Gómez',
  document_number: '99100002',
};

interface Calls {
  bodies: unknown[];
  methods: string[];
}

/** El detalle del paciente, con los pedidos de las acciones registrados. */
async function openDetail(data: PatientDetail = patientDetail(), role: Role = 'administrador') {
  const calls: Calls = { bodies: [], methods: [] };
  server.use(
    meHandler(meFor(role)),
    http.get('/api/patients/:id', () => success(data)),
  );
  const rendered = renderApp(`/pacientes/${String(data.id)}`, { session: sessionFor() });
  await screen.findByText(data.name, { selector: 'h2', exact: false });

  return { ...rendered, calls };
}

/** PATCH patients/:id: registra el cuerpo y responde con el paciente actualizado (o lo que devuelva `respond`). */
function patchHandler(
  calls: Calls,
  respond: (body: { deceased_at: string | null }) => Response | Promise<Response> = (body) =>
    success(
      patientDetail({
        is_deceased: body.deceased_at !== null,
        deceased_at: body.deceased_at === null ? null : '2026-11-12T13:30:00.000000Z',
      }),
      { message: 'Paciente actualizado correctamente.' },
    ),
) {
  return http.patch('/api/patients/:id', async ({ request }) => {
    const body = (await request.json()) as { deceased_at: string | null };
    calls.methods.push('PATCH');
    calls.bodies.push(body);

    return respond(body);
  });
}

/** El elemento, o el test falla aquí (sin `as` ni `!`, que ESLint no admite). */
function required<T>(value: T | null | undefined, what: string): T {
  if (value === null || value === undefined) {
    throw new Error(`No está: ${what}`);
  }

  return value;
}

const dialog = () => document.querySelector<HTMLElement>('.confirm-dialog');
const button = (text: string) => screen.getByText(text, { selector: 'button' });
const input = (name: string) =>
  document.querySelector<HTMLInputElement>(`.confirm-dialog input[name="${name}"]`);
const errorOf = (element: HTMLElement | null) =>
  element === null ? null : (document.getElementById(`${element.id}-error`)?.textContent ?? null);
const dialogButton = (text: string) =>
  Array.from(dialog()?.querySelectorAll('button') ?? []).find((node) => node.textContent === text);

function setDeceased(date: string, time: string) {
  fireEvent.change(required(input('deceased_date'), 'la fecha'), { target: { value: date } });
  fireEvent.change(required(input('deceased_time'), 'la hora'), { target: { value: time } });
}

describe('/pacientes/$id: los botones de cada rol', () => {
  it.each([
    ['administrador', true, true],
    ['recepcionista', true, false],
    ['veterinario', false, false],
  ] as const)(
    '%s: "Registrar el fallecimiento" sí; "Cambiar de dueño" %s; "Eliminar" %s',
    async (role, owner, destroy) => {
      await openDetail(patientDetail(), role);

      expect(button('Registrar el fallecimiento')).toBeInTheDocument();
      expect(screen.queryByText('Cambiar de dueño', { selector: 'button' }) !== null).toBe(owner);
      expect(screen.queryByText('Eliminar', { selector: 'button' }) !== null).toBe(destroy);
    },
  );

  it.each(ROLES)(
    '%s: un fallecido muestra "Falleció el …" con la hora de Asunción, sin "Registrar"',
    async (role) => {
      await openDetail(DECEASED, role);

      expect(document.querySelector('.patient-deceased-at')).toHaveTextContent(
        'Falleció el 12/11/2026 10:30',
      );
      expect(screen.queryByText('Registrar el fallecimiento')).not.toBeInTheDocument();
      expect(button('Anular el fallecimiento')).toBeInTheDocument();
      expect(button('Corregir la fecha')).toBeInTheDocument();
    },
  );

  it('sin patients.update: ni registrar, ni corregir, ni anular', async () => {
    const permissions = meFor('veterinario').permissions.filter(
      (permission) => permission !== 'patients.update',
    );
    server.use(
      meHandler(meFor('veterinario', { permissions })),
      http.get('/api/patients/:id', () => success(DECEASED)),
    );
    renderApp('/pacientes/1', { session: sessionFor() });
    await screen.findByText('Falleció el', { exact: false });

    expect(screen.queryByText('Anular el fallecimiento')).not.toBeInTheDocument();
    expect(screen.queryByText('Corregir la fecha')).not.toBeInTheDocument();
  });
});

describe('Registrar el fallecimiento', () => {
  it('por defecto, ahora en Asunción (con la PC en UTC)', async () => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(NOW);
    await openDetail();

    fireEvent.click(button('Registrar el fallecimiento'));

    expect(input('deceased_date')?.value).toBe('2026-11-13');
    expect(input('deceased_time')?.value).toBe('22:30');
    expect(input('deceased_date')?.max).toBe('2026-11-13');
  });

  it('manda solo deceased_at con la hora de pared elegida y -03:00; el detalle muestra la misma hora', async () => {
    const { calls } = await openDetail();
    server.use(patchHandler(calls));

    fireEvent.click(button('Registrar el fallecimiento'));
    setDeceased('2026-11-12', '10:30');
    fireEvent.click(dialogButton('Registrar el fallecimiento') as HTMLElement);

    expect(await screen.findByText('Falleció el', { exact: false })).toHaveTextContent(
      'Falleció el 12/11/2026 10:30',
    );
    expect(calls.bodies).toEqual([{ deceased_at: '2026-11-12T10:30:00.000-03:00' }]);
    expect(dialog()).toBeNull();
    expect(screen.getByText('Fallecido', { selector: '.patient-deceased' })).toBeInTheDocument();
  });

  it.each([
    [
      'futura (mañana en Asunción, aunque ya sea hoy en UTC)',
      '2026-11-14',
      '00:00',
      DECEASED_MESSAGES.future,
    ],
    ['anterior al nacimiento', '2022-03-09', '23:59', DECEASED_MESSAGES.beforeBirth],
    ['a medio escribir (el campo da "")', '', '10:00', DECEASED_MESSAGES.date],
  ])('una fecha %s: el error en el campo, sin mandar', async (_case, date, time, message) => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(NOW);
    const { calls } = await openDetail();
    server.use(patchHandler(calls));

    fireEvent.click(button('Registrar el fallecimiento'));
    setDeceased(date, time);
    fireEvent.click(dialogButton('Registrar el fallecimiento') as HTMLElement);

    expect(errorOf(input('deceased_date'))).toBe(message);
    expect(document.activeElement).toBe(input('deceased_date'));
    expect(calls.methods).toEqual([]);
  });

  it('el mismo día del nacimiento se manda', async () => {
    const { calls } = await openDetail(patientDetail({ birth_date: '2026-11-10' }));
    server.use(patchHandler(calls));

    fireEvent.click(button('Registrar el fallecimiento'));
    setDeceased('2026-11-10', '00:00');
    fireEvent.click(dialogButton('Registrar el fallecimiento') as HTMLElement);

    await waitFor(() => {
      expect(calls.bodies).toEqual([{ deceased_at: '2026-11-10T00:00:00.000-03:00' }]);
    });
  });

  it('el 422 de la API en deceased_at va al campo de la fecha', async () => {
    const { calls } = await openDetail();
    server.use(
      patchHandler(calls, () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
          errors: { deceased_at: ['La fecha de fallecimiento no puede ser futura.'] },
        }),
      ),
    );

    fireEvent.click(button('Registrar el fallecimiento'));
    setDeceased('2026-11-12', '10:30');
    fireEvent.click(dialogButton('Registrar el fallecimiento') as HTMLElement);

    await waitFor(() => {
      expect(errorOf(input('deceased_date'))).toBe(
        'La fecha de fallecimiento no puede ser futura.',
      );
    });
    expect(dialog()).not.toBeNull();
  });

  it('un doble clic manda un solo pedido', async () => {
    const { calls } = await openDetail();
    server.use(
      patchHandler(calls, async () => {
        await delay(50);

        return success(DECEASED);
      }),
    );

    fireEvent.click(button('Registrar el fallecimiento'));
    setDeceased('2026-11-12', '10:30');
    const confirm = dialogButton('Registrar el fallecimiento') as HTMLElement;
    fireEvent.click(confirm);
    fireEvent.click(confirm);

    await screen.findByText('Falleció el', { exact: false });
    expect(calls.methods).toEqual(['PATCH']);
  });

  it('dos Enter en el formulario (sin pasar por el botón) mandan un solo pedido', async () => {
    const { calls } = await openDetail();
    server.use(
      patchHandler(calls, async () => {
        await delay(50);

        return success(DECEASED);
      }),
    );

    fireEvent.click(button('Registrar el fallecimiento'));
    setDeceased('2026-11-12', '10:30');
    const form = required(document.querySelector('.deceased-form'), 'el formulario');
    fireEvent.submit(form);
    fireEvent.submit(form);

    await screen.findByText('Falleció el', { exact: false });
    expect(calls.methods).toEqual(['PATCH']);
  });

  it('corregir la fecha abre el diálogo con la guardada, en la hora de Asunción', async () => {
    await openDetail(DECEASED);

    fireEvent.click(button('Corregir la fecha'));

    expect(input('deceased_date')?.value).toBe('2026-11-12');
    expect(input('deceased_time')?.value).toBe('10:30');
  });
});

describe('Anular el fallecimiento', () => {
  it('con confirmación: manda deceased_at null y el detalle vuelve a mostrarlo activo', async () => {
    const { calls } = await openDetail(DECEASED);
    server.use(patchHandler(calls));

    fireEvent.click(button('Anular el fallecimiento'));
    expect(dialog()).toHaveTextContent('¿Querés anular el fallecimiento de Firulais?');
    fireEvent.click(dialogButton('Anular el fallecimiento') as HTMLElement);

    await waitFor(() => {
      expect(document.querySelector('.patient-deceased-at')).toBeNull();
    });
    expect(calls.bodies).toEqual([{ deceased_at: null }]);
    expect(button('Registrar el fallecimiento')).toBeInTheDocument();
  });

  it('"Cancelar" no llama a la API', async () => {
    const { calls } = await openDetail(DECEASED);
    server.use(patchHandler(calls));

    fireEvent.click(button('Anular el fallecimiento'));
    fireEvent.click(dialogButton('Cancelar') as HTMLElement);

    expect(dialog()).toBeNull();
    expect(calls.methods).toEqual([]);
  });

  it('un 403 FORBIDDEN deja el mensaje en el diálogo', async () => {
    const { calls } = await openDetail(DECEASED);
    server.use(
      patchHandler(calls, () => failure(403, 'FORBIDDEN', 'No tenés permiso para esta acción.')),
    );

    fireEvent.click(button('Anular el fallecimiento'));
    fireEvent.click(dialogButton('Anular el fallecimiento') as HTMLElement);

    expect(await screen.findByText('No tenés permiso para esta acción.')).toBeInTheDocument();
    expect(dialog()).not.toBeNull();
  });
});

describe('Cambiar de dueño', () => {
  /** GET clients: Laura (el dueño actual), Carlos y la empresa; registra la búsqueda de cada pedido. */
  function clientsHandler(searches: (string | null)[]) {
    return http.get('/api/clients', ({ request }) => {
      const params = new URL(request.url).searchParams;
      searches.push(params.get('search'));
      expect(params.get('per_page')).toBe('10');

      return success([PERSON, OTHER, COMPANY], { meta: pagination({ total: 3, per_page: 10 }) });
    });
  }

  const ownerInput = () =>
    required(document.querySelector<HTMLInputElement>('.owner-search input'), 'el buscador');
  const optionNames = () =>
    Array.from(document.querySelectorAll('.owner-options label .d-block')).map(
      (node) => node.textContent,
    );

  it('la búsqueda espera 300 ms y manda un solo pedido; el dueño actual no se ofrece', async () => {
    await openDetail(patientDetail(), 'recepcionista');
    const searches: (string | null)[] = [];
    server.use(clientsHandler(searches));
    fireEvent.click(button('Cambiar de dueño'));

    vi.useFakeTimers();
    fireEvent.change(ownerInput(), { target: { value: 'G' } });
    fireEvent.change(ownerInput(), { target: { value: 'Gó ' } });
    act(() => {
      vi.advanceTimersByTime(299);
    });
    expect(searches).toEqual([]);
    act(() => {
      vi.advanceTimersByTime(1);
    });
    // El pedido sale con el reloj falso: se deja correr lo que MSW programó antes de volver a los relojes reales.
    await act(async () => {
      await vi.advanceTimersByTimeAsync(100);
    });
    vi.useRealTimers();

    await waitFor(() => {
      expect(optionNames()).toEqual(['Carlos Gómez', 'Granja San Jorge S.R.L.']);
    });
    expect(searches).toEqual(['Gó']);
  });

  it('sin elegir, el botón no hace nada; al elegir, manda client_id y el detalle muestra el dueño nuevo; invalida los clientes', async () => {
    const { queryClient } = await openDetail(patientDetail(), 'recepcionista');
    const searches: (string | null)[] = [];
    const bodies: unknown[] = [];
    queryClient.setQueryData(['clients', 'detail', 1], detail(PERSON));
    queryClient.setQueryData(['clients', 'detail', 2], detail(OTHER));
    server.use(
      clientsHandler(searches),
      http.post('/api/patients/:id/change-owner', async ({ request }) => {
        bodies.push(await request.json());

        return success(
          patientDetail({
            client: {
              id: 2,
              display_name: 'Carlos Gómez',
              phone: null,
              email: null,
              deleted: false,
            },
          }),
          { message: 'Dueño del paciente actualizado correctamente.' },
        );
      }),
    );

    fireEvent.click(button('Cambiar de dueño'));
    fireEvent.click(dialogButton('Cambiar de dueño') as HTMLElement);
    expect(bodies).toEqual([]);

    fireEvent.change(ownerInput(), { target: { value: 'Carlos' } });
    fireEvent.click(
      await screen.findByText('Carlos Gómez', { selector: '.owner-options .d-block' }),
    );
    fireEvent.click(dialogButton('Pasar a Carlos Gómez') as HTMLElement);

    expect(
      await screen.findByText('Carlos Gómez', { selector: '.patient-owner-link' }),
    ).toBeInTheDocument();
    expect(bodies).toEqual([{ client_id: 2 }]);
    expect(dialog()).toBeNull();
    expect(queryClient.getQueryState(['clients', 'detail', 1])?.isInvalidated).toBe(true);
    expect(queryClient.getQueryState(['clients', 'detail', 2])?.isInvalidated).toBe(true);
  });

  it.each([
    [
      'el 422 en client_id (el cliente se eliminó mientras tanto)',
      () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
          errors: { client_id: ['El cliente seleccionado no existe.'] },
        }),
      'El cliente seleccionado no existe.',
    ],
    [
      'PATIENT_SAME_OWNER',
      () => failure(422, 'PATIENT_SAME_OWNER', 'El paciente ya pertenece a ese cliente.'),
      'El paciente ya pertenece a ese cliente.',
    ],
  ])('%s: el mensaje en el diálogo', async (_case, respond, message) => {
    await openDetail(patientDetail(), 'administrador');
    server.use(
      clientsHandler([]),
      http.post('/api/patients/:id/change-owner', () => respond()),
    );

    fireEvent.click(button('Cambiar de dueño'));
    fireEvent.change(ownerInput(), { target: { value: 'Carlos' } });
    fireEvent.click(
      await screen.findByText('Carlos Gómez', { selector: '.owner-options .d-block' }),
    );
    fireEvent.click(dialogButton('Pasar a Carlos Gómez') as HTMLElement);

    expect(await screen.findByText(message)).toBeInTheDocument();
    expect(dialog()).not.toBeNull();
  });
});

describe('Eliminar el paciente', () => {
  function deleteHandler(methods: string[], respond: () => Response) {
    return http.delete('/api/patients/:id', () => {
      methods.push('DELETE');

      return respond();
    });
  }

  it('"Cancelar" no llama a la API', async () => {
    const methods: string[] = [];
    await openDetail();
    server.use(deleteHandler(methods, () => success(null)));

    fireEvent.click(button('Eliminar'));
    expect(dialog()).toHaveTextContent('¿Querés eliminar a Firulais (P-000001)?');
    fireEvent.click(dialogButton('Cancelar') as HTMLElement);

    expect(methods).toEqual([]);
  });

  it('con el 200, al detalle del dueño', async () => {
    const methods: string[] = [];
    const { history } = await openDetail();
    server.use(
      deleteHandler(methods, () => success(null, { message: 'Paciente eliminado correctamente.' })),
      http.get('/api/clients/:id', () => success(detail(PERSON))),
    );

    fireEvent.click(button('Eliminar'));
    fireEvent.click(dialogButton('Eliminar a Firulais') as HTMLElement);

    await waitFor(() => {
      expect(history.location.pathname).toBe('/clientes/1');
    });
    expect(methods).toEqual(['DELETE']);
    expect(await screen.findByText('Paciente eliminado correctamente.')).toBeInTheDocument();
  });

  it('PATIENT_IN_USE: el mensaje y "Registrar el fallecimiento", que abre el otro diálogo', async () => {
    const message =
      'El paciente tiene consultas, aplicaciones o turnos y no puede eliminarse. Si falleció, registrá la fecha de fallecimiento.';
    await openDetail();
    server.use(deleteHandler([], () => failure(409, 'PATIENT_IN_USE', message)));

    fireEvent.click(button('Eliminar'));
    fireEvent.click(dialogButton('Eliminar a Firulais') as HTMLElement);

    expect(await screen.findByText(message)).toBeInTheDocument();
    fireEvent.click(dialogButton('Registrar el fallecimiento') as HTMLElement);

    expect(input('deceased_date')).not.toBeNull();
    expect(screen.queryByText(message)).not.toBeInTheDocument();
  });

  it('PATIENT_IN_USE de un fallecido: el mensaje y solo "Cerrar"', async () => {
    await openDetail(DECEASED);
    server.use(
      deleteHandler([], () => failure(409, 'PATIENT_IN_USE', 'El paciente tiene consultas.')),
    );

    fireEvent.click(button('Eliminar'));
    fireEvent.click(dialogButton('Eliminar a Firulais') as HTMLElement);

    expect(await screen.findByText('El paciente tiene consultas.')).toBeInTheDocument();
    expect(
      Array.from(dialog()?.querySelectorAll('button') ?? []).map((b) => b.textContent),
    ).toEqual(['Cerrar']);
  });

  it('un 404: aviso y al listado de pacientes', async () => {
    const { history } = await openDetail();
    server.use(
      deleteHandler([], () => failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.')),
      http.get('/api/patients', () => success([], { meta: pagination({ total: 0 }) })),
    );

    fireEvent.click(button('Eliminar'));
    fireEvent.click(dialogButton('Eliminar a Firulais') as HTMLElement);

    await waitFor(() => {
      expect(history.location.pathname).toBe('/pacientes');
    });
    expect(await screen.findByText('El recurso no existe.')).toBeInTheDocument();
  });
});
