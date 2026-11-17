import { fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { pagination } from '../../test/clients';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { serviceListItem, SURGERY, VACCINE } from '../../test/services';
import { SERVICE_MESSAGES } from './form';
import type { ServiceDetail } from './types';

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

function required(selector: string): HTMLElement {
  const element = document.querySelector<HTMLElement>(selector);

  if (element === null) {
    throw new Error(`Sin ${selector}`);
  }

  return element;
}

/** Intl separa "Gs." del importe con un espacio duro. */
const plain = (text: string | null | undefined) => (text ?? '').replace(/\s/g, ' ');

const ADMIN = meFor('administrador');
const RECEPTION = meFor('recepcionista');
const IN_USE_MESSAGE = 'El servicio ya se usó y no se puede eliminar: inactivalo.';
const NAME_LOCKED_MESSAGE =
  'El servicio ya se usó y no puede cambiar de nombre: creá otro e inactivá este.';

function listHandler(urls: URL[] = [], items = [VACCINE, SURGERY]) {
  return http.get('/api/services', ({ request }) => {
    urls.push(new URL(request.url));

    return success(items.map(serviceListItem), { meta: pagination({ total: items.length }) });
  });
}

function detailHandler(current: () => ServiceDetail) {
  return http.get('/api/services/:id', ({ params }) =>
    String(params.id) === String(current().id)
      ? success(current())
      : failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'),
  );
}

async function openDetail(
  service: ServiceDetail,
  extra: Parameters<typeof server.use> = [],
  me = ADMIN,
) {
  server.use(
    ...extra,
    meHandler(me),
    detailHandler(() => service),
  );
  const app = renderApp(`/servicios/${String(service.id)}`, { session: sessionFor() });
  await screen.findByText(`${service.code} · ${service.name}`, { selector: '.service-title' });

  return app;
}

describe('/servicios: listado', () => {
  it('el administrador: la tabla con el precio en guaraníes, el enlace y "Nuevo servicio"; categoría y estado en la URL y en el pedido', async () => {
    const urls: URL[] = [];
    server.use(meHandler(ADMIN), listHandler(urls));
    const { history } = renderApp('/servicios', { session: sessionFor() });

    expect(await screen.findByText('Vacuna 5+1', { selector: 'a' })).toHaveAttribute(
      'href',
      '/servicios/3',
    );
    const prices = Array.from(document.querySelectorAll('.service-price')).map((cell) =>
      plain(cell.textContent),
    );
    expect(prices).toEqual(['Gs. 150.000', 'Gs. 999.999.999.999']);
    expect(screen.getByText('30 min', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('10 %', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('Vacunación', { selector: 'td' })).toBeInTheDocument();
    expect(screen.getByText('Nuevo servicio', { selector: 'a' })).toBeInTheDocument();
    // Por defecto, solo los activos: ni status ni include_inactive.
    expect(urls[0]?.searchParams.has('status')).toBe(false);
    expect(urls[0]?.searchParams.has('include_inactive')).toBe(false);
    expect(urls[0]?.searchParams.get('per_page')).toBe('20');

    fireEvent.change(screen.getByLabelText('Categoría'), { target: { value: 'surgery' } });
    await waitFor(() => {
      expect(urls.at(-1)?.searchParams.get('category')).toBe('surgery');
    });
    expect(history.location.search).toContain('categoria=surgery');

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
  });

  it.each(['recepcionista', 'veterinario'] as const)(
    '%s: el listado con el enlace al detalle y sin "Nuevo servicio"',
    async (role) => {
      server.use(meHandler(meFor(role)), listHandler());
      renderApp('/servicios', { session: sessionFor() });

      expect(await screen.findByText('Vacuna 5+1', { selector: 'a' })).toBeInTheDocument();
      expect(screen.queryByText('Nuevo servicio')).toBeNull();
    },
  );

  it('una categoría o un estado desconocidos en la URL se descartan', async () => {
    const urls: URL[] = [];
    server.use(meHandler(ADMIN), listHandler(urls, []));
    renderApp('/servicios?categoria=dentistry&estado=borrados', { session: sessionFor() });

    expect(await screen.findByText('Todavía no hay servicios.')).toBeInTheDocument();
    expect(urls[0]?.searchParams.has('category')).toBe(false);
    expect(urls[0]?.searchParams.has('status')).toBe(false);
  });
});

describe('/servicios/$id: detalle', () => {
  it('el administrador: los datos, el precio en guaraníes y las acciones', async () => {
    await openDetail(VACCINE);

    expect(plain(screen.getByText(/Gs\./, { selector: 'dd' }).textContent)).toBe('Gs. 150.000');
    expect(screen.getByText('Séxtuple para perros.', { selector: 'dd' })).toBeInTheDocument();
    expect(required('.service-edit')).toHaveAttribute('href', '/servicios/3/editar');
    expect(required('.service-status-action').textContent).toBe('Inactivar');
    expect(required('.service-delete').textContent).toBe('Eliminar');
  });

  it.each(['recepcionista', 'veterinario'] as const)(
    '%s ve el detalle sin acciones',
    async (role) => {
      await openDetail(VACCINE, [], meFor(role));

      expect(screen.getByText('Vacunación', { selector: 'dd' })).toBeInTheDocument();
      expect(document.querySelector('.service-edit')).toBeNull();
      expect(document.querySelector('.service-status-action')).toBeNull();
      expect(document.querySelector('.service-delete')).toBeNull();
    },
  );

  it('un id que no existe: la página de no encontrado', async () => {
    server.use(
      meHandler(ADMIN),
      detailHandler(() => VACCINE),
    );
    renderApp('/servicios/99', { session: sessionFor() });

    expect(await screen.findByText('No encontramos este servicio')).toBeInTheDocument();
  });
});

describe('/servicios/nuevo: alta', () => {
  it('"150.000" se manda como "150000" (texto), con la duración y el IVA por defecto como números; al detalle con el código', async () => {
    const bodies: unknown[] = [];
    const created: ServiceDetail = {
      ...VACCINE,
      id: 7,
      code: 'SRV-0007',
      name: 'Consulta general',
    };
    server.use(
      meHandler(ADMIN),
      http.post('/api/services', async ({ request }) => {
        bodies.push(await request.json());

        return success(created, { status: 201, message: 'Servicio creado correctamente.' });
      }),
      detailHandler(() => created),
    );
    const { history, queryClient } = renderApp('/servicios/nuevo', { session: sessionFor() });
    queryClient.setQueryData(['services', 'list', { marker: true }], { items: [] });

    await screen.findByText('Nuevo servicio', { selector: 'h2' });
    type('Nombre', ' Consulta general ');
    type('Categoría', 'consultation');
    type('Precio (Gs.)', '150.000');
    // Doble clic: un solo pedido.
    save();
    save();

    expect(await screen.findByText('SRV-0007 · Consulta general')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/servicios/7');
    expect(bodies).toEqual([
      {
        name: 'Consulta general',
        category: 'consultation',
        duration_minutes: 30,
        standard_price: '150000',
        tax_rate: 10,
        description: null,
      },
    ]);
    expect(queryClient.getQueryState(['services', 'list', { marker: true }])?.isInvalidated).toBe(
      true,
    );
  });

  it.each([['150000,50'], ['150.000,00'], ['-1'], ['1e5'], ['1.000.000.000.000']])(
    'el precio %j: el mensaje de WholeAmount en su campo, sin pedido',
    async (price) => {
      const bodies: unknown[] = [];
      server.use(
        meHandler(ADMIN),
        http.post('/api/services', () => {
          bodies.push(1);

          return success(VACCINE, { status: 201 });
        }),
      );
      renderApp('/servicios/nuevo', { session: sessionFor() });

      await screen.findByText('Nuevo servicio', { selector: 'h2' });
      type('Nombre', 'Consulta general');
      type('Categoría', 'consultation');
      type('Precio (Gs.)', price);
      save();

      await waitFor(() => {
        expect(fieldError('Precio (Gs.)')).toBe(SERVICE_MESSAGES.standard_price.whole);
      });
      expect(bodies).toEqual([]);
    },
  );

  it('el nombre con "//" y la duración de 722: sus mensajes, sin pedido', async () => {
    server.use(meHandler(ADMIN));
    renderApp('/servicios/nuevo', { session: sessionFor() });

    await screen.findByText('Nuevo servicio', { selector: 'h2' });
    type('Nombre', 'a//b');
    type('Categoría', 'consultation');
    type('Precio (Gs.)', '1000');
    type('Duración (minutos)', '722');
    save();

    await waitFor(() => {
      expect(fieldError('Nombre')).toBe(SERVICE_MESSAGES.name.regex);
    });
    expect(fieldError('Duración (minutos)')).toBe(SERVICE_MESSAGES.duration_minutes.between);
  });

  it('422 del nombre repetido: el mensaje de la API en su campo', async () => {
    server.use(
      meHandler(ADMIN),
      http.post('/api/services', () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
          errors: { name: ['Ya hay un servicio con ese nombre.'] },
        }),
      ),
    );
    renderApp('/servicios/nuevo', { session: sessionFor() });

    await screen.findByText('Nuevo servicio', { selector: 'h2' });
    type('Nombre', 'Vacuna 5+1');
    type('Categoría', 'vaccination');
    type('Precio (Gs.)', '150.000');
    save();

    await waitFor(() => {
      expect(fieldError('Nombre')).toBe('Ya hay un servicio con ese nombre.');
    });
  });

  it('el recepcionista en /servicios/nuevo va a /sin-permiso', async () => {
    server.use(meHandler(RECEPTION));
    const { history } = renderApp('/servicios/nuevo', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/sin-permiso');
  });
});

describe('/servicios/$id/editar', () => {
  it('el precio de la API se muestra "150.000"; el cuerpo completo sin code ni status', async () => {
    const bodies: unknown[] = [];
    server.use(
      meHandler(ADMIN),
      detailHandler(() => VACCINE),
      http.patch('/api/services/:id', async ({ request }) => {
        bodies.push(await request.json());

        return success(
          { ...VACCINE, standard_price: '175000.00' },
          { message: 'Servicio actualizado correctamente.' },
        );
      }),
    );
    const { history } = renderApp('/servicios/3/editar', { session: sessionFor() });

    await screen.findByText('Editar el servicio', { selector: 'h2' });
    expect(field('Precio (Gs.)').value).toBe('150.000');
    expect(screen.getByText('SRV-0001', { selector: '.service-fixed dd' })).toBeInTheDocument();
    type('Precio (Gs.)', '175.000');
    fireEvent.click(required('input[name="tax_rate"][value="5"]'));
    save();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/servicios/3');
    });
    expect(bodies).toEqual([
      {
        name: 'Vacuna 5+1',
        category: 'vaccination',
        duration_minutes: 30,
        standard_price: '175000',
        tax_rate: 5,
        description: 'Séxtuple para perros.',
      },
    ]);
  });

  it('SERVICE_NAME_NOT_EDITABLE: el mensaje de la API en el nombre', async () => {
    server.use(
      meHandler(ADMIN),
      detailHandler(() => VACCINE),
      http.patch('/api/services/:id', () =>
        failure(422, 'SERVICE_NAME_NOT_EDITABLE', NAME_LOCKED_MESSAGE),
      ),
    );
    renderApp('/servicios/3/editar', { session: sessionFor() });

    await screen.findByText('Editar el servicio', { selector: 'h2' });
    type('Nombre', 'Vacuna 6+1');
    save();

    await waitFor(() => {
      expect(fieldError('Nombre')).toBe(NAME_LOCKED_MESSAGE);
    });
    expect(document.activeElement).toBe(field('Nombre'));
  });
});

describe('/servicios/$id: inactivar, activar y eliminar', () => {
  function statusHandler(bodies: unknown[], base: ServiceDetail) {
    return http.patch('/api/services/:id', async ({ request }) => {
      const body = (await request.json()) as { status: 'active' | 'inactive' };
      bodies.push(body);

      return success(
        {
          ...base,
          status: { value: body.status, label: body.status === 'active' ? 'Activo' : 'Inactivo' },
        },
        { message: 'Servicio actualizado correctamente.' },
      );
    });
  }

  it('inactivar: el cuerpo solo con el estado y la marca nueva', async () => {
    const bodies: unknown[] = [];
    await openDetail(VACCINE, [statusHandler(bodies, VACCINE)]);

    fireEvent.click(required('.service-status-action'));
    fireEvent.click(await findDialogButton('Inactivar Vacuna 5+1'));

    await waitFor(() => {
      expect(required('.service-status-action').textContent).toBe('Activar');
    });
    expect(bodies).toEqual([{ status: 'inactive' }]);
  });

  it('activar uno inactivo: { status: active }', async () => {
    const bodies: unknown[] = [];
    await openDetail(SURGERY, [statusHandler(bodies, SURGERY)]);

    fireEvent.click(required('.service-status-action'));
    fireEvent.click(await findDialogButton('Activar Castración macho/hembra'));

    await waitFor(() => {
      expect(bodies).toEqual([{ status: 'active' }]);
    });
  });

  it('eliminar con el 200: al listado, con el aviso', async () => {
    const calls: string[] = [];
    const { history } = await openDetail(VACCINE, [
      http.delete('/api/services/:id', ({ params }) => {
        calls.push(String(params.id));

        return success(null, { message: 'Servicio eliminado correctamente.' });
      }),
      listHandler([], [SURGERY]),
    ]);

    fireEvent.click(required('.service-delete'));
    fireEvent.click(await findDialogButton('Eliminar Vacuna 5+1'));

    await waitFor(() => {
      expect(history.location.pathname).toBe('/servicios');
    });
    expect(await screen.findByText('Servicio eliminado correctamente.')).toBeInTheDocument();
    expect(calls).toEqual(['3']);
  });

  it('eliminar con SERVICE_IN_USE: el mensaje y "Inactivar", que abre el diálogo de inactivar', async () => {
    await openDetail(VACCINE, [
      http.delete('/api/services/:id', () => failure(409, 'SERVICE_IN_USE', IN_USE_MESSAGE)),
    ]);

    fireEvent.click(required('.service-delete'));
    fireEvent.click(await findDialogButton('Eliminar Vacuna 5+1'));

    fireEvent.click(await findDialogButton('Inactivar'));
    expect(screen.queryByText(IN_USE_MESSAGE)).toBeNull();
    expect(await findDialogButton('Inactivar Vacuna 5+1')).toBeInTheDocument();
  });

  it('SERVICE_IN_USE en uno ya inactivo: el mensaje y solo "Cerrar"', async () => {
    await openDetail(SURGERY, [
      http.delete('/api/services/:id', () => failure(409, 'SERVICE_IN_USE', IN_USE_MESSAGE)),
    ]);

    fireEvent.click(required('.service-delete'));
    fireEvent.click(await findDialogButton('Eliminar Castración macho/hembra'));

    expect(await screen.findByText(IN_USE_MESSAGE)).toBeInTheDocument();
    expect(await findDialogButton('Cerrar')).toBeInTheDocument();
    expect(dialog()?.textContent).not.toContain('¿Querés eliminar');
  });

  it('inactivar uno que otra pestaña eliminó (404): el aviso y de vuelta al listado', async () => {
    const { history } = await openDetail(VACCINE, [
      http.patch('/api/services/:id', () =>
        failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'),
      ),
      listHandler([], [SURGERY]),
    ]);

    fireEvent.click(required('.service-status-action'));
    fireEvent.click(await findDialogButton('Inactivar Vacuna 5+1'));

    await waitFor(() => {
      expect(history.location.pathname).toBe('/servicios');
    });
    expect(await screen.findByText('El recurso no existe.')).toBeInTheDocument();
  });
});
