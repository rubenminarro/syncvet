import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { CENTRAL, listItem, NORTH, warehouse } from '../../test/branches';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { BRANCH_MESSAGES } from './form';
import type { BranchDetail, CreateBranchPayload } from './types';

// Por etiqueta o por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
function field(label: string, container: ParentNode = document): HTMLInputElement {
  const found = Array.from(container.querySelectorAll('label')).find(
    (element) => element.textContent.replace('*', '').trim() === label,
  );
  const input =
    found?.querySelector('input') ??
    (found?.htmlFor ? document.getElementById(found.htmlFor) : null);

  if (!(input instanceof HTMLInputElement)) {
    throw new Error(`Sin el campo ${label}`);
  }

  return input;
}

function type(label: string, value: string, container?: ParentNode) {
  fireEvent.change(field(label, container), { target: { value } });
}

const fieldError = (label: string, container?: ParentNode) =>
  document.getElementById(`${field(label, container).id}-error`)?.textContent ?? null;

const button = (label: string) =>
  screen.queryByText(label, {
    selector: '.page-header button, .page-header a, .card-actions button',
  });

/** Un botón de las acciones de la sucursal o de la tarjeta de depósitos, que tiene que estar. */
function pageButton(label: string): HTMLElement {
  const found = button(label);

  if (found === null) {
    throw new Error(`Sin el botón ${label}`);
  }

  return found;
}

const dialog = () => document.querySelector<HTMLElement>('[role="dialog"]');

/** El diálogo abierto, que tiene que estar. */
function openDialog(): HTMLElement {
  const found = dialog();

  if (found === null) {
    throw new Error('Sin diálogo');
  }

  return found;
}

const dialogButton = (label: string) =>
  Array.from(dialog()?.querySelectorAll('button') ?? []).find(
    (element) => element.textContent.trim() === label,
  ) ?? null;

const dialogButtons = () =>
  Array.from(dialog()?.querySelectorAll('button') ?? []).map((element) =>
    element.textContent.trim(),
  );

const ADMIN = meFor('administrador');

/** GET branches/{id} con lo que devuelva `current` (cambia después de cada acción). */
function detailHandler(current: () => BranchDetail) {
  return http.get('/api/branches/:id', ({ params }) =>
    String(params.id) === String(current().id)
      ? success(current())
      : failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'),
  );
}

async function openDetail(branch: BranchDetail) {
  server.use(
    meHandler(ADMIN),
    detailHandler(() => branch),
  );
  const app = renderApp(`/configuracion/sucursales/${String(branch.id)}`, {
    session: sessionFor(),
  });
  await screen.findByText('Datos de la sucursal');

  return app;
}

describe('/configuracion', () => {
  it('el administrador ve la tarjeta de Sucursales, que lleva al listado', async () => {
    server.use(
      meHandler(ADMIN),
      http.get('/api/branches', () => success([listItem(CENTRAL)], { meta: pagination() })),
    );
    const { history } = renderApp('/configuracion', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Sucursales', { selector: 'span' }));

    expect(await screen.findByText('Sucursales', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/configuracion/sucursales');
  });

  it.each(['recepcionista', 'veterinario'] as const)(
    '%s en /configuracion/sucursales va a /sin-permiso y no ve "Configuración"',
    async (role) => {
      server.use(meHandler(meFor(role)));
      const { history } = renderApp('/configuracion/sucursales', { session: sessionFor() });

      expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
      expect(history.location.pathname).toBe('/sin-permiso');
      expect(screen.queryByText('Configuración', { selector: '.nav-link-title' })).toBeNull();
    },
  );
});

describe('/configuracion/sucursales: listado', () => {
  it('la tabla, y "Incluir las inactivas" pide include_inactive=1 y lo pone en la URL', async () => {
    const urls: URL[] = [];
    server.use(
      meHandler(ADMIN),
      http.get('/api/branches', ({ request }) => {
        const url = new URL(request.url);
        urls.push(url);
        const items =
          url.searchParams.get('include_inactive') === '1'
            ? [listItem(CENTRAL), listItem({ ...NORTH, is_active: false })]
            : [listItem(CENTRAL)];

        return success(items, { meta: pagination({ total: items.length }) });
      }),
    );
    const { history } = renderApp('/configuracion/sucursales', { session: sessionFor() });

    expect(await screen.findByText('Casa central', { selector: 'a' })).toBeInTheDocument();
    expect(screen.getByText('Por defecto', { selector: '.badge' })).toBeInTheDocument();
    expect(button('Nueva sucursal')).not.toBeNull();
    expect(urls[0]?.searchParams.get('include_inactive')).toBeNull();
    expect(urls[0]?.searchParams.get('per_page')).toBe('20');

    fireEvent.click(field('Incluir las inactivas'));

    expect(await screen.findByText('Inactivada', { selector: '.badge' })).toBeInTheDocument();
    expect(history.location.search).toBe('?inactivas=1');
    expect(urls.at(-1)?.searchParams.get('include_inactive')).toBe('1');
  });
});

/** POST branches: guarda los cuerpos y responde el 201 con el depósito por defecto. */
function storeHandler(
  respond: (body: CreateBranchPayload) => Response = (body) =>
    success(
      {
        ...NORTH,
        id: 12,
        code: body.code,
        name: body.name,
        warehouses: [warehouse({ id: 120, branch_id: 12, code: body.warehouse_code })],
      },
      { status: 201, message: 'Sucursal creada correctamente.' },
    ),
) {
  const bodies: CreateBranchPayload[] = [];
  const handler = http.post('/api/branches', async ({ request }) => {
    const body = (await request.json()) as CreateBranchPayload;
    bodies.push(body);

    return respond(body);
  });

  return { handler, bodies };
}

describe('/configuracion/sucursales/nueva: alta', () => {
  async function openCreate(store = storeHandler()) {
    server.use(meHandler(ADMIN), store.handler);
    const app = renderApp('/configuracion/sucursales/nueva', { session: sessionFor() });
    await screen.findByText('Nueva sucursal', { selector: 'h2' });

    return { ...app, store };
  }

  it('los códigos en mayúsculas, el nombre del depósito vacío en null; al detalle de la nueva', async () => {
    const { store, history } = await openCreate();

    type('Código', ' sur-1 ');
    type('Nombre', 'Sucursal Sur');
    type('Establecimiento', '003');
    type('Teléfono', '+595 21 110-0003');
    type('Código del depósito', 'sur-dep');
    fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));

    expect(await screen.findByText('Datos de la sucursal')).toBeInTheDocument();
    expect(store.bodies).toEqual([
      {
        code: 'SUR-1',
        name: 'Sucursal Sur',
        establishment: '003',
        address: null,
        phone: '+595211100003',
        is_default: false,
        warehouse_code: 'SUR-DEP',
        warehouse_name: null,
      },
    ]);
    expect(history.location.pathname).toBe('/configuracion/sucursales/12');
    expect(screen.getByText('Sucursal creada correctamente.')).toBeInTheDocument();
  });

  it('sin pedido con un error de forma; el 422 de la API va a su campo', async () => {
    const { store } = await openCreate(
      storeHandler(() =>
        failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
          errors: { warehouse_code: ['Ya hay un depósito con ese código.'] },
        }),
      ),
    );

    type('Código', 'SUR');
    type('Nombre', 'Sucursal Sur');
    type('Establecimiento', '000');
    fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));

    await waitFor(() => {
      expect(fieldError('Establecimiento')).toBe(BRANCH_MESSAGES.establishment.regex);
    });
    expect(fieldError('Código del depósito')).toBe(BRANCH_MESSAGES.warehouse_code.required);
    expect(store.bodies).toEqual([]);

    type('Establecimiento', '003');
    type('Código del depósito', 'CEN-DEP');
    fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));

    await waitFor(() => {
      expect(fieldError('Código del depósito')).toBe('Ya hay un depósito con ese código.');
    });
    expect(store.bodies).toHaveLength(1);
  });
});

describe('/configuracion/sucursales/$id/editar: edición', () => {
  function updateHandler(
    bodies: unknown[],
    respond: (body: Record<string, unknown>) => Response = (body) =>
      success({ ...CENTRAL, ...body }, { message: 'Sucursal actualizada correctamente.' }),
  ) {
    return http.patch('/api/branches/:id', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;
      bodies.push(body);

      return respond(body);
    });
  }

  it('la por defecto: la casilla marcada y deshabilitada, y el cuerpo con is_default: true', async () => {
    const bodies: unknown[] = [];
    server.use(
      meHandler(ADMIN),
      detailHandler(() => CENTRAL),
      updateHandler(bodies),
    );
    const { history } = renderApp('/configuracion/sucursales/10/editar', {
      session: sessionFor(),
    });
    await screen.findByText('Editar la sucursal', { selector: 'h2' });

    expect(field('Sucursal por defecto')).toBeChecked();
    expect(field('Sucursal por defecto')).toBeDisabled();
    expect(screen.queryByText('Código del depósito')).toBeNull();
    type('Nombre', 'Casa matriz');
    fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/sucursales/10');
    });
    expect(bodies).toEqual([
      {
        code: 'CEN',
        name: 'Casa matriz',
        establishment: '001',
        address: 'Av. Mcal. López 1234',
        phone: '+595211100001',
        is_default: true,
      },
    ]);
  });

  it('BRANCH_ESTABLISHMENT_NOT_EDITABLE va al campo del establecimiento', async () => {
    const message =
      'El código de establecimiento no se puede cambiar: la sucursal ya tiene facturas.';
    server.use(
      meHandler(ADMIN),
      detailHandler(() => NORTH),
      updateHandler([], () => failure(422, 'BRANCH_ESTABLISHMENT_NOT_EDITABLE', message)),
    );
    renderApp('/configuracion/sucursales/11/editar', { session: sessionFor() });
    await screen.findByText('Editar la sucursal', { selector: 'h2' });

    expect(field('Sucursal por defecto')).not.toBeDisabled();
    type('Establecimiento', '004');
    fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));

    await waitFor(() => {
      expect(fieldError('Establecimiento')).toBe(message);
    });
    expect(document.activeElement).toBe(field('Establecimiento'));
    expect(document.querySelector('.alert')).toBeNull();
  });

  it('una inactiva: "Sucursal por defecto" deshabilitada con la explicación, y el cuerpo con is_default: false', async () => {
    const bodies: unknown[] = [];
    const inactive = { ...NORTH, is_active: false };
    server.use(
      meHandler(ADMIN),
      detailHandler(() => inactive),
      updateHandler(bodies, () => success(inactive)),
    );
    renderApp('/configuracion/sucursales/11/editar', { session: sessionFor() });
    await screen.findByText('Editar la sucursal', { selector: 'h2' });

    expect(field('Sucursal por defecto')).not.toBeChecked();
    expect(field('Sucursal por defecto')).toBeDisabled();
    expect(
      screen.getByText('Activá la sucursal para marcarla como la por defecto.'),
    ).toBeInTheDocument();
    fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));

    await waitFor(() => {
      expect(bodies).toHaveLength(1);
    });
    expect(bodies[0]).toMatchObject({ is_default: false });
  });
});

describe('/configuracion/sucursales/$id: detalle y acciones de la sucursal', () => {
  it('la por defecto: sin "Inactivar" ni "Marcar como por defecto"; sus depósitos', async () => {
    await openDetail(CENTRAL);

    expect(screen.getByText('Casa central', { selector: 'h2' })).toBeInTheDocument();
    expect(button('Editar')).not.toBeNull();
    expect(button('Inactivar')).toBeNull();
    expect(button('Marcar como por defecto')).toBeNull();
    expect(button('Nuevo depósito')).not.toBeNull();
    expect(screen.getByText('CEN-DEP')).toBeInTheDocument();
  });

  it('una inactiva: "Activar" y sin "Nuevo depósito"', async () => {
    await openDetail({ ...NORTH, is_active: false });

    expect(button('Activar')).not.toBeNull();
    expect(button('Inactivar')).toBeNull();
    expect(button('Marcar como por defecto')).toBeNull();
    expect(button('Nuevo depósito')).toBeNull();
  });

  it('BRANCH_DEFAULT_NOT_DEACTIVATABLE (un detalle viejo): el mensaje en el diálogo y solo "Cerrar"', async () => {
    const message =
      'La sucursal por defecto no se puede inactivar: marcá otra como sucursal por defecto primero.';
    await openDetail(NORTH);
    server.use(
      http.patch('/api/branches/:id', () =>
        failure(409, 'BRANCH_DEFAULT_NOT_DEACTIVATABLE', message),
      ),
    );

    fireEvent.click(pageButton('Inactivar'));
    expect(dialog()).toHaveTextContent('van a tener que elegir otra');
    fireEvent.click(dialogButton('Inactivar Sucursal Norte') as HTMLElement);

    expect(await screen.findByText(message)).toBeInTheDocument();
    expect(dialogButtons()).toEqual(['Cerrar']);
    expect(dialog()).not.toHaveTextContent('¿Querés inactivar');
  });

  it('inactivar la sucursal actual vuelve a pedir me: el layout pide elegir otra sin recargar', async () => {
    let current: BranchDetail = { ...CENTRAL, is_default: false };
    let meCalls = 0;
    const bodies: unknown[] = [];
    server.use(
      http.get('/api/me', () => {
        meCalls += 1;

        return success(
          current.is_active
            ? ADMIN
            : {
                ...ADMIN,
                current_branch: null,
                branches: [{ id: 11, code: 'NOR', name: 'Sucursal Norte' }],
              },
        );
      }),
      detailHandler(() => current),
      http.patch('/api/branches/:id', async ({ request }) => {
        bodies.push(await request.json());
        current = { ...current, is_active: false };

        return success(current, { message: 'Sucursal actualizada correctamente.' });
      }),
    );
    renderApp('/configuracion/sucursales/10', { session: sessionFor() });
    await screen.findByText('Datos de la sucursal');
    const before = meCalls;

    fireEvent.click(pageButton('Inactivar'));
    fireEvent.click(dialogButton('Inactivar Casa central') as HTMLElement);

    expect(
      await screen.findByText('Elegí tu sucursal actual', { exact: false }),
    ).toBeInTheDocument();
    expect(meCalls).toBeGreaterThan(before);
    expect(bodies).toEqual([{ is_active: false }]);
    expect(button('Activar')).not.toBeNull();
  });

  it('"Marcar como por defecto" manda solo is_default: true', async () => {
    const bodies: unknown[] = [];
    await openDetail(NORTH);
    server.use(
      http.patch('/api/branches/:id', async ({ request }) => {
        bodies.push(await request.json());

        return success({ ...NORTH, is_default: true });
      }),
    );

    fireEvent.click(pageButton('Marcar como por defecto'));
    fireEvent.click(dialogButton('Marcar como por defecto') as HTMLElement);

    await waitFor(() => {
      expect(button('Inactivar')).toBeNull();
    });
    expect(bodies).toEqual([{ is_default: true }]);
  });

  it('un doble clic en la confirmación manda un solo pedido', async () => {
    const bodies: unknown[] = [];
    await openDetail(NORTH);
    server.use(
      http.patch('/api/branches/:id', async ({ request }) => {
        bodies.push(await request.json());

        return success({ ...NORTH, is_default: true });
      }),
    );

    fireEvent.click(pageButton('Marcar como por defecto'));
    const confirm = dialogButton('Marcar como por defecto') as HTMLElement;
    fireEvent.click(confirm);
    fireEvent.click(confirm);

    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    expect(bodies).toEqual([{ is_default: true }]);
  });

  it('un 404 en una acción: el aviso y de vuelta al listado', async () => {
    const { history } = await openDetail(NORTH);
    server.use(
      http.patch('/api/branches/:id', () =>
        failure(404, 'RESOURCE_NOT_FOUND', 'La sucursal no existe.'),
      ),
      http.get('/api/branches', () =>
        success([listItem(CENTRAL)], { meta: pagination({ total: 1 }) }),
      ),
    );

    fireEvent.click(pageButton('Inactivar'));
    fireEvent.click(dialogButton('Inactivar Sucursal Norte') as HTMLElement);

    expect(await screen.findByText('La sucursal no existe.')).toBeInTheDocument();
    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/sucursales');
    });
    expect(await screen.findByText('Casa central', { selector: 'a' })).toBeInTheDocument();
    expect(dialog()).toBeNull();
  });

  it('un id que no existe: la página de no encontrado', async () => {
    server.use(
      meHandler(ADMIN),
      detailHandler(() => CENTRAL),
    );
    renderApp('/configuracion/sucursales/99', { session: sessionFor() });

    expect(await screen.findByText('No encontramos esta sucursal')).toBeInTheDocument();
  });
});

describe('los depósitos, en diálogos del detalle', () => {
  it('"Nuevo depósito": el código en mayúsculas y la sucursal pedida de nuevo', async () => {
    let current = NORTH;
    const bodies: unknown[] = [];
    await openDetail(NORTH);
    server.use(
      detailHandler(() => current),
      http.post('/api/branches/11/warehouses', async ({ request }) => {
        const body = (await request.json()) as { code: string; name: string };
        bodies.push(body);
        current = {
          ...NORTH,
          warehouses: [
            ...NORTH.warehouses,
            warehouse({ id: 112, branch_id: 11, ...body, is_default: false }),
          ],
        };

        return success(current.warehouses.at(-1), {
          status: 201,
          message: 'Depósito creado correctamente.',
        });
      }),
    );

    fireEvent.click(pageButton('Nuevo depósito'));
    const form = openDialog();
    fireEvent.click(dialogButton('Crear el depósito') as HTMLElement);
    expect(fieldError('Código', form)).toBe('El código del depósito es obligatorio.');
    expect(bodies).toEqual([]);

    type('Código', ' nor-vac ', form);
    type('Nombre', 'Vacunas', form);
    fireEvent.click(dialogButton('Crear el depósito') as HTMLElement);

    expect(await screen.findByText('NOR-VAC')).toBeInTheDocument();
    expect(dialog()).toBeNull();
    expect(bodies).toEqual([{ code: 'NOR-VAC', name: 'Vacunas', is_default: false }]);
  });

  it('con el teclado: el foco en "Cancelar" al abrir, Escape cierra sin pedido y el foco vuelve a "Nuevo depósito"', async () => {
    await openDetail(NORTH);
    const opener = pageButton('Nuevo depósito');

    opener.focus();
    fireEvent.click(opener);
    expect(document.activeElement).toBe(dialogButton('Cancelar'));

    fireEvent.keyDown(openDialog(), { key: 'Escape' });

    expect(dialog()).toBeNull();
    expect(document.activeElement).toBe(opener);
  });

  it('un doble clic en "Crear el depósito" manda un solo pedido', async () => {
    const bodies: unknown[] = [];
    await openDetail(NORTH);
    server.use(
      http.post('/api/branches/11/warehouses', async ({ request }) => {
        bodies.push(await request.json());

        return success(warehouse({ id: 112, branch_id: 11, is_default: false }), {
          status: 201,
          message: 'Depósito creado correctamente.',
        });
      }),
    );

    fireEvent.click(pageButton('Nuevo depósito'));
    const form = openDialog();
    type('Código', 'NOR-VAC', form);
    type('Nombre', 'Vacunas', form);
    const create = dialogButton('Crear el depósito') as HTMLElement;
    fireEvent.click(create);
    fireEvent.click(create);

    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    expect(bodies).toHaveLength(1);
  });

  it('un depósito que ya no está (404): el aviso, el diálogo cerrado y la sucursal pedida de nuevo', async () => {
    let current = NORTH;
    await openDetail(NORTH);
    server.use(
      detailHandler(() => current),
      http.patch('/api/warehouses/:id', () => {
        current = { ...NORTH, warehouses: NORTH.warehouses.slice(0, 1) };

        return failure(404, 'RESOURCE_NOT_FOUND', 'El depósito no existe.');
      }),
    );

    fireEvent.click(screen.getByLabelText('Editar Farmacia'));
    type('Nombre', 'Farmacia central', openDialog());
    fireEvent.click(dialogButton('Guardar') as HTMLElement);

    expect(await screen.findByText('El depósito no existe.')).toBeInTheDocument();
    expect(dialog()).toBeNull();
    await waitFor(() => {
      expect(screen.queryByText('NOR-FAR')).toBeNull();
    });
  });

  it('la sucursal inactivada mientras tanto (422 en branch): el mensaje en la alerta del diálogo', async () => {
    await openDetail(NORTH);
    server.use(
      http.post('/api/branches/11/warehouses', () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
          errors: { branch: ['La sucursal está inactiva.'] },
        }),
      ),
    );

    fireEvent.click(pageButton('Nuevo depósito'));
    const form = openDialog();
    type('Código', 'NOR-VAC', form);
    type('Nombre', 'Vacunas', form);
    fireEvent.click(dialogButton('Crear el depósito') as HTMLElement);

    expect(await screen.findByText('La sucursal está inactiva.')).toBeInTheDocument();
    expect(fieldError('Código', form)).toBeNull();
  });

  it('editar el por defecto: la casilla deshabilitada y el cuerpo con is_default: true', async () => {
    const bodies: unknown[] = [];
    await openDetail(NORTH);
    server.use(
      http.patch('/api/warehouses/:id', async ({ request }) => {
        bodies.push(await request.json());

        return success(NORTH.warehouses[0], { message: 'Depósito actualizado correctamente.' });
      }),
    );

    fireEvent.click(screen.getByLabelText('Editar Principal'));
    const form = openDialog();
    expect(field('Depósito por defecto', form)).toBeChecked();
    expect(field('Depósito por defecto', form)).toBeDisabled();
    type('Nombre', 'Depósito central', form);
    fireEvent.click(dialogButton('Guardar') as HTMLElement);

    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    expect(bodies).toEqual([{ code: 'NOR-DEP', name: 'Depósito central', is_default: true }]);
  });

  it('editar un inactivo: "Depósito por defecto" deshabilitado con la explicación, y el cuerpo con is_default: false', async () => {
    const bodies: unknown[] = [];
    const inactive = warehouse({
      id: 111,
      branch_id: 11,
      code: 'NOR-FAR',
      name: 'Farmacia',
      is_default: false,
      is_active: false,
    });
    await openDetail({ ...NORTH, warehouses: [NORTH.warehouses[0] ?? warehouse(), inactive] });
    server.use(
      http.patch('/api/warehouses/:id', async ({ request }) => {
        bodies.push(await request.json());

        return success(inactive, { message: 'Depósito actualizado correctamente.' });
      }),
    );

    fireEvent.click(screen.getByLabelText('Editar Farmacia'));
    const form = openDialog();
    expect(field('Depósito por defecto', form)).not.toBeChecked();
    expect(field('Depósito por defecto', form)).toBeDisabled();
    expect(form).toHaveTextContent('Activá el depósito para marcarlo como el por defecto.');
    fireEvent.click(dialogButton('Guardar') as HTMLElement);

    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    expect(bodies).toEqual([{ code: 'NOR-FAR', name: 'Farmacia', is_default: false }]);
  });

  it('el por defecto sin "Inactivar"; otro, con WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE en el diálogo', async () => {
    const message =
      'El depósito por defecto no se puede inactivar: marcá otro como depósito por defecto primero.';
    await openDetail(NORTH);
    server.use(
      http.patch('/api/warehouses/:id', () =>
        failure(409, 'WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE', message),
      ),
    );

    expect(screen.queryByLabelText('Inactivar Principal')).toBeNull();
    expect(screen.queryByLabelText('Marcar Principal como por defecto')).toBeNull();
    fireEvent.click(screen.getByLabelText('Inactivar Farmacia'));
    fireEvent.click(dialogButton('Inactivar Farmacia') as HTMLElement);

    expect(await screen.findByText(message)).toBeInTheDocument();
    expect(dialogButtons()).toEqual(['Cerrar']);
  });

  it('cambiar el depósito por defecto invalida me (el selector de sucursal usa me.branches)', async () => {
    let meCalls = 0;
    const bodies: unknown[] = [];
    await openDetail(NORTH);
    server.use(
      http.get('/api/me', () => {
        meCalls += 1;

        return success(ADMIN);
      }),
      http.patch('/api/warehouses/:id', async ({ request }) => {
        bodies.push(await request.json());

        return success({ ...NORTH.warehouses[1], is_default: true });
      }),
    );

    fireEvent.click(screen.getByLabelText('Marcar Farmacia como por defecto'));
    await act(async () => {
      fireEvent.click(dialogButton('Marcar como por defecto') as HTMLElement);
      await Promise.resolve();
    });

    await waitFor(() => {
      expect(meCalls).toBeGreaterThan(0);
    });
    expect(bodies).toEqual([{ is_default: true }]);
  });
});
