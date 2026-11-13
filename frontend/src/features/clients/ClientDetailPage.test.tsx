import { fireEvent, screen } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { COMPANY, detail, pagination, patient, PERSON } from '../../test/clients';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { ClientDetail } from './types';

function detailHandler(client: ClientDetail) {
  const calls: string[] = [];
  const handler = http.get('/api/clients/:id', ({ params }) => {
    calls.push(String(params.id));

    return success(client);
  });

  return { handler, calls };
}

/** Los datos de la tarjeta como pares etiqueta → valor. */
function dataRows(): Record<string, string> {
  const terms = Array.from(document.querySelectorAll('.client-data dt'));

  return Object.fromEntries(
    terms.map((term) => [term.textContent, term.nextElementSibling?.textContent ?? '']),
  );
}

const patientNames = () =>
  Array.from(document.querySelectorAll('.patient-name')).map((node) => node.textContent);

const toggle = () => document.querySelector<HTMLButtonElement>('button.toggle-deceased');

function requiredToggle(): HTMLButtonElement {
  const button = toggle();

  if (button === null) {
    throw new Error('Falta el botón de los fallecidos.');
  }

  return button;
}

describe('/clientes/$id: detalle', () => {
  it('una persona: sus datos, con las fechas en la hora de Asunción', async () => {
    const api = detailHandler(detail(PERSON));
    server.use(meHandler(meFor('recepcionista')), api.handler);
    renderApp('/clientes/1', { session: sessionFor() });

    expect(await screen.findByText('Laura Martínez', { selector: 'h2' })).toBeInTheDocument();
    expect(dataRows()).toEqual({
      Tipo: 'Persona',
      Documento: '99100001',
      Nombre: 'Laura',
      Apellido: 'Martínez',
      Teléfono: '+595981100001',
      Email: 'laura.martinez@example.com',
      Dirección: 'Calle Falsa 123',
      Ciudad: 'Asunción',
      Notificaciones: 'Activadas',
      Notas: '—',
      // 13:30 y 18:05 UTC son las 10:30 y las 15:05 de Asunción (UTC−3).
      Alta: '10/11/2026 10:30',
      'Última modificación': '12/11/2026 15:05',
    });
    expect(api.calls).toEqual(['1']);
  });

  it('una empresa: el RUC y la razón social, sin nombre ni apellido', async () => {
    server.use(meHandler(meFor('veterinario')), detailHandler(detail(COMPANY)).handler);
    renderApp('/clientes/3', { session: sessionFor() });

    await screen.findByText('Granja San Jorge S.R.L.', { selector: 'h2' });
    const rows = dataRows();
    expect(rows).toMatchObject({
      Tipo: 'Empresa',
      RUC: '99999903-6',
      'Razón social': 'Granja San Jorge S.R.L.',
      Email: '—',
      Notificaciones: 'Desactivadas',
      Notas: 'Cliente mayorista, con crédito a 30 días.',
    });
    expect(rows).not.toHaveProperty('Nombre');
    expect(rows).not.toHaveProperty('Documento');
  });

  it('los pacientes, con especie, raza, sexo y edad; los fallecidos ocultos detrás del botón', async () => {
    const client = detail(PERSON, [
      patient(),
      patient({ id: 2, code: 'P-000002', name: 'Mishi', is_deceased: true, age: null }),
      patient({ id: 3, code: 'P-000003', name: 'Rocky', is_deceased: true }),
    ]);
    server.use(meHandler(meFor('administrador')), detailHandler(client).handler);
    renderApp('/clientes/1', { session: sessionFor() });

    await screen.findByText('Laura Martínez', { selector: 'h2' });
    expect(patientNames()).toEqual(['Firulais']);
    expect(document.querySelector('.patient-details')?.textContent).toBe(
      'Canino · Mestizo · Macho · 4 años',
    );
    expect(toggle()?.textContent).toBe('Mostrar los fallecidos (2)');
    expect(toggle()).toHaveAttribute('aria-pressed', 'false');

    fireEvent.click(requiredToggle());
    expect(patientNames()).toEqual(['Firulais', 'Mishi', 'Rocky']);
    expect(document.querySelectorAll('.patient-deceased')).toHaveLength(2);
    expect(toggle()?.textContent).toBe('Ocultar los fallecidos');
    expect(toggle()).toHaveAttribute('aria-pressed', 'true');

    fireEvent.click(requiredToggle());
    expect(patientNames()).toEqual(['Firulais']);
  });

  it('sin fallecidos, no hay botón', async () => {
    server.use(
      meHandler(meFor('administrador')),
      detailHandler(detail(PERSON, [patient()])).handler,
    );
    renderApp('/clientes/1', { session: sessionFor() });

    await screen.findByText('Firulais', { selector: '.patient-name' });
    expect(toggle()).toBeNull();
  });

  it('sin pacientes: "Este cliente todavía no tiene pacientes."', async () => {
    server.use(meHandler(meFor('administrador')), detailHandler(detail(PERSON)).handler);
    renderApp('/clientes/1', { session: sessionFor() });

    expect(await screen.findByText('Este cliente todavía no tiene pacientes.')).toBeInTheDocument();
  });

  it('con todos fallecidos: el aviso y el botón para verlos', async () => {
    server.use(
      meHandler(meFor('administrador')),
      detailHandler(detail(PERSON, [patient({ is_deceased: true })])).handler,
    );
    renderApp('/clientes/1', { session: sessionFor() });

    expect(
      await screen.findByText('Todos sus pacientes figuran como fallecidos.'),
    ).toBeInTheDocument();
    expect(toggle()?.textContent).toBe('Mostrar los fallecidos (1)');
  });

  it('un 404 de la API (inexistente o de otra clínica) muestra la página de no encontrado, dentro del layout', async () => {
    server.use(
      meHandler(meFor('administrador')),
      http.get('/api/clients/:id', () =>
        failure(404, 'RESOURCE_NOT_FOUND', 'El recurso solicitado no existe.'),
      ),
    );
    renderApp('/clientes/999', { session: sessionFor() });

    expect(await screen.findByText('No encontramos este cliente')).toBeInTheDocument();
    expect(screen.getByText('Ana Gómez', { selector: '.session-user' })).toBeInTheDocument();
    expect(screen.getByText('Volver a los clientes', { selector: 'a' })).toHaveAttribute(
      'href',
      '/clientes',
    );
  });

  it.each(['abc', '0', '-1', '01', '1.5'])(
    'un id que no es un entero positivo (%s) es no encontrado, sin llamar a la API',
    async (id) => {
      const api = detailHandler(detail(PERSON));
      server.use(meHandler(meFor('administrador')), api.handler);
      renderApp(`/clientes/${id}`, { session: sessionFor() });

      expect(await screen.findByText('No encontramos este cliente')).toBeInTheDocument();
      expect(api.calls).toEqual([]);
    },
  );

  it('un 403 FORBIDDEN de la API muestra la página de sin permiso con su mensaje', async () => {
    server.use(
      meHandler(meFor('administrador')),
      http.get('/api/clients/:id', () =>
        failure(403, 'FORBIDDEN', 'No tenés permisos para realizar esta acción.'),
      ),
    );
    renderApp('/clientes/1', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(screen.getByText('No tenés permisos para realizar esta acción.')).toBeInTheDocument();
  });

  it('un 5xx (ya reintentado una vez) muestra la página de error; "Reintentar" lo vuelve a pedir', async () => {
    let attempts = 0;
    server.use(
      meHandler(meFor('administrador')),
      http.get('/api/clients/:id', () => {
        attempts++;

        return attempts <= 2
          ? failure(500, 'INTERNAL_SERVER_ERROR', 'Error interno.')
          : success(detail(PERSON));
      }),
    );
    renderApp('/clientes/1', { session: sessionFor() });

    expect(await screen.findByText('No se pudo cargar esta página')).toBeInTheDocument();
    expect(attempts).toBe(2);

    fireEvent.click(screen.getByText('Reintentar', { selector: 'button' }));
    expect(await screen.findByText('Laura Martínez', { selector: 'h2' })).toBeInTheDocument();
  });

  it('sin clients.show, /clientes/1 va a /sin-permiso sin pedir el cliente', async () => {
    const me = meFor('administrador');
    const api = detailHandler(detail(PERSON));
    server.use(
      meHandler({
        ...me,
        permissions: me.permissions.filter((permission) => permission !== 'clients.show'),
      }),
      api.handler,
    );
    const { history } = renderApp('/clientes/1', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/sin-permiso');
    expect(api.calls).toEqual([]);
  });

  it('desde el listado: el enlace abre el detalle y "Atrás" vuelve a la misma búsqueda', async () => {
    server.use(
      meHandler(meFor('administrador')),
      http.get('/api/clients', () =>
        success([PERSON], {
          meta: { pagination: { total: 1, per_page: 20, current_page: 1, last_page: 1 } },
        }),
      ),
      detailHandler(detail(PERSON, [patient()])).handler,
    );
    const { history } = renderApp('/clientes?buscar=Firulais', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Laura Martínez', { selector: 'a' }));
    expect(await screen.findByText('Laura Martínez', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/clientes/1');

    history.back();
    expect(await screen.findByText('Clientes', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.search).toBe('?buscar=Firulais');
    expect(screen.getByLabelText<HTMLInputElement>('Buscar clientes').value).toBe('Firulais');
  });
});

const IN_USE = 'El cliente tiene pacientes, turnos, consultas o facturas y no puede eliminarse.';

/** DELETE clients/{id}: cuenta los pedidos y responde lo que diga `respond`. */
function deleteHandler(
  respond: () => Response = () => success(null, { message: 'Cliente eliminado correctamente.' }),
) {
  const calls: string[] = [];
  const handler = http.delete('/api/clients/:id', ({ params }) => {
    calls.push(String(params.id));

    return respond();
  });

  return { handler, calls };
}

const dialog = () => document.querySelector('[role="dialog"]');
const button = (text: string) => screen.getByText(text, { selector: 'button' });

describe('/clientes/$id: editar y eliminar', () => {
  it.each([
    ['administrador', true],
    ['recepcionista', false],
    ['veterinario', false],
  ] as const)(
    '%s: "Editar" siempre; "Eliminar" solo con clients.destroy (%s)',
    async (role, canDelete) => {
      server.use(meHandler(meFor(role)), detailHandler(detail(PERSON)).handler);
      renderApp('/clientes/1', { session: sessionFor() });

      await screen.findByText('Laura Martínez', { selector: 'h2' });
      expect(document.querySelector('a.client-edit')).toHaveAttribute('href', '/clientes/1/editar');
      expect(document.querySelector('button.client-delete') !== null).toBe(canDelete);
    },
  );

  it('"Cancelar" en la confirmación no llama a la API', async () => {
    const api = deleteHandler();
    server.use(
      meHandler(meFor('administrador')),
      detailHandler(detail(PERSON)).handler,
      api.handler,
    );
    renderApp('/clientes/1', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Eliminar', { selector: 'button' }));
    expect(dialog()).toHaveTextContent('¿Querés eliminar a Laura Martínez? No se puede deshacer.');
    fireEvent.click(button('Cancelar'));

    expect(dialog()).toBeNull();
    expect(api.calls).toEqual([]);
  });

  it('con la confirmación, DELETE y al listado de donde se vino; la página fuera de rango sigue la regla de la 1.1', async () => {
    const api = deleteHandler();
    let deleted = false;
    server.use(
      meHandler(meFor('administrador')),
      detailHandler(detail(PERSON)).handler,
      api.handler,
      // La página 2 tenía solo a Laura: después de la baja, queda una sola página.
      http.get('/api/clients', ({ request }) => {
        const page = Number(new URL(request.url).searchParams.get('page'));

        return deleted
          ? success([], { meta: pagination({ total: 20, current_page: page, last_page: 1 }) })
          : success([PERSON], {
              meta: pagination({ total: 21, current_page: page, last_page: 2 }),
            });
      }),
    );
    const { history } = renderApp('/clientes?pagina=2', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Laura Martínez', { selector: 'a' }));
    fireEvent.click(await screen.findByText('Eliminar', { selector: 'button' }));
    deleted = true;
    fireEvent.click(button('Eliminar a Laura Martínez'));

    expect(await screen.findByText('Esta página no existe.')).toBeInTheDocument();
    expect(api.calls).toEqual(['1']);
    expect(history.location.pathname).toBe('/clientes');
    expect(history.location.search).toBe('?pagina=2');
    expect(screen.getByText('Cliente eliminado correctamente.')).toBeInTheDocument();
    expect(screen.queryByText('Laura Martínez', { selector: 'a' })).toBeNull();
  });

  it('CLIENT_IN_USE: el mensaje de la API dentro del diálogo y solo "Cerrar", con el foco', async () => {
    const api = deleteHandler(() => failure(409, 'CLIENT_IN_USE', IN_USE));
    server.use(
      meHandler(meFor('administrador')),
      detailHandler(detail(PERSON, [patient()])).handler,
      api.handler,
    );
    const { history } = renderApp('/clientes/1', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Eliminar', { selector: 'button' }));
    fireEvent.click(button('Eliminar a Laura Martínez'));

    expect(await screen.findByText(IN_USE)).toBeInTheDocument();
    expect(dialog()).toContainElement(screen.getByText(IN_USE));
    expect(screen.queryByText('Eliminar a Laura Martínez', { selector: 'button' })).toBeNull();
    expect(document.activeElement).toBe(button('Cerrar'));
    expect(history.location.pathname).toBe('/clientes/1');

    fireEvent.click(button('Cerrar'));
    expect(dialog()).toBeNull();
  });

  it('un 403 FORBIDDEN: el mensaje dentro del diálogo, que sigue con la acción', async () => {
    const api = deleteHandler(() =>
      failure(403, 'FORBIDDEN', 'No tenés permiso para eliminar clientes.'),
    );
    server.use(
      meHandler(meFor('administrador')),
      detailHandler(detail(PERSON)).handler,
      api.handler,
    );
    renderApp('/clientes/1', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Eliminar', { selector: 'button' }));
    fireEvent.click(button('Eliminar a Laura Martínez'));

    expect(await screen.findByText('No tenés permiso para eliminar clientes.')).toBeInTheDocument();
    expect(button('Eliminar a Laura Martínez')).toBeInTheDocument();
  });

  it('un 404 (otra persona ya lo borró): aviso y al listado', async () => {
    const api = deleteHandler(() => failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'));
    server.use(
      meHandler(meFor('administrador')),
      detailHandler(detail(PERSON)).handler,
      api.handler,
      http.get('/api/clients', () => success([], { meta: pagination({ total: 0 }) })),
    );
    const { history } = renderApp('/clientes/1', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Eliminar', { selector: 'button' }));
    fireEvent.click(button('Eliminar a Laura Martínez'));

    expect(await screen.findByText('El recurso no existe.')).toBeInTheDocument();
    expect(await screen.findByText('Clientes', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/clientes');
  });
});
