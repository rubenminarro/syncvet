import type { QueryClient } from '@tanstack/react-query';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { CENTRAL, listItem, NORTH } from '../../test/branches';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import {
  ADMIN_USER,
  RECEPTION_USER,
  ROLES_RESPONSE,
  testRole,
  userListItem,
  VET_USER,
} from '../../test/users';
import type { Me } from '../auth/types';
import { USER_MESSAGES } from './form';
import type { UserDetail } from './types';

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

const hasField = (label: string) => {
  try {
    field(label);

    return true;
  } catch {
    return false;
  }
};

function type(label: string, value: string) {
  fireEvent.change(field(label), { target: { value } });
}

const fieldError = (label: string) =>
  document.getElementById(`${field(label).id}-error`)?.textContent ?? null;

/** El error de un grupo de casillas (roles, sucursales): el de su `fieldset`. */
const groupError = (className: string) =>
  document.querySelector(`.${className} .invalid-feedback`)?.textContent ?? null;

const save = () => {
  fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));
};

const dialog = () => document.querySelector<HTMLElement>('[role="dialog"]');

const dialogButton = (label: string) =>
  Array.from(dialog()?.querySelectorAll('button') ?? []).find(
    (element) => element.textContent.trim() === label,
  ) ?? null;

/** Espera el botón del diálogo (waitFor reintenta solo si el callback falla, no si devuelve null). */
function findDialogButton(label: string): Promise<HTMLElement> {
  return waitFor(() => {
    const found = dialogButton(label);

    if (found === null) {
      throw new Error(`Sin el botón ${label} en el diálogo`);
    }

    return found;
  });
}

/** El estado de las mutaciones de la caché, como texto: para comprobar que la contraseña no quedó ahí. */
const mutationCacheText = (queryClient: QueryClient) =>
  JSON.stringify(
    queryClient
      .getMutationCache()
      .getAll()
      .map((mutation) => mutation.state),
  );

const ADMIN = meFor('administrador');
const PASSWORD = 'Clave-Segura-2026';

/** Una sucursal inactiva de la clínica: no se ofrece en el alta; en la edición, solo si el usuario ya la tiene. */
const SOUTH = { ...listItem(NORTH), id: 12, code: 'SUR', name: 'Sucursal Sur', is_active: false };

/** GET roles y GET branches (las opciones: todas, también las inactivas). */
function optionHandlers() {
  return [
    http.get('/api/roles', () => success(ROLES_RESPONSE)),
    http.get('/api/branches', () =>
      success([listItem(CENTRAL), listItem(NORTH), SOUTH], { meta: pagination({ total: 3 }) }),
    ),
  ];
}

/** GET users/{id} con lo que devuelva `current`. */
function detailHandler(current: () => UserDetail) {
  return http.get('/api/users/:id', ({ params }) =>
    String(params.id) === String(current().id)
      ? success(current())
      : failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'),
  );
}

/** PATCH users/{id}: guarda los cuerpos y responde con `respond` (por defecto, el usuario con el cuerpo aplicado). */
function updateHandler(
  bodies: Record<string, unknown>[],
  base: UserDetail,
  respond?: (body: Record<string, unknown>) => Response,
) {
  return http.patch('/api/users/:id', async ({ request }) => {
    const body = (await request.json()) as Record<string, unknown>;
    bodies.push(body);

    if (respond !== undefined) {
      return respond(body);
    }

    const roles = (body.roles as UserDetail['roles'][number]['name'][]).map(testRole);

    return success(
      { ...base, first_name: body.first_name, roles },
      { message: 'Usuario actualizado correctamente.' },
    );
  });
}

async function openEdit(
  user: UserDetail,
  me: Me = ADMIN,
  extra: Parameters<typeof server.use> = [],
) {
  // Los del caso primero: MSW usa el primer handler que coincide.
  server.use(
    ...extra,
    meHandler(me),
    ...optionHandlers(),
    detailHandler(() => user),
  );
  const app = renderApp(`/configuracion/usuarios/${String(user.id)}/editar`, {
    session: sessionFor(),
  });
  await screen.findByText('Editar el usuario', { selector: 'h2' });

  return app;
}

describe('/configuracion: la tarjeta de Usuarios', () => {
  it('el administrador la ve y lleva al listado', async () => {
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      http.get('/api/users', () =>
        success([userListItem(ADMIN_USER)], { meta: pagination({ total: 1 }) }),
      ),
    );
    const { history } = renderApp('/configuracion', { session: sessionFor() });

    fireEvent.click(await screen.findByText('Usuarios', { selector: 'span' }));

    expect(await screen.findByText('Usuarios', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/configuracion/usuarios');
  });

  it.each(['recepcionista', 'veterinario'] as const)(
    '%s en /configuracion/usuarios va a /sin-permiso',
    async (role) => {
      server.use(meHandler(meFor(role)));
      const { history } = renderApp('/configuracion/usuarios/nuevo', { session: sessionFor() });

      expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
      expect(history.location.pathname).toBe('/sin-permiso');
    },
  );
});

describe('/configuracion/usuarios: listado', () => {
  it('la tabla; el rol, la sucursal e "Incluir los inactivos" van a la URL y al pedido', async () => {
    const urls: URL[] = [];
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      http.get('/api/users', ({ request }) => {
        const url = new URL(request.url);
        urls.push(url);

        return success([userListItem(ADMIN_USER), userListItem(RECEPTION_USER)], {
          meta: pagination(),
        });
      }),
    );
    const { history } = renderApp('/configuracion/usuarios', { session: sessionFor() });

    expect(await screen.findByText('Benítez, Rosa', { selector: 'a' })).toBeInTheDocument();
    expect(screen.getByText('CEN, NOR', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('Recepcionista', { selector: 'td' })).toBeInTheDocument();
    // Las opciones de la sucursal: la inactiva, marcada.
    await screen.findByText('Sucursal Sur (inactivada)', { selector: 'option' });

    fireEvent.change(screen.getByLabelText('Rol'), { target: { value: 'recepcionista' } });
    await waitFor(() => {
      expect(history.location.search).toContain('rol=recepcionista');
    });
    fireEvent.change(screen.getByLabelText('Sucursal'), { target: { value: '11' } });
    await waitFor(() => {
      expect(history.location.search).toContain('sucursal=11');
    });
    fireEvent.click(field('Incluir los inactivos'));
    await waitFor(() => {
      expect(urls.at(-1)?.searchParams.get('include_inactive')).toBe('1');
    });

    const last = urls.at(-1);
    expect(last?.searchParams.get('role')).toBe('recepcionista');
    expect(last?.searchParams.get('branch_id')).toBe('11');
    expect(last?.searchParams.get('per_page')).toBe('20');
    expect(history.location.search).toContain('inactivos=1');
  });

  it('un rol desconocido en la URL se descarta: el pedido sale sin role', async () => {
    const urls: URL[] = [];
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      http.get('/api/users', ({ request }) => {
        urls.push(new URL(request.url));

        return success([], { meta: pagination({ total: 0 }) });
      }),
    );
    renderApp('/configuracion/usuarios?rol=gerente&sucursal=abc', { session: sessionFor() });

    expect(await screen.findByText('Todavía no hay usuarios.')).toBeInTheDocument();
    expect(urls[0]?.searchParams.has('role')).toBe(false);
    expect(urls[0]?.searchParams.has('branch_id')).toBe(false);
  });
});

describe('/configuracion/usuarios/$id: detalle', () => {
  it('el usuario de la sesión con "(vos)", su sucursal actual marcada y sin ficha', async () => {
    server.use(
      meHandler(ADMIN),
      detailHandler(() => ADMIN_USER),
    );
    renderApp('/configuracion/usuarios/3', { session: sessionFor() });

    expect(await screen.findByText('(vos)')).toBeInTheDocument();
    expect(screen.getByText('Actual', { selector: '.user-current-branch' })).toBeInTheDocument();
    expect(screen.getByText('Sin ficha', { selector: 'dd' })).toBeInTheDocument();
  });

  it('otro usuario: sin "(vos)", la sucursal actual en la suya y la ficha de veterinario', async () => {
    server.use(
      meHandler(ADMIN),
      detailHandler(() => VET_USER),
    );
    renderApp('/configuracion/usuarios/8', { session: sessionFor() });

    expect(await screen.findByText('VET-0001', { selector: 'dd' })).toBeInTheDocument();
    expect(screen.queryByText('(vos)')).toBeNull();
  });

  it('un id que no existe: la página de no encontrado', async () => {
    server.use(
      meHandler(ADMIN),
      detailHandler(() => ADMIN_USER),
    );
    renderApp('/configuracion/usuarios/99', { session: sessionFor() });

    expect(await screen.findByText('No encontramos este usuario')).toBeInTheDocument();
  });
});

describe('/configuracion/usuarios/nuevo: alta', () => {
  function storeHandler(
    bodies: Record<string, unknown>[],
    respond: () => Response = () =>
      success(
        { ...RECEPTION_USER, id: 20, branches: [RECEPTION_USER.branches[0]] },
        { status: 201, message: 'Usuario creado correctamente.' },
      ),
  ) {
    return http.post('/api/users', async ({ request }) => {
      bodies.push((await request.json()) as Record<string, unknown>);

      return respond();
    });
  }

  async function openCreate(bodies: Record<string, unknown>[], respond?: () => Response) {
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      storeHandler(bodies, respond),
      detailHandler(() => ({ ...RECEPTION_USER, id: 20 })),
    );
    const app = renderApp('/configuracion/usuarios/nuevo', { session: sessionFor() });
    await screen.findByText('Nuevo usuario', { selector: 'h2' });

    return app;
  }

  function fill() {
    type('Nombre', ' Rosa ');
    type('Apellido', 'Benítez');
    type('Email', ' Rosa.Benitez@Example.COM ');
    fireEvent.click(field('Recepcionista'));
    fireEvent.click(field('CEN · Casa central'));
    type('Contraseña', PASSWORD);
    type('Repetí la contraseña', PASSWORD);
  }

  it('el email en minúsculas, roles y sucursales en el cuerpo; al detalle del nuevo', async () => {
    const bodies: Record<string, unknown>[] = [];
    const { history, queryClient } = await openCreate(bodies);

    // Las sucursales inactivas no se ofrecen en el alta.
    expect(hasField('SUR · Sucursal Sur (inactivada)')).toBe(false);
    fill();
    save();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/usuarios/20');
    });
    expect(bodies).toEqual([
      {
        first_name: 'Rosa',
        last_name: 'Benítez',
        email: 'rosa.benitez@example.com',
        roles: ['recepcionista'],
        branch_ids: [10],
        password: PASSWORD,
      },
    ]);
    // La contraseña no quedó en la caché de mutaciones.
    await waitFor(() => {
      expect(
        JSON.stringify(
          queryClient
            .getMutationCache()
            .getAll()
            .map((m) => m.state),
        ),
      ).not.toContain(PASSWORD);
    });
  });

  it('dos envíos seguidos mandan un solo pedido', async () => {
    const bodies: Record<string, unknown>[] = [];
    const { history } = await openCreate(bodies);

    fill();
    const form = document.querySelector('form.user-form') ?? document.body;
    fireEvent.submit(form);
    fireEvent.submit(form);

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/usuarios/20');
    });
    expect(bodies).toHaveLength(1);
  });

  it('sin pedido con un error de forma: la contraseña corta, sin rol y sin sucursal', async () => {
    const bodies: Record<string, unknown>[] = [];
    await openCreate(bodies);

    type('Nombre', 'Rosa');
    type('Apellido', 'Benítez');
    type('Email', 'rosa@example.com');
    type('Contraseña', 'Abcdefgh-1x');
    type('Repetí la contraseña', 'Abcdefgh-1x');
    save();

    await waitFor(() => {
      expect(fieldError('Contraseña')).toBe(USER_MESSAGES.password.min);
    });
    expect(groupError('user-roles')).toBe(USER_MESSAGES.roles.required);
    expect(groupError('user-branches-field')).toBe(USER_MESSAGES.branch_ids.required);
    expect(bodies).toEqual([]);
  });

  it('el 422 del email y de branch_ids.0 van a sus campos, sin dejar la contraseña en la caché', async () => {
    const bodies: Record<string, unknown>[] = [];
    const { queryClient } = await openCreate(bodies, () =>
      failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
        errors: {
          email: ['Ya hay un usuario con ese email.'],
          'branch_ids.0': ['La sucursal no existe o está inactiva.'],
        },
      }),
    );

    fill();
    save();

    await waitFor(() => {
      expect(fieldError('Email')).toBe('Ya hay un usuario con ese email.');
    });
    expect(groupError('user-branches-field')).toBe('La sucursal no existe o está inactiva.');
    expect(bodies).toHaveLength(1);
    // Desvío 4: el error llega al formulario y la mutación (con la contraseña) igual sale de la caché.
    expect(field('Contraseña').value).toBe(PASSWORD);
    await waitFor(() => {
      expect(mutationCacheText(queryClient)).not.toContain(PASSWORD);
    });
  });
});

describe('/configuracion/usuarios/$id/editar: otro usuario', () => {
  it('el 422 de la contraseña filtrada va a su campo, sin dejarla en la caché', async () => {
    const message = 'Esa contraseña aparece en filtraciones de datos conocidas: elegí otra.';
    const bodies: Record<string, unknown>[] = [];
    const { history, queryClient } = await openEdit(RECEPTION_USER, ADMIN, [
      updateHandler(bodies, RECEPTION_USER, () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
          errors: { password: [message] },
        }),
      ),
    ]);

    fireEvent.click(field('Cambiar la contraseña'));
    await screen.findByText('Se van a cerrar todas sus sesiones.');
    type('Contraseña nueva', PASSWORD);
    type('Repetí la contraseña', PASSWORD);
    save();

    await waitFor(() => {
      expect(fieldError('Contraseña nueva')).toBe(message);
    });
    expect(bodies).toEqual([expect.objectContaining({ password: PASSWORD })]);
    expect(history.location.pathname).toBe('/configuracion/usuarios/7/editar');
    await waitFor(() => {
      expect(mutationCacheText(queryClient)).not.toContain(PASSWORD);
    });
  });

  it('sin "Cambiar la contraseña", el cuerpo no lleva la clave password; la inactiva que ya tenía se ofrece', async () => {
    const bodies: Record<string, unknown>[] = [];
    const user: UserDetail = {
      ...RECEPTION_USER,
      branches: [...RECEPTION_USER.branches, { id: 12, code: 'SUR', name: 'Sucursal Sur' }],
    };
    const { history } = await openEdit(user, ADMIN, [updateHandler(bodies, user)]);

    expect(field('SUR · Sucursal Sur (inactivada)')).toBeChecked();
    expect(hasField('Contraseña nueva')).toBe(false);
    type('Nombre', 'Rosalía');
    save();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/usuarios/7');
    });
    expect(bodies).toEqual([
      {
        first_name: 'Rosalía',
        last_name: 'Benítez',
        email: 'recepcion-a@example.com',
        roles: ['recepcionista'],
        branch_ids: [10, 11, 12],
      },
    ]);
  });

  it('con "Cambiar la contraseña": el aviso de las sesiones y la contraseña en el cuerpo', async () => {
    const bodies: Record<string, unknown>[] = [];
    const { history, queryClient } = await openEdit(RECEPTION_USER, ADMIN, [
      updateHandler(bodies, RECEPTION_USER),
    ]);

    fireEvent.click(field('Cambiar la contraseña'));
    expect(await screen.findByText('Se van a cerrar todas sus sesiones.')).toBeInTheDocument();
    save();
    await waitFor(() => {
      expect(fieldError('Contraseña nueva')).toBe(USER_MESSAGES.password.required);
    });
    expect(bodies).toEqual([]);

    type('Contraseña nueva', PASSWORD);
    type('Repetí la contraseña', PASSWORD);
    save();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/usuarios/7');
    });
    expect(bodies[0]?.password).toBe(PASSWORD);
    await waitFor(() => {
      expect(
        JSON.stringify(
          queryClient
            .getMutationCache()
            .getAll()
            .map((m) => m.state),
        ),
      ).not.toContain(PASSWORD);
    });
  });

  it('USER_IS_VETERINARIAN: la alerta con el mensaje y el código de la ficha', async () => {
    const message =
      'El usuario tiene una ficha de veterinario: eliminala antes de quitarle el rol.';
    await openEdit(VET_USER, ADMIN, [
      updateHandler([], VET_USER, () => failure(409, 'USER_IS_VETERINARIAN', message)),
    ]);

    fireEvent.click(field('Veterinario'));
    fireEvent.click(field('Recepcionista'));
    save();

    expect(await screen.findByText(message)).toBeInTheDocument();
    expect(screen.getByText('Ficha de veterinario: VET-0001.')).toBeInTheDocument();
  });

  it('un 404 al guardar: el aviso y de vuelta al listado', async () => {
    const { history } = await openEdit(RECEPTION_USER, ADMIN, [
      updateHandler([], RECEPTION_USER, () =>
        failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'),
      ),
      http.get('/api/users', () => success([], { meta: pagination({ total: 0 }) })),
    ]);

    save();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/usuarios');
    });
    expect(await screen.findByText('El recurso no existe.')).toBeInTheDocument();
  });
});

describe('/configuracion/usuarios/$id/editar: el propio usuario', () => {
  it('sin contraseña, con el enlace a /perfil; guardar vuelve a pedir me', async () => {
    const bodies: Record<string, unknown>[] = [];
    let meCalls = 0;
    await openEdit(ADMIN_USER, ADMIN, [
      http.get('/api/me', () => {
        meCalls += 1;

        return success(ADMIN);
      }),
      updateHandler(bodies, ADMIN_USER),
    ]);
    const before = meCalls;

    expect(hasField('Cambiar la contraseña')).toBe(false);
    expect(hasField('Contraseña nueva')).toBe(false);
    expect(screen.getByText('perfil', { selector: 'a' })).toHaveAttribute('href', '/perfil');
    type('Nombre', 'Anita');
    save();

    await waitFor(() => {
      expect(meCalls).toBeGreaterThan(before);
    });
    expect(bodies).toHaveLength(1);
    expect(bodies[0]).not.toHaveProperty('password');
  });

  it('un 422 de password (no se manda, pero si llega) va a la alerta: el campo no está', async () => {
    const message = 'Para cambiar tu contraseña usá tu perfil.';
    await openEdit(ADMIN_USER, ADMIN, [
      updateHandler([], ADMIN_USER, () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
          errors: { password: [message] },
        }),
      ),
    ]);

    save();

    expect(await screen.findByText(message)).toBeInTheDocument();
  });

  it('si su sucursal actual sale de la lista: me de nuevo y las consultas de la sucursal invalidadas', async () => {
    let current: Me = {
      ...ADMIN,
      branches: [...ADMIN.branches, { id: 11, code: 'NOR', name: 'Sucursal Norte' }],
    };
    const bodies: Record<string, unknown>[] = [];
    const user: UserDetail = {
      ...ADMIN_USER,
      branches: [...ADMIN_USER.branches, { id: 11, code: 'NOR', name: 'Sucursal Norte' }],
    };
    const { history, queryClient } = await openEdit(user, current, [
      http.get('/api/me', () => success(current)),
      updateHandler(bodies, user, () => {
        current = {
          ...current,
          current_branch: { id: 11, code: 'NOR', name: 'Sucursal Norte' },
          branches: [{ id: 11, code: 'NOR', name: 'Sucursal Norte' }],
        };

        return success(
          { ...user, branches: [user.branches[1]], current_branch_id: 11 },
          { message: 'Usuario actualizado correctamente.' },
        );
      }),
    ]);
    queryClient.setQueryData(['branch', 10, 'probe'], 'datos de CEN');

    fireEvent.click(field('CEN · Casa central'));
    save();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/usuarios/3');
    });
    expect(bodies).toEqual([expect.objectContaining({ branch_ids: [11] })]);
    await waitFor(() => {
      expect(queryClient.getQueryState(['branch', 10, 'probe'])?.isInvalidated).toBe(true);
    });
    expect(queryClient.getQueryData<Me>(['me'])?.current_branch?.id).toBe(11);
  });

  it('USER_LAST_ADMIN al quitarse el rol: la confirmación y, con el 409, la alerta arriba', async () => {
    const message = 'La clínica tiene que tener al menos un administrador activo.';
    const bodies: Record<string, unknown>[] = [];
    const { history } = await openEdit(ADMIN_USER, ADMIN, [
      updateHandler(bodies, ADMIN_USER, () => failure(409, 'USER_LAST_ADMIN', message)),
    ]);

    fireEvent.click(field('Recepcionista'));
    fireEvent.click(field('Administrador'));
    save();

    expect(
      await screen.findByText(
        'Vas a dejar de ser administrador y no vas a poder volver a esta pantalla.',
      ),
    ).toBeInTheDocument();
    expect(bodies).toEqual([]);
    fireEvent.click(dialogButton('Quitarme el rol de administrador') as HTMLElement);

    expect(await screen.findByText(message)).toBeInTheDocument();
    expect(bodies).toEqual([expect.objectContaining({ roles: ['recepcionista'] })]);
    expect(history.location.pathname).toBe('/configuracion/usuarios/3/editar');
  });

  it('quitarse el rol de administrador (con otro activo): me de nuevo y a /, sin "Configuración"', async () => {
    let current: Me = ADMIN;
    const bodies: Record<string, unknown>[] = [];
    const { history } = await openEdit(ADMIN_USER, ADMIN, [
      http.get('/api/me', () => success(current)),
      updateHandler(bodies, ADMIN_USER, () => {
        current = meFor('recepcionista');

        return success(
          { ...ADMIN_USER, roles: [testRole('recepcionista')] },
          { message: 'Usuario actualizado correctamente.' },
        );
      }),
    ]);

    fireEvent.click(field('Recepcionista'));
    fireEvent.click(field('Administrador'));
    save();
    fireEvent.click(await findDialogButton('Quitarme el rol de administrador'));

    await waitFor(() => {
      expect(history.location.pathname).toBe('/');
    });
    expect(bodies).toHaveLength(1);
    await waitFor(() => {
      expect(screen.queryByText('Configuración', { selector: '.nav-link-title' })).toBeNull();
    });
  });

  it('"Cancelar" en la confirmación no manda nada', async () => {
    const bodies: Record<string, unknown>[] = [];
    await openEdit(ADMIN_USER, ADMIN, [updateHandler(bodies, ADMIN_USER)]);

    fireEvent.click(field('Recepcionista'));
    fireEvent.click(field('Administrador'));
    save();
    fireEvent.click(await findDialogButton('Cancelar'));

    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    expect(bodies).toEqual([]);
  });
});

describe('/configuracion/usuarios/$id: inactivar, reactivar y eliminar (2.2b)', () => {
  const statusButton = () => document.querySelector<HTMLElement>('.user-status');
  const deleteButton = () => document.querySelector<HTMLElement>('.user-delete');

  /** El elemento, o el test falla acá (no en el clic). */
  function required(selector: string): HTMLElement {
    const found = document.querySelector<HTMLElement>(selector);

    if (found === null) {
      throw new Error(`Sin ${selector}`);
    }

    return found;
  }

  /** El detalle de `user` (con el administrador en la sesión), con los handlers del caso primero. */
  async function openDetail(user: UserDetail, extra: Parameters<typeof server.use> = []) {
    server.use(
      ...extra,
      meHandler(ADMIN),
      detailHandler(() => user),
    );
    const app = renderApp(`/configuracion/usuarios/${String(user.id)}`, { session: sessionFor() });
    await screen.findByText(user.name, { selector: 'h2' });

    return app;
  }

  /** PATCH users/{id} con el cuerpo parcial: guarda los cuerpos y aplica `is_active` (o responde con `respond`). */
  function statusHandler(
    bodies: Record<string, unknown>[],
    base: UserDetail,
    respond?: () => Response,
  ) {
    return http.patch('/api/users/:id', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;
      bodies.push(body);

      return (
        respond?.() ??
        success(
          { ...base, is_active: body.is_active as boolean },
          { message: 'Usuario actualizado correctamente.' },
        )
      );
    });
  }

  function deleteHandler(calls: string[], respond: () => Response) {
    return http.delete('/api/users/:id', ({ params }) => {
      calls.push(String(params.id));

      return respond();
    });
  }

  const deleted = () => success(null, { message: 'Usuario eliminado correctamente.' });
  const IN_USE_MESSAGE =
    'El usuario tiene registros a su nombre y no se puede eliminar: inactivalo.';

  it('el propio usuario no tiene "Inactivar" ni "Eliminar"; otro, sí', async () => {
    await openDetail(ADMIN_USER);

    expect(screen.getByText('(vos)')).toBeInTheDocument();
    expect(document.querySelector('.user-edit')).not.toBeNull();
    expect(statusButton()).toBeNull();
    expect(deleteButton()).toBeNull();
  });

  it('inactivar: el aviso de las sesiones, solo is_active en el cuerpo, la marca y la invalidación', async () => {
    const bodies: Record<string, unknown>[] = [];
    let current = RECEPTION_USER;
    const { queryClient } = await openDetail(RECEPTION_USER, [
      detailHandler(() => current),
      statusHandler(bodies, RECEPTION_USER, () => {
        current = { ...RECEPTION_USER, is_active: false };

        return success(current, { message: 'Usuario actualizado correctamente.' });
      }),
    ]);
    queryClient.setQueryData(['veterinarians', 'list', {}], { probe: true });
    queryClient.setQueryData(['users', 'list', { probe: true }], { probe: true });

    expect(statusButton()?.textContent).toBe('Inactivar');
    expect(deleteButton()).not.toBeNull();
    fireEvent.click(required('.user-status'));
    expect(dialog()?.textContent).toContain(
      'Se van a cerrar todas sus sesiones y no va a poder ingresar hasta que lo reactives.',
    );
    fireEvent.click(await findDialogButton('Inactivar a Rosa Benítez'));

    expect(
      await screen.findByText('Inactivado', { selector: '.user-inactive' }),
    ).toBeInTheDocument();
    expect(bodies).toEqual([{ is_active: false }]);
    expect(dialog()).toBeNull();
    expect(statusButton()?.textContent).toBe('Reactivar');
    expect(screen.getByText('Inactivado', { selector: 'dd' })).toBeInTheDocument();
    expect(queryClient.getQueryState(['veterinarians', 'list', {}])?.isInvalidated).toBe(true);
    expect(queryClient.getQueryState(['users', 'list', { probe: true }])?.isInvalidated).toBe(true);
  });

  it('reactivar: "Va a tener que ingresar de nuevo." e is_active: true', async () => {
    const inactive = { ...RECEPTION_USER, is_active: false };
    const bodies: Record<string, unknown>[] = [];
    await openDetail(inactive, [statusHandler(bodies, inactive)]);

    expect(statusButton()?.textContent).toBe('Reactivar');
    fireEvent.click(required('.user-status'));
    expect(dialog()?.textContent).toContain('Va a tener que ingresar de nuevo.');
    fireEvent.click(await findDialogButton('Reactivar a Rosa Benítez'));

    await waitFor(() => {
      expect(statusButton()?.textContent).toBe('Inactivar');
    });
    expect(bodies).toEqual([{ is_active: true }]);
    expect(document.querySelector('.user-inactive')).toBeNull();
  });

  it('dos clics seguidos en la confirmación mandan un solo pedido', async () => {
    const bodies: Record<string, unknown>[] = [];
    await openDetail(RECEPTION_USER, [statusHandler(bodies, RECEPTION_USER)]);

    fireEvent.click(required('.user-status'));
    const confirm = await findDialogButton('Inactivar a Rosa Benítez');
    fireEvent.click(confirm);
    fireEvent.click(confirm);

    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    expect(bodies).toHaveLength(1);
  });

  it.each([
    ['USER_LAST_ADMIN', 'La clínica tiene que tener al menos un administrador activo.'],
    ['USER_SELF_ACTION', 'No podés inactivarte ni darte de baja a vos mismo.'],
  ])('inactivar con %s: el mensaje en el diálogo y solo "Cerrar"', async (code, message) => {
    const bodies: Record<string, unknown>[] = [];
    await openDetail(RECEPTION_USER, [
      statusHandler(bodies, RECEPTION_USER, () => failure(409, code, message)),
    ]);

    fireEvent.click(required('.user-status'));
    fireEvent.click(await findDialogButton('Inactivar a Rosa Benítez'));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain(message);
    });
    expect(dialog()?.textContent).not.toContain('Se van a cerrar');
    expect(dialogButton('Inactivar a Rosa Benítez')).toBeNull();
    expect(dialogButton('Cerrar')).not.toBeNull();
    expect(bodies).toHaveLength(1);
  });

  it('eliminar con el 200 (doble clic): un pedido, al listado, sin pedir de nuevo el detalle ni dejarlo en la caché', async () => {
    const calls: string[] = [];
    let detailGets = 0;
    const { history, queryClient } = await openDetail(RECEPTION_USER, [
      http.get('/api/users/:id', () => {
        detailGets += 1;

        return success(RECEPTION_USER);
      }),
      deleteHandler(calls, deleted),
      http.get('/api/users', () =>
        success([userListItem(ADMIN_USER)], { meta: pagination({ total: 1 }) }),
      ),
      ...optionHandlers(),
    ]);
    queryClient.setQueryData(['veterinarians', 'list', {}], { probe: true });
    const loads = detailGets;

    fireEvent.click(required('.user-delete'));
    expect(dialog()?.textContent).toContain(
      '¿Querés eliminar a Rosa Benítez? No se puede deshacer.',
    );
    const confirm = await findDialogButton('Eliminar a Rosa Benítez');
    fireEvent.click(confirm);
    fireEvent.click(confirm);

    expect(await screen.findByText('Gómez, Ana', { selector: 'a' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/configuracion/usuarios');
    expect(calls).toEqual(['7']);
    await waitFor(() => {
      expect(queryClient.getQueryData(['users', 'detail', 7])).toBeUndefined();
    });
    // Desvío 1: el detalle sale de la caché al llegar al listado, sin volver a pedirse (sería un 404).
    expect(detailGets).toBe(loads);
    expect(queryClient.getQueryState(['veterinarians', 'list', {}])?.isInvalidated).toBe(true);
    expect(screen.queryByText('Benítez, Rosa')).toBeNull();
    expect(screen.getByText('Usuario eliminado correctamente.')).toBeInTheDocument();
  });

  it('de la baja con USER_IN_USE a inactivar con el teclado: el foco en "Cancelar", Escape cierra sin pedido y vuelve a "Eliminar"', async () => {
    const bodies: Record<string, unknown>[] = [];
    await openDetail(VET_USER, [
      deleteHandler([], () => failure(409, 'USER_IN_USE', IN_USE_MESSAGE)),
      statusHandler(bodies, VET_USER),
    ]);

    const opener = required('.user-delete');
    opener.focus();
    fireEvent.click(opener);
    fireEvent.click(await findDialogButton('Eliminar a Víctor Acosta'));
    const inactivate = await findDialogButton('Inactivar');
    fireEvent.click(inactivate);

    await waitFor(() => {
      expect(dialog()?.textContent).toContain('Se van a cerrar todas sus sesiones');
    });
    expect(document.activeElement).toBe(dialogButton('Cancelar'));

    fireEvent.keyDown(document, { key: 'Escape' });

    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    expect(document.activeElement).toBe(required('.user-delete'));
    expect(bodies).toEqual([]);
  });

  it('eliminar con USER_IN_USE: el mensaje y "Inactivar", que abre el diálogo de inactivar', async () => {
    const calls: string[] = [];
    const bodies: Record<string, unknown>[] = [];
    await openDetail(VET_USER, [
      deleteHandler(calls, () => failure(409, 'USER_IN_USE', IN_USE_MESSAGE)),
      statusHandler(bodies, VET_USER),
    ]);

    fireEvent.click(required('.user-delete'));
    fireEvent.click(await findDialogButton('Eliminar a Víctor Acosta'));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain(IN_USE_MESSAGE);
    });
    expect(dialog()?.textContent).not.toContain('¿Querés eliminar');
    expect(dialogButton('Eliminar a Víctor Acosta')).toBeNull();
    // "Inactivar" no borra: el diálogo pasa a tone="primary" (azul), no rojo.
    expect(dialogButton('Inactivar')?.className).toContain('btn-primary');
    fireEvent.click(dialogButton('Inactivar') as HTMLElement);

    await waitFor(() => {
      expect(dialog()?.textContent).toContain('Se van a cerrar todas sus sesiones');
    });
    expect(bodies).toEqual([]);
    fireEvent.click(await findDialogButton('Inactivar a Víctor Acosta'));

    expect(
      await screen.findByText('Inactivado', { selector: '.user-inactive' }),
    ).toBeInTheDocument();
    expect(bodies).toEqual([{ is_active: false }]);
    expect(calls).toEqual(['8']);
  });

  it('eliminar a un inactivo con USER_IN_USE: el mensaje y solo "Cerrar"', async () => {
    await openDetail({ ...VET_USER, is_active: false }, [
      deleteHandler([], () => failure(409, 'USER_IN_USE', IN_USE_MESSAGE)),
    ]);

    fireEvent.click(required('.user-delete'));
    fireEvent.click(await findDialogButton('Eliminar a Víctor Acosta'));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain(IN_USE_MESSAGE);
    });
    expect(dialogButton('Inactivar')).toBeNull();
    expect(dialogButton('Eliminar a Víctor Acosta')).toBeNull();
    expect(dialogButton('Cerrar')).not.toBeNull();
  });

  it.each([
    ['USER_LAST_ADMIN', 'La clínica tiene que tener al menos un administrador activo.'],
    ['USER_SELF_ACTION', 'No podés inactivarte ni darte de baja a vos mismo.'],
  ])('eliminar con %s: el mensaje y solo "Cerrar", sin "Inactivar"', async (code, message) => {
    const calls: string[] = [];
    const { history } = await openDetail(RECEPTION_USER, [
      deleteHandler(calls, () => failure(409, code, message)),
    ]);

    fireEvent.click(required('.user-delete'));
    fireEvent.click(await findDialogButton('Eliminar a Rosa Benítez'));

    await waitFor(() => {
      expect(dialog()?.textContent).toContain(message);
    });
    expect(dialogButton('Inactivar')).toBeNull();
    expect(dialogButton('Eliminar a Rosa Benítez')).toBeNull();
    expect(dialogButton('Cerrar')).not.toBeNull();
    expect(history.location.pathname).toBe('/configuracion/usuarios/7');
    expect(calls).toHaveLength(1);
  });

  it('un 404 al eliminar (ya no está): el aviso y de vuelta al listado', async () => {
    const { history } = await openDetail(RECEPTION_USER, [
      deleteHandler([], () => failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.')),
      http.get('/api/users', () => success([], { meta: pagination({ total: 0 }) })),
      ...optionHandlers(),
    ]);

    fireEvent.click(required('.user-delete'));
    fireEvent.click(await findDialogButton('Eliminar a Rosa Benítez'));

    expect(await screen.findByText('El recurso no existe.')).toBeInTheDocument();
    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/usuarios');
    });
  });

  it('ida y vuelta: inactivado, sale del listado por defecto y está con "Incluir los inactivos"', async () => {
    let current = RECEPTION_USER;
    await openDetail(RECEPTION_USER, [
      detailHandler(() => current),
      statusHandler([], RECEPTION_USER, () => {
        current = { ...RECEPTION_USER, is_active: false };

        return success(current, { message: 'Usuario actualizado correctamente.' });
      }),
      http.get('/api/users', ({ request }) => {
        const includeInactive = new URL(request.url).searchParams.get('include_inactive') === '1';
        const items = [ADMIN_USER, current]
          .filter((user) => includeInactive || user.is_active)
          .map(userListItem);

        return success(items, { meta: pagination({ total: items.length }) });
      }),
      ...optionHandlers(),
    ]);

    fireEvent.click(required('.user-status'));
    fireEvent.click(await findDialogButton('Inactivar a Rosa Benítez'));
    await screen.findByText('Inactivado', { selector: '.user-inactive' });

    fireEvent.click(screen.getByText('Usuarios', { selector: 'a' }));
    expect(await screen.findByText('Gómez, Ana', { selector: 'a' })).toBeInTheDocument();
    expect(screen.queryByText('Benítez, Rosa', { selector: 'a' })).toBeNull();

    fireEvent.click(field('Incluir los inactivos'));
    expect(await screen.findByText('Benítez, Rosa', { selector: 'a' })).toBeInTheDocument();
    expect(screen.getByText('Inactivado', { selector: '.user-inactive' })).toBeInTheDocument();
  });
});
