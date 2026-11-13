import { fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { detail, pagination, PERSON, wrongRuc } from '../../test/clients';
import { meFor, ROLE_PERMISSIONS, ROLES } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { CLIENT_MESSAGES } from './form';
import { RUC_MESSAGE } from './ruc';
import type { ClientDetail, ClientPayload, SavedClient } from './types';

// Por etiqueta o por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
function field(label: string): HTMLInputElement {
  return screen.getByLabelText(label, { selector: 'input, textarea' });
}

function type(label: string, value: string) {
  fireEvent.change(field(label), { target: { value } });
}

const submit = () => {
  fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));
};

const fieldError = (label: string) =>
  document.getElementById(`${field(label).id}-error`)?.textContent ?? null;

/** El cliente que devuelve la API después de guardar, a partir del cuerpo mandado. */
function saved(id: number, body: ClientPayload, patients = PERSON_DETAIL.patients): ClientDetail {
  const displayName =
    body.kind === 'company'
      ? (body.business_name ?? '')
      : `${body.first_name ?? ''} ${body.last_name ?? ''}`;

  return {
    ...body,
    id,
    kind: { value: body.kind, label: body.kind === 'company' ? 'Empresa' : 'Persona' },
    display_name: displayName,
    patients,
    created_at: '2026-11-13T12:00:00.000000Z',
    updated_at: '2026-11-13T12:00:00.000000Z',
  };
}

const PERSON_DETAIL = detail(PERSON);

/** POST clients: guarda los cuerpos y responde lo que diga `respond` (por defecto, el 201 sin pacientes, como la API). */
function storeHandler(
  respond: (body: ClientPayload) => Response = (body) => {
    // El 201 no trae los pacientes (ShowClientResource con whenLoaded).
    const client: SavedClient = saved(7, body, []);
    delete client.patients;

    return success(client, { status: 201, message: 'Cliente creado correctamente.' });
  },
) {
  const bodies: ClientPayload[] = [];
  const handler = http.post('/api/clients', async ({ request }) => {
    const body = (await request.json()) as ClientPayload;
    bodies.push(body);

    return respond(body);
  });

  return { handler, bodies };
}

/** GET clients/{id} de lo que se fue guardando (el detalle, después de guardar). */
function detailHandler(client: () => ClientDetail) {
  return http.get('/api/clients/:id', () => success(client()));
}

function fillPerson() {
  type('Nombre', 'Juan');
  type('Apellido', 'Pérez');
  type('Documento', '99400001');
  type('Email', '  Juan.Perez@Example.COM ');
  type('Teléfono', '+595 971 222-333');
}

async function openCreate(role: Parameters<typeof meFor>[0] = 'recepcionista') {
  const store = storeHandler();
  // El detalle después del alta: lo último guardado, sin pacientes (como la API).
  server.use(
    meHandler(meFor(role)),
    store.handler,
    detailHandler(() => {
      const last = store.bodies.at(-1);

      return last === undefined ? PERSON_DETAIL : saved(7, last, []);
    }),
  );
  const app = renderApp('/clientes/nuevo', { session: sessionFor() });
  await screen.findByText('Nuevo cliente', { selector: 'h2' });

  return { ...app, store };
}

describe('/clientes/nuevo: alta', () => {
  it('una persona: el cuerpo con la razón social en null, el email en minúsculas y el teléfono limpio; al detalle', async () => {
    const { store, history } = await openCreate();

    expect(field('Persona')).toBeChecked();
    expect(field('Recibe recordatorios')).toBeChecked();
    fillPerson();
    type('Notas', ' Prefiere WhatsApp. ');
    submit();

    expect(await screen.findByText('Juan Pérez', { selector: 'h2' })).toBeInTheDocument();
    expect(store.bodies).toEqual([
      {
        kind: 'person',
        first_name: 'Juan',
        last_name: 'Pérez',
        business_name: null,
        document_number: '99400001',
        email: 'juan.perez@example.com',
        phone: '+595971222333',
        address: null,
        city: null,
        notifications_enabled: true,
        notes: 'Prefiere WhatsApp.',
      },
    ]);
    expect(history.location.pathname).toBe('/clientes/7');
    expect(screen.getByText('Cliente creado correctamente.')).toBeInTheDocument();
    // El detalle sale de la respuesta del alta (sin pacientes: el 201 no los trae).
    expect(screen.getByText('Este cliente todavía no tiene pacientes.')).toBeInTheDocument();
    // Guardar reemplaza al formulario: "Atrás" no vuelve a él.
    expect(history.length).toBe(1);
  });

  it('persona → empresa: los campos de persona se ocultan y van en null; el RUC con su dígito', async () => {
    const { store } = await openCreate();

    fillPerson();
    fireEvent.click(field('Empresa'));

    expect(screen.queryByLabelText('Nombre')).toBeNull();
    expect(screen.queryByLabelText('Apellido')).toBeNull();
    type('Razón social', 'Haras del Sur SA');
    type('RUC', '99999904-4');
    fireEvent.click(field('Recibe recordatorios'));
    submit();

    expect(await screen.findByText('Haras del Sur SA', { selector: 'h2' })).toBeInTheDocument();
    expect(store.bodies[0]).toMatchObject({
      kind: 'company',
      business_name: 'Haras del Sur SA',
      first_name: null,
      last_name: null,
      document_number: '99999904-4',
      notifications_enabled: false,
    });
  });

  it('empresa → persona: lo escrito en nombre y apellido vuelve y la razón social va en null', async () => {
    const { store } = await openCreate();

    fillPerson();
    fireEvent.click(field('Empresa'));
    type('Razón social', 'Haras del Sur SA');
    fireEvent.click(field('Persona'));

    expect(field('Nombre')).toHaveValue('Juan');
    submit();
    await screen.findByText('Juan Pérez', { selector: 'h2' });
    expect(store.bodies[0]).toMatchObject({ kind: 'person', business_name: null });
  });

  it('el RUC con el dígito verificador equivocado: el error en el campo, con el foco, sin llamar a la API', async () => {
    const { store } = await openCreate();

    fillPerson();
    type('Documento', wrongRuc('99999903'));
    submit();

    expect(await screen.findByText(RUC_MESSAGE)).toBeInTheDocument();
    expect(fieldError('Documento')).toBe(RUC_MESSAGE);
    expect(document.activeElement).toBe(field('Documento'));
    expect(store.bodies).toEqual([]);
  });

  // Revisión de la 1.2: dos clics seguidos llegaban los dos a mutate antes de que el botón se deshabilitara (dos POST).
  it('dos clics seguidos en Guardar: un solo POST', async () => {
    const { store } = await openCreate();

    fillPerson();
    submit();
    submit();

    expect(await screen.findByText('Juan Pérez', { selector: 'h2' })).toBeInTheDocument();
    expect(store.bodies).toHaveLength(1);
  });

  // El envío del formulario no pasa por el botón (Enter en un campo, requestSubmit): lo frena la ref, no el botón
  // deshabilitado.
  it('dos envíos seguidos del formulario: un solo POST', async () => {
    const { store } = await openCreate();

    fillPerson();
    const form = document.querySelector('form.client-form');

    if (form === null) {
      throw new Error('Falta el formulario.');
    }

    fireEvent.submit(form);
    fireEvent.submit(form);

    expect(await screen.findByText('Juan Pérez', { selector: 'h2' })).toBeInTheDocument();
    expect(store.bodies).toHaveLength(1);
  });

  it('Guardar se deshabilita en el mismo clic, mientras valida (antes de que salga el pedido)', async () => {
    await openCreate();

    fillPerson();
    submit();

    expect(screen.getByText('Guardar', { selector: 'button' })).toBeDisabled();
    await screen.findByText('Juan Pérez', { selector: 'h2' });
  });

  it('mientras espera la API, Guardar está deshabilitado', async () => {
    const { store } = await openCreate();
    let release: () => void = () => undefined;
    server.use(
      http.post('/api/clients', async ({ request }) => {
        store.bodies.push((await request.json()) as ClientPayload);
        await new Promise<void>((resolve) => {
          release = resolve;
        });

        return failure(403, 'FORBIDDEN', 'No tenés permiso para crear clientes.');
      }),
    );

    fillPerson();
    submit();

    await waitFor(() => {
      expect(screen.getByText('Guardar', { selector: 'button' })).toBeDisabled();
    });
    release();
    await screen.findByText('No tenés permiso para crear clientes.', { selector: '.alert-title' });
    expect(screen.getByText('Guardar', { selector: 'button' })).toBeEnabled();
    expect(store.bodies).toHaveLength(1);
  });

  it('vacío: cada obligatorio con su mensaje y el foco en el primero, sin llamar a la API', async () => {
    const { store } = await openCreate();

    submit();

    expect(await screen.findByText(CLIENT_MESSAGES.first_name.required)).toBeInTheDocument();
    expect(fieldError('Apellido')).toBe(CLIENT_MESSAGES.last_name.required);
    expect(fieldError('Documento')).toBe(CLIENT_MESSAGES.document_number.required);
    expect(fieldError('Email')).toBe(CLIENT_MESSAGES.email.required);
    expect(fieldError('Teléfono')).toBe(CLIENT_MESSAGES.phone.required);
    expect(document.activeElement).toBe(field('Nombre'));
    expect(store.bodies).toEqual([]);
  });

  it('el 422 de la API: el documento y el email en uso, cada uno en su campo, con el foco en el documento', async () => {
    const store = storeHandler(() =>
      failure(422, 'VALIDATION_ERROR', 'Revisá los datos.', {
        errors: {
          document_number: ['Este número de documento ya está en uso.'],
          email: ['Este correo ya está en uso.'],
        },
      }),
    );
    server.use(meHandler(meFor('recepcionista')), store.handler);
    const { history } = renderApp('/clientes/nuevo', { session: sessionFor() });
    await screen.findByText('Nuevo cliente', { selector: 'h2' });

    fillPerson();
    submit();

    expect(await screen.findByText('Este número de documento ya está en uso.')).toBeInTheDocument();
    expect(fieldError('Email')).toBe('Este correo ya está en uso.');
    expect(document.activeElement).toBe(field('Documento'));
    expect(history.location.pathname).toBe('/clientes/nuevo');
    expect(document.querySelector('.alert')).toBeNull();
  });

  it('un 422 con una clave que no es de un campo: a la alerta de arriba', async () => {
    const store = storeHandler(() =>
      failure(422, 'VALIDATION_ERROR', 'Revisá los datos.', {
        errors: { clinic: ['La clínica no acepta clientes nuevos.'] },
      }),
    );
    server.use(meHandler(meFor('recepcionista')), store.handler);
    renderApp('/clientes/nuevo', { session: sessionFor() });
    await screen.findByText('Nuevo cliente', { selector: 'h2' });

    fillPerson();
    submit();

    expect(
      await screen.findByText('La clínica no acepta clientes nuevos.', {
        selector: '.alert-title',
      }),
    ).toBeInTheDocument();
  });

  it('un 403 FORBIDDEN: la alerta con el mensaje de la API y el formulario como estaba', async () => {
    const store = storeHandler(() =>
      failure(403, 'FORBIDDEN', 'No tenés permiso para crear clientes.'),
    );
    server.use(meHandler(meFor('recepcionista')), store.handler);
    renderApp('/clientes/nuevo', { session: sessionFor() });
    await screen.findByText('Nuevo cliente', { selector: 'h2' });

    fillPerson();
    submit();

    expect(
      await screen.findByText('No tenés permiso para crear clientes.', {
        selector: '.alert-title',
      }),
    ).toBeInTheDocument();
    expect(field('Nombre')).toHaveValue('Juan');
  });

  it('"Cancelar" vuelve al listado de donde se vino (su búsqueda y su página), sin llamar a la API', async () => {
    const list = http.get('/api/clients', () =>
      success([PERSON], { meta: pagination({ current_page: 2, last_page: 2, total: 21 }) }),
    );
    const store = storeHandler();
    server.use(meHandler(meFor('administrador')), list, store.handler);
    const { history } = renderApp('/clientes?buscar=Laura&pagina=2', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Nuevo cliente', { selector: 'a' }));
    await screen.findByText('Nuevo cliente', { selector: 'h2' });
    fireEvent.click(screen.getByText('Cancelar', { selector: 'a' }));

    await screen.findByText('Clientes', { selector: 'h2' });
    expect(history.location.search).toBe('?buscar=Laura&pagina=2');
    expect(store.bodies).toEqual([]);
  });

  it.each(ROLES)('%s tiene clients.store: entra al alta', async (role) => {
    await openCreate(role);

    expect(field('Documento')).toBeInTheDocument();
  });

  it('sin clients.store, /clientes/nuevo va a /sin-permiso', async () => {
    // Ningún rol real deja de tenerlo: los permisos del recepcionista, sin ese (para probar la guarda de la ruta).
    const permissions = ROLE_PERMISSIONS.recepcionista.filter((name) => name !== 'clients.store');
    server.use(meHandler(meFor('recepcionista', { permissions })));
    const { history } = renderApp('/clientes/nuevo', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/sin-permiso');
  });
});

/** PATCH clients/{id}: guarda los cuerpos; por defecto, el 200 con el cliente guardado y sus pacientes. */
function updateHandler(
  respond: (body: ClientPayload) => Response = (body) =>
    success(saved(1, body), { message: 'Cliente actualizado correctamente.' }),
) {
  const bodies: ClientPayload[] = [];
  const handler = http.patch('/api/clients/:id', async ({ request }) => {
    const body = (await request.json()) as ClientPayload;
    bodies.push(body);

    return respond(body);
  });

  return { handler, bodies };
}

async function openEdit(
  update = updateHandler(),
  role: Parameters<typeof meFor>[0] = 'recepcionista',
  extra: Parameters<typeof server.use> = [],
) {
  // El detalle devuelve lo último guardado (como la API).
  server.use(
    meHandler(meFor(role)),
    http.get('/api/clients/:id', () => {
      const last = update.bodies.at(-1);

      return success(last === undefined ? PERSON_DETAIL : saved(1, last));
    }),
    update.handler,
    ...extra,
  );
  const app = renderApp('/clientes/1/editar', { session: sessionFor() });
  await screen.findByText('Editar el cliente', { selector: 'h2' });

  return { ...app, update };
}

describe('/clientes/$id/editar: edición', () => {
  it('el formulario con los datos guardados; manda el formulario completo y vuelve al detalle con lo guardado', async () => {
    const { update, history } = await openEdit();

    expect(field('Nombre')).toHaveValue('Laura');
    expect(field('Email')).toHaveValue('laura.martinez@example.com');
    expect(field('Persona')).toBeChecked();
    type('Teléfono', '+595 981 999-888');
    type('Email', 'LAURA@example.com');
    submit();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/clientes/1');
    });
    expect(update.bodies).toEqual([
      {
        kind: 'person',
        first_name: 'Laura',
        last_name: 'Martínez',
        business_name: null,
        document_number: '99100001',
        email: 'laura@example.com',
        phone: '+595981999888',
        address: 'Calle Falsa 123',
        city: 'Asunción',
        notifications_enabled: true,
        notes: null,
      },
    ]);
    expect(await screen.findByText('Cliente actualizado correctamente.')).toBeInTheDocument();
    // El detalle muestra lo guardado.
    expect(screen.getByText('laura@example.com', { selector: 'dd' })).toBeInTheDocument();
  });

  it('dos clics seguidos en Guardar: un solo PATCH', async () => {
    const { update, history } = await openEdit();

    type('Teléfono', '+595981999888');
    submit();
    submit();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/clientes/1');
    });
    expect(update.bodies).toHaveLength(1);
  });

  it('guardar invalida el detalle de los pacientes (muestra el nombre del dueño) y no otras consultas', async () => {
    const { queryClient, history } = await openEdit();
    const patientKey = ['patients', 'detail', 5];
    const otherKey = ['species', { include_inactive: false }];
    queryClient.setQueryData(patientKey, { id: 5 });
    queryClient.setQueryData(otherKey, []);

    submit();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/clientes/1');
    });
    expect(queryClient.getQueryState(patientKey)?.isInvalidated).toBe(true);
    expect(queryClient.getQueryState(otherKey)?.isInvalidated).toBe(false);
  });

  it('desde el listado: guardar vuelve al detalle (reemplazando el formulario) con la búsqueda del listado', async () => {
    const update = updateHandler();
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/clients', () =>
        success([PERSON], { meta: pagination({ current_page: 2, last_page: 2, total: 21 }) }),
      ),
      http.get('/api/clients/:id', () => success(PERSON_DETAIL)),
      update.handler,
    );
    const { history } = renderApp('/clientes?buscar=Laura&pagina=2', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Laura Martínez', { selector: 'a' }));
    fireEvent.click(await screen.findByText('Editar', { selector: 'a' }));
    await screen.findByText('Editar el cliente', { selector: 'h2' });
    submit();

    await screen.findByText('Cliente actualizado correctamente.');
    expect(history.location.pathname).toBe('/clientes/1');
    // El encabezado "Clientes" (y la baja) vuelven a la misma búsqueda y página.
    expect(document.querySelector('.page-pretitle a')).toHaveAttribute(
      'href',
      '/clientes?buscar=Laura&pagina=2',
    );
    // Listado, detalle y el detalle que reemplazó al formulario: "Atrás" no vuelve al formulario.
    expect(history.length).toBe(3);
  });

  it('CLIENT_KIND_NOT_EDITABLE: la alerta arriba y el tipo vuelve al guardado', async () => {
    const message =
      'El cliente ya tiene facturas: no se puede cambiar de persona a empresa ni al revés.';
    await openEdit(updateHandler(() => failure(422, 'CLIENT_KIND_NOT_EDITABLE', message)));

    fireEvent.click(field('Empresa'));
    type('Razón social', 'Martínez e Hijos');
    submit();

    expect(await screen.findByText(message, { selector: '.alert-title' })).toBeInTheDocument();
    expect(field('Persona')).toBeChecked();
    expect(field('Nombre')).toHaveValue('Laura');
  });

  it('un 404 al guardar (el cliente ya no está): aviso y al listado', async () => {
    const list = http.get('/api/clients', () => success([], { meta: pagination({ total: 0 }) }));
    const { history } = await openEdit(
      updateHandler(() => failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.')),
      'recepcionista',
      [list],
    );

    submit();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/clientes');
    });
    expect(await screen.findByText('El recurso no existe.')).toBeInTheDocument();
  });

  it('"Cancelar" vuelve al detalle sin llamar a la API', async () => {
    const { update, history } = await openEdit();

    type('Nombre', 'Otra');
    fireEvent.click(screen.getByText('Cancelar', { selector: 'a' }));

    await screen.findByText('Laura Martínez', { selector: 'h2' });
    expect(history.location.pathname).toBe('/clientes/1');
    expect(update.bodies).toEqual([]);
  });

  it('sin clients.update, /clientes/1/editar va a /sin-permiso', async () => {
    // Ningún rol real deja de tenerlo: los permisos del veterinario, sin ese (para probar la guarda de la ruta).
    const permissions = ROLE_PERMISSIONS.veterinario.filter((name) => name !== 'clients.update');
    server.use(meHandler(meFor('veterinario', { permissions })));
    const { history } = renderApp('/clientes/1/editar', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/sin-permiso');
  });

  it('un cliente inexistente: la página de no encontrado', async () => {
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/clients/:id', () =>
        failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'),
      ),
    );
    renderApp('/clientes/99/editar', { session: sessionFor() });

    expect(await screen.findByText('No encontramos este cliente')).toBeInTheDocument();
  });
});
