import { fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { CENTRAL, listItem, NORTH } from '../../test/branches';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { RECEPTION_USER, testRole, userListItem, VET_USER } from '../../test/users';
import { OTHER_VET_DETAIL, VET_DETAIL, veterinarianListItem } from '../../test/veterinarians';
import type { Me } from '../auth/types';
import type { UserDetail } from '../users/types';
import { VETERINARIAN_MESSAGES } from './form';
import type { VeterinarianDetail } from './types';

// Por etiqueta o por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
function field(label: string): HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement {
  const found = Array.from(document.querySelectorAll('label')).find(
    (element) => element.textContent.replace('*', '').trim() === label,
  );
  const input = found?.htmlFor ? document.getElementById(found.htmlFor) : null;

  if (!(
    input instanceof HTMLInputElement ||
    input instanceof HTMLSelectElement ||
    input instanceof HTMLTextAreaElement
  )) {
    throw new Error(`Sin el campo ${label}`);
  }

  return input;
}

function type(label: string, value: string) {
  fireEvent.change(field(label), { target: { value } });
}

const fieldError = (label: string) =>
  document.getElementById(`${field(label).id}-error`)?.textContent ?? null;

const save = () => {
  fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));
};

const dialog = () => document.querySelector<HTMLElement>('[role="dialog"]');

const dialogButton = (label: string) =>
  Array.from(dialog()?.querySelectorAll('button') ?? []).find(
    (element) => element.textContent.trim() === label,
  ) ?? null;

function findDialogButton(label: string): Promise<HTMLElement> {
  return waitFor(() => {
    const found = dialogButton(label);

    if (found === null) {
      throw new Error(`Sin el botón ${label} en el diálogo`);
    }

    return found;
  });
}

function required(selector: string): HTMLElement {
  const element = document.querySelector<HTMLElement>(selector);

  if (element === null) {
    throw new Error(`Sin ${selector}`);
  }

  return element;
}

const ADMIN = meFor('administrador');
const RECEPTION = meFor('recepcionista');
const IN_USE_MESSAGE = 'El veterinario tiene registros y no se puede eliminar: inactivalo.';

/** Un usuario con el rol veterinario y sin ficha: el que se puede elegir en el alta. */
const NEW_VET_USER: UserDetail = {
  ...RECEPTION_USER,
  id: 12,
  first_name: 'Marta',
  last_name: 'Ríos',
  name: 'Marta Ríos',
  email: 'vet-c@example.com',
  roles: [testRole('veterinario')],
  veterinarian: null,
};

function branchesHandler() {
  return http.get('/api/branches', () =>
    success([listItem(CENTRAL), listItem(NORTH)], { meta: pagination({ total: 2 }) }),
  );
}

function detailHandler(current: () => VeterinarianDetail) {
  return http.get('/api/veterinarians/:id', ({ params }) =>
    String(params.id) === String(current().id)
      ? success(current())
      : failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'),
  );
}

function listHandler(urls: URL[] = [], items = [VET_DETAIL, OTHER_VET_DETAIL]) {
  return http.get('/api/veterinarians', ({ request }) => {
    urls.push(new URL(request.url));

    return success(items.map(veterinarianListItem), {
      meta: pagination({ total: items.length }),
    });
  });
}

/** GET users?role=veterinario&per_page=100: las opciones del alta. */
function userOptionsHandler(urls: URL[] = [], users: UserDetail[] = [VET_USER, NEW_VET_USER]) {
  return http.get('/api/users', ({ request }) => {
    urls.push(new URL(request.url));

    return success(users.map(userListItem), { meta: pagination({ total: users.length }) });
  });
}

async function openDetail(
  veterinarian: VeterinarianDetail,
  extra: Parameters<typeof server.use> = [],
  me: Me = ADMIN,
) {
  server.use(
    ...extra,
    meHandler(me),
    detailHandler(() => veterinarian),
  );
  const app = renderApp(`/veterinarios/${String(veterinarian.id)}`, { session: sessionFor() });
  await screen.findByText(`${veterinarian.code} · ${veterinarian.user.name}`, {
    selector: '.veterinarian-title',
  });

  return app;
}

describe('/veterinarios: listado', () => {
  it('el administrador: la tabla con el enlace y "Nuevo veterinario"; el estado y la sucursal van a la URL y al pedido', async () => {
    const urls: URL[] = [];
    server.use(meHandler(ADMIN), branchesHandler(), listHandler(urls));
    const { history } = renderApp('/veterinarios', { session: sessionFor() });

    expect(await screen.findByText('Víctor Acosta', { selector: 'a' })).toBeInTheDocument();
    expect(screen.getByText('MV-1001', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('CEN, NOR', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('Usuario inactivo', { selector: 'span' })).toBeInTheDocument();
    expect(screen.getByText('Nuevo veterinario', { selector: 'a' })).toBeInTheDocument();
    // Por defecto, solo los activos: ni status ni include_inactive.
    expect(urls[0]?.searchParams.has('status')).toBe(false);
    expect(urls[0]?.searchParams.has('include_inactive')).toBe(false);

    fireEvent.change(screen.getByLabelText('Estado'), { target: { value: 'inactivos' } });
    await waitFor(() => {
      expect(urls.at(-1)?.searchParams.get('status')).toBe('inactive');
    });
    expect(history.location.search).toContain('estado=inactivos');

    fireEvent.change(screen.getByLabelText('Estado'), { target: { value: 'todos' } });
    await waitFor(() => {
      expect(urls.at(-1)?.searchParams.get('include_inactive')).toBe('1');
    });
    expect(urls.at(-1)?.searchParams.has('status')).toBe(false);

    await screen.findByText('Sucursal Norte', { selector: 'option' });
    fireEvent.change(screen.getByLabelText('Sucursal'), { target: { value: '11' } });
    await waitFor(() => {
      expect(urls.at(-1)?.searchParams.get('branch_id')).toBe('11');
    });
    expect(urls.at(-1)?.searchParams.get('per_page')).toBe('20');
    expect(history.location.search).toContain('sucursal=11');
  });

  it('el recepcionista: el listado sin enlaces, sin "Nuevo veterinario" y sin la comisión', async () => {
    server.use(meHandler(RECEPTION), branchesHandler(), listHandler());
    renderApp('/veterinarios', { session: sessionFor() });

    expect(
      await screen.findByText('Víctor Acosta', { selector: '.veterinarian-name' }),
    ).toBeInTheDocument();
    expect(document.querySelector('.veterinarian-link')).toBeNull();
    expect(screen.queryByText('Nuevo veterinario')).toBeNull();
    expect(screen.queryByText('Comisión', { selector: 'th' })).toBeNull();
    expect(document.body.textContent).not.toContain('12,5');
  });

  it('un estado desconocido en la URL se descarta: el pedido sale sin status', async () => {
    const urls: URL[] = [];
    server.use(meHandler(ADMIN), branchesHandler(), listHandler(urls, []));
    renderApp('/veterinarios?estado=borrados&sucursal=abc', { session: sessionFor() });

    expect(await screen.findByText('Todavía no hay veterinarios.')).toBeInTheDocument();
    expect(urls[0]?.searchParams.has('status')).toBe(false);
    expect(urls[0]?.searchParams.has('branch_id')).toBe(false);
  });
});

describe('/veterinarios/$id: detalle', () => {
  it('el administrador: la comisión con coma, las notas y el enlace al usuario', async () => {
    await openDetail(VET_DETAIL);

    expect(screen.getByText('12,5 %', { selector: 'dd' })).toBeInTheDocument();
    expect(screen.getByText('Guardias los sábados.', { selector: 'dd' })).toBeInTheDocument();
    expect(screen.getByText('Víctor Acosta', { selector: 'a' })).toHaveAttribute(
      'href',
      '/configuracion/usuarios/8',
    );
  });

  it.each(['recepcionista', 'veterinario'] as const)(
    '%s en /veterinarios/$id va a /sin-permiso, sin pedir la ficha',
    async (role) => {
      const calls: string[] = [];
      server.use(
        meHandler(meFor(role)),
        http.get('/api/veterinarians/:id', ({ params }) => {
          calls.push(String(params.id));

          return success(VET_DETAIL);
        }),
      );
      const { history } = renderApp('/veterinarios/4', { session: sessionFor() });

      expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
      expect(history.location.pathname).toBe('/sin-permiso');
      expect(calls).toEqual([]);
    },
  );

  it('un id que no existe: la página de no encontrado', async () => {
    server.use(
      meHandler(ADMIN),
      detailHandler(() => VET_DETAIL),
    );
    renderApp('/veterinarios/99', { session: sessionFor() });

    expect(await screen.findByText('No encontramos este veterinario')).toBeInTheDocument();
  });
});

describe('/veterinarios/nuevo: alta', () => {
  it('el selector sin los usuarios con ficha; ?usuario= lo deja elegido; la matrícula en mayúsculas y la comisión con punto', async () => {
    const bodies: Record<string, unknown>[] = [];
    const userUrls: URL[] = [];
    const created: VeterinarianDetail = {
      ...VET_DETAIL,
      id: 6,
      code: 'VET-0003',
      user: { id: 12, name: 'Marta Ríos', email: 'vet-c@example.com', is_active: true },
      license_number: 'MV-2002',
      commission_percentage: '7.50',
    };
    server.use(
      meHandler(ADMIN),
      userOptionsHandler(userUrls),
      http.post('/api/veterinarians', async ({ request }) => {
        bodies.push((await request.json()) as Record<string, unknown>);

        return success(created, { status: 201, message: 'Veterinario creado correctamente.' });
      }),
      detailHandler(() => created),
    );
    const { history, queryClient } = renderApp('/veterinarios/nuevo?usuario=12', {
      session: sessionFor(),
    });

    await screen.findByText('Nuevo veterinario', { selector: 'h2' });
    expect(userUrls[0]?.searchParams.get('role')).toBe('veterinario');
    expect(userUrls[0]?.searchParams.get('per_page')).toBe('100');
    // Víctor Acosta ya tiene ficha: no se ofrece.
    const options = Array.from((field('Usuario') as HTMLSelectElement).options).map(
      (option) => option.textContent,
    );
    expect(options).toEqual(['Elegí el usuario', 'Ríos, Marta (vet-c@example.com)']);
    expect(field('Usuario').value).toBe('12');

    type('Matrícula', '  mv-2002 ');
    type('Comisión (%)', '7,5');
    save();

    expect(await screen.findByText('VET-0003 · Marta Ríos')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/veterinarios/6');
    expect(bodies).toEqual([
      {
        user_id: 12,
        license_number: 'MV-2002',
        specialty: null,
        commission_percentage: '7.5',
        notes: null,
      },
    ]);
    expect(screen.getByText('7,5 %', { selector: 'dd' })).toBeInTheDocument();
    // Los usuarios quedan viejos (la ficha está en su detalle, el listado y el selector).
    expect(
      queryClient.getQueryState([
        'users',
        'options',
        { role: 'veterinario', include_inactive: false },
      ])?.isInvalidated,
    ).toBe(true);
  });

  it('una ficha eliminada que la API restaura: el detalle muestra su código de siempre', async () => {
    const restored: VeterinarianDetail = {
      ...OTHER_VET_DETAIL,
      user: { ...OTHER_VET_DETAIL.user, id: 12, name: 'Marta Ríos', is_active: true },
      status: { value: 'active', label: 'Activo' },
    };
    server.use(
      meHandler(ADMIN),
      userOptionsHandler(),
      http.post('/api/veterinarians', () =>
        success(restored, { status: 201, message: 'Veterinario creado correctamente.' }),
      ),
      detailHandler(() => restored),
    );
    renderApp('/veterinarios/nuevo?usuario=12', { session: sessionFor() });

    await screen.findByText('Nuevo veterinario', { selector: 'h2' });
    save();

    expect(await screen.findByText('VET-0002 · Marta Ríos')).toBeInTheDocument();
    expect(screen.getByText('VET-0002', { selector: 'dd' })).toBeInTheDocument();
    expect(await screen.findByText('Veterinario creado correctamente.')).toBeInTheDocument();
  });

  it('sin elegir el usuario: el error en el campo, sin pedido', async () => {
    const bodies: unknown[] = [];
    server.use(
      meHandler(ADMIN),
      userOptionsHandler(),
      http.post('/api/veterinarians', () => {
        bodies.push(1);

        return success(VET_DETAIL, { status: 201 });
      }),
    );
    renderApp('/veterinarios/nuevo', { session: sessionFor() });

    await screen.findByText('Nuevo veterinario', { selector: 'h2' });
    save();

    await waitFor(() => {
      expect(fieldError('Usuario')).toBe(VETERINARIAN_MESSAGES.user_id.required);
    });
    expect(bodies).toEqual([]);
  });

  it('sin usuarios para elegir: el aviso con el enlace a "Nuevo usuario"', async () => {
    server.use(meHandler(ADMIN), userOptionsHandler([], [VET_USER]));
    renderApp('/veterinarios/nuevo', { session: sessionFor() });

    expect(
      await screen.findByText('No hay usuarios con el rol veterinario sin ficha.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Nuevo usuario', { selector: 'a' })).toHaveAttribute(
      'href',
      '/configuracion/usuarios/nuevo',
    );
    expect(screen.queryByText('Guardar', { selector: 'button' })).toBeNull();
  });

  it('422 de la matrícula repetida: el mensaje de la API en su campo', async () => {
    const message = 'Ya hay un veterinario con esa matrícula.';
    server.use(
      meHandler(ADMIN),
      userOptionsHandler(),
      http.post('/api/veterinarians', () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
          errors: { license_number: [message] },
        }),
      ),
    );
    renderApp('/veterinarios/nuevo?usuario=12', { session: sessionFor() });

    await screen.findByText('Nuevo veterinario', { selector: 'h2' });
    type('Matrícula', 'mv-1001');
    save();

    await waitFor(() => {
      expect(fieldError('Matrícula')).toBe(message);
    });
  });

  it('el recepcionista en /veterinarios/nuevo va a /sin-permiso', async () => {
    server.use(meHandler(RECEPTION));
    const { history } = renderApp('/veterinarios/nuevo', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/sin-permiso');
  });
});

describe('/veterinarios/$id/editar', () => {
  it('el usuario y el código solo para leer; el cuerpo sin user_id, code ni status; la comisión con coma', async () => {
    const bodies: Record<string, unknown>[] = [];
    let current = VET_DETAIL;
    server.use(
      meHandler(ADMIN),
      detailHandler(() => current),
      http.patch('/api/veterinarians/:id', async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>;
        bodies.push(body);
        current = {
          ...current,
          license_number: body.license_number as string,
          commission_percentage: '15.25',
        };

        return success(current, { message: 'Veterinario actualizado correctamente.' });
      }),
    );
    const { history } = renderApp('/veterinarios/4/editar', { session: sessionFor() });

    await screen.findByText('Editar el veterinario', { selector: 'h2' });
    expect(field('Comisión (%)').value).toBe('12,50');
    expect(
      screen.getByText('VET-0001', { selector: '.veterinarian-fixed dd' }),
    ).toBeInTheDocument();
    expect(() => field('Usuario')).toThrow();

    type('Matrícula', 'mv-1001-b');
    type('Comisión (%)', '15,25');
    save();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/veterinarios/4');
    });
    expect(await screen.findByText('15,25 %', { selector: 'dd' })).toBeInTheDocument();
    expect(bodies).toEqual([
      {
        license_number: 'MV-1001-B',
        specialty: 'Clínica general',
        commission_percentage: '15.25',
        notes: 'Guardias los sábados.',
      },
    ]);
  });

  it('la comisión fuera de rango: el mensaje de la API en el campo, sin pedido', async () => {
    const bodies: unknown[] = [];
    server.use(
      meHandler(ADMIN),
      detailHandler(() => VET_DETAIL),
      http.patch('/api/veterinarians/:id', () => {
        bodies.push(1);

        return success(VET_DETAIL);
      }),
    );
    renderApp('/veterinarios/4/editar', { session: sessionFor() });

    await screen.findByText('Editar el veterinario', { selector: 'h2' });
    type('Comisión (%)', '100,5');
    save();

    await waitFor(() => {
      expect(fieldError('Comisión (%)')).toBe(VETERINARIAN_MESSAGES.commission_percentage.between);
    });
    expect(bodies).toEqual([]);
  });
});

describe('/veterinarios/$id: inactivar, activar y eliminar', () => {
  function statusHandler(bodies: Record<string, unknown>[], base: VeterinarianDetail) {
    return http.patch('/api/veterinarians/:id', async ({ request }) => {
      const body = (await request.json()) as { status: 'active' | 'inactive' };
      bodies.push(body);

      return success(
        {
          ...base,
          status: {
            value: body.status,
            label: body.status === 'active' ? 'Activo' : 'Inactivo',
          },
        },
        { message: 'Veterinario actualizado correctamente.' },
      );
    });
  }

  function deleteHandler(calls: string[], respond: () => Response) {
    return http.delete('/api/veterinarians/:id', ({ params }) => {
      calls.push(String(params.id));

      return respond();
    });
  }

  it('el administrador ve Editar, Inactivar y Eliminar', async () => {
    await openDetail(VET_DETAIL);

    expect(required('.veterinarian-status-action').textContent).toBe('Inactivar');
    expect(required('.veterinarian-delete').textContent).toBe('Eliminar');
    expect(required('.veterinarian-edit')).toHaveAttribute('href', '/veterinarios/4/editar');
  });

  it('inactivar: el cuerpo solo con el estado, la marca y los usuarios viejos', async () => {
    const bodies: Record<string, unknown>[] = [];
    const { queryClient } = await openDetail(VET_DETAIL, [statusHandler(bodies, VET_DETAIL)]);
    queryClient.setQueryData(['users', 'detail', 8], VET_USER);

    fireEvent.click(required('.veterinarian-status-action'));
    fireEvent.click(await findDialogButton('Inactivar a Víctor Acosta'));

    await waitFor(() => {
      expect(required('.veterinarian-status-action').textContent).toBe('Activar');
    });
    expect(screen.getByText('Inactivo', { selector: '.veterinarian-status' })).toBeInTheDocument();
    expect(bodies).toEqual([{ status: 'inactive' }]);
    expect(queryClient.getQueryState(['users', 'detail', 8])?.isInvalidated).toBe(true);
  });

  it('activar una ficha inactiva: { status: active }', async () => {
    const bodies: Record<string, unknown>[] = [];
    await openDetail(OTHER_VET_DETAIL, [statusHandler(bodies, OTHER_VET_DETAIL)]);

    fireEvent.click(required('.veterinarian-status-action'));
    fireEvent.click(await findDialogButton('Activar a Laura Benítez'));

    await waitFor(() => {
      expect(bodies).toEqual([{ status: 'active' }]);
    });
  });

  it('eliminar con el 200: al listado, con el aviso', async () => {
    const calls: string[] = [];
    const { history } = await openDetail(VET_DETAIL, [
      deleteHandler(calls, () =>
        success(null, { message: 'Veterinario eliminado correctamente.' }),
      ),
      branchesHandler(),
      listHandler([], [OTHER_VET_DETAIL]),
    ]);

    fireEvent.click(required('.veterinarian-delete'));
    fireEvent.click(await findDialogButton('Eliminar a Víctor Acosta'));

    await waitFor(() => {
      expect(history.location.pathname).toBe('/veterinarios');
    });
    expect(await screen.findByText('Veterinario eliminado correctamente.')).toBeInTheDocument();
    expect(calls).toEqual(['4']);
  });

  it('eliminar con VETERINARIAN_IN_USE: el mensaje y "Inactivar", que abre el diálogo de inactivar', async () => {
    const calls: string[] = [];
    const bodies: Record<string, unknown>[] = [];
    await openDetail(VET_DETAIL, [
      deleteHandler(calls, () => failure(409, 'VETERINARIAN_IN_USE', IN_USE_MESSAGE)),
      statusHandler(bodies, VET_DETAIL),
    ]);

    fireEvent.click(required('.veterinarian-delete'));
    fireEvent.click(await findDialogButton('Eliminar a Víctor Acosta'));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain(IN_USE_MESSAGE);
    });
    expect(dialog()?.textContent).not.toContain('¿Querés eliminar');
    expect(dialogButton('Inactivar')?.className).toContain('btn-primary');
    fireEvent.click(dialogButton('Inactivar') as HTMLElement);

    fireEvent.click(await findDialogButton('Inactivar a Víctor Acosta'));

    await waitFor(() => {
      expect(bodies).toEqual([{ status: 'inactive' }]);
    });
    expect(calls).toEqual(['4']);
  });

  it('VETERINARIAN_IN_USE en una ficha ya inactiva: el mensaje y solo "Cerrar"', async () => {
    await openDetail(OTHER_VET_DETAIL, [
      deleteHandler([], () => failure(409, 'VETERINARIAN_IN_USE', IN_USE_MESSAGE)),
    ]);

    fireEvent.click(required('.veterinarian-delete'));
    fireEvent.click(await findDialogButton('Eliminar a Laura Benítez'));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain(IN_USE_MESSAGE);
    });
    expect(dialogButton('Inactivar')).toBeNull();
    expect(dialogButton('Eliminar a Laura Benítez')).toBeNull();
    expect(dialogButton('Cerrar')).not.toBeNull();
  });
});

describe('el detalle del usuario: su ficha de veterinario', () => {
  function userDetailHandler(user: UserDetail) {
    return http.get('/api/users/:id', () => success(user));
  }

  it('con ficha: el código enlaza a /veterinarios/$id', async () => {
    server.use(meHandler(ADMIN), userDetailHandler(VET_USER));
    renderApp('/configuracion/usuarios/8', { session: sessionFor() });

    expect(await screen.findByText('VET-0001', { selector: 'a' })).toHaveAttribute(
      'href',
      '/veterinarios/4',
    );
    expect(screen.queryByText('Crear la ficha de veterinario')).toBeNull();
  });

  it('con el rol y sin ficha: "Crear la ficha de veterinario" lleva al alta con ?usuario=', async () => {
    server.use(meHandler(ADMIN), userDetailHandler(NEW_VET_USER), userOptionsHandler());
    const { history } = renderApp('/configuracion/usuarios/12', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Crear la ficha de veterinario', { selector: 'a' }));

    expect(await screen.findByText('Nuevo veterinario', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/veterinarios/nuevo');
    expect(history.location.search).toContain('usuario=12');
    expect(field('Usuario').value).toBe('12');
  });

  it.each([
    ['sin el rol veterinario', RECEPTION_USER],
    ['inactivo', { ...NEW_VET_USER, is_active: false }],
  ])('%s: sin el botón', async (_case, user) => {
    server.use(meHandler(ADMIN), userDetailHandler(user));
    renderApp(`/configuracion/usuarios/${String(user.id)}`, { session: sessionFor() });

    expect(await screen.findByText('Sin ficha', { selector: 'dd' })).toBeInTheDocument();
    expect(screen.queryByText('Crear la ficha de veterinario')).toBeNull();
  });
});
