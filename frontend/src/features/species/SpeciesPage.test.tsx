import { fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { meFor, ROLE_PERMISSIONS, type Role } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { Species } from '../patients/types';

/**
 * /especies (1.5): el catálogo con sus razas; por defecto, solo las activas, y "Incluir las inactivas" (`?inactivas=1`) para
 * quien tiene species.update; las acciones solo en las propias y con los permisos de especie (administrador y veterinario);
 * el recepcionista solo lista las activas.
 */

const filterBox = () =>
  document.querySelector<HTMLInputElement>('.species-inactive-filter input[type="checkbox"]');

/** Un clic en "Incluir las inactivas"; falla si no está. */
function clickFilter() {
  const box = filterBox();

  if (box === null) {
    throw new Error('No está "Incluir las inactivas".');
  }

  fireEvent.click(box);
}

/** Canino es común, con una raza común y una propia; Pez es propia; Exótico, propia e inactiva (solo con include_inactive). */
function catalog(includeInactive: boolean): Species[] {
  const all: Species[] = [
    {
      id: 1,
      name: 'Canino',
      is_active: true,
      is_global: true,
      breeds: [
        { id: 2, species_id: 1, name: 'Mestizo', is_active: true, is_global: true },
        { id: 7, species_id: 1, name: 'Ovejero Chaqueño', is_active: true, is_global: false },
      ],
    },
    { id: 20, name: 'Pez', is_active: true, is_global: false, breeds: [] },
    { id: 21, name: 'Exótico', is_active: false, is_global: false, breeds: [] },
  ];

  return includeInactive ? all : all.filter((species) => species.is_active);
}

/** GET species: cuenta los pedidos y anota si llevaron include_inactive. */
function catalogHandler() {
  const calls: (string | null)[] = [];
  server.use(
    http.get('/api/species', ({ request }) => {
      const includeInactive = new URL(request.url).searchParams.get('include_inactive');
      calls.push(includeInactive);

      return success(catalog(includeInactive === '1'));
    }),
  );

  return calls;
}

/** Con las inactivas: `?inactivas=1` (solo lo usa quien tiene species.update). */
const WITH_INACTIVE = '/especies?inactivas=1';

async function openPage(
  role: Role = 'administrador',
  permissions?: string[],
  url = '/especies',
) {
  const calls = catalogHandler();
  server.use(meHandler(meFor(role, permissions === undefined ? {} : { permissions })));
  const rendered = renderApp(url, { session: sessionFor() });
  await screen.findByText('Canino', { selector: 'button' });

  return { ...rendered, calls };
}

function speciesItem(name: string): HTMLElement {
  const item = document.querySelector<HTMLElement>(`.species-item[data-species="${name}"]`);

  if (item === null) {
    throw new Error(`No está la especie ${name}.`);
  }

  return item;
}

function breedItem(name: string): HTMLElement {
  const item = document.querySelector<HTMLElement>(`.breed-item[data-breed="${name}"]`);

  if (item === null) {
    throw new Error(`No está la raza ${name}.`);
  }

  return item;
}

/** Un clic en el elemento del selector dentro del ítem; falla si no está. */
function clickIn(item: HTMLElement, selector: string) {
  const element = item.querySelector<HTMLElement>(selector);

  if (element === null) {
    throw new Error(`No está ${selector}.`);
  }

  fireEvent.click(element);
}

function expand(name: string) {
  clickIn(speciesItem(name), '.species-expand');
}

const dialog = () => document.querySelector('[role="dialog"]');

function dialogButton(text: string): HTMLElement {
  return screen.getByText(text, { selector: '[role="dialog"] button' });
}

function nameInput(): HTMLInputElement {
  const input = document.querySelector<HTMLInputElement>('[role="dialog"] input[name="name"]');

  if (input === null) {
    throw new Error('No está el campo del nombre.');
  }

  return input;
}

describe('/especies (1.5)', () => {
  it('el recepcionista solo lista las activas, sin acciones ni "Incluir las inactivas" (ignora ?inactivas=1)', async () => {
    const { calls } = await openPage('recepcionista', undefined, WITH_INACTIVE);

    expect(calls).toEqual([null]);
    expect(filterBox()).toBeNull();
    expect(document.querySelector('.species-item[data-species="Exótico"]')).toBeNull();
    expect(screen.queryByText('Nueva especie')).not.toBeInTheDocument();
    expand('Canino');
    expect(breedItem('Mestizo')).toBeInTheDocument();
    expect(
      document.querySelector('.species-rename, .species-delete, .breed-rename, .breed-new'),
    ).toBeNull();
  });

  it.each(['administrador', 'veterinario'] as const)(
    '%s: por defecto solo las activas; "Incluir las inactivas" en la URL; "Común" sin acciones, "Propia" con renombrar, inactivar y eliminar',
    async (role) => {
      const { calls, history } = await openPage(role);

      expect(calls).toEqual([null]);
      expect(document.querySelector('.species-item[data-species="Exótico"]')).toBeNull();
      expect(filterBox()).not.toBeChecked();

      clickFilter();
      expect(await screen.findByText('Exótico', { selector: 'button' })).toBeInTheDocument();
      expect(history.location.search).toBe('?inactivas=1');
      expect(calls).toEqual([null, '1']);
      expect(filterBox()).toBeChecked();
      expect(speciesItem('Exótico')).toHaveTextContent('Inactivada');
      expect(speciesItem('Canino')).toHaveTextContent('Común');
      expect(speciesItem('Canino').querySelector('.species-rename, .species-delete')).toBeNull();
      expect(speciesItem('Pez')).toHaveTextContent('Propia');
      expect(speciesItem('Pez').querySelector('.species-rename')).not.toBeNull();
      expect(speciesItem('Pez').querySelector('.species-toggle-active')).toHaveTextContent(
        'Inactivar',
      );
      expect(speciesItem('Exótico').querySelector('.species-toggle-active')).toHaveTextContent(
        'Activar',
      );
      expect(speciesItem('Pez').querySelector('.species-delete')).not.toBeNull();

      expand('Canino');
      expect(breedItem('Mestizo').querySelector('button')).toBeNull();
      expect(breedItem('Ovejero Chaqueño').querySelector('.breed-rename')).not.toBeNull();
      // Una raza propia puede ir en una especie común.
      expect(speciesItem('Canino').querySelector('.breed-new')).not.toBeNull();

      // Sin la casilla, de vuelta al valor por defecto (sin el parámetro en la URL).
      clickFilter();
      await waitFor(() => {
        expect(document.querySelector('.species-item[data-species="Exótico"]')).toBeNull();
      });
      expect(history.location.search).toBe('');
    },
  );

  it('la lista se despliega con aria-expanded', async () => {
    await openPage();
    const button = speciesItem('Canino').querySelector('.species-expand');

    expect(button).toHaveAttribute('aria-expanded', 'false');
    expect(document.querySelector('.breed-item')).toBeNull();
    expand('Canino');
    expect(button).toHaveAttribute('aria-expanded', 'true');
    expect(breedItem('Mestizo')).toBeInTheDocument();
  });

  it('nueva especie: el nombre se controla con los mensajes de la API, sin pedido', async () => {
    let posts = 0;
    server.use(
      http.post('/api/species', () => {
        posts += 1;

        return success({}, { status: 201 });
      }),
    );
    await openPage();
    fireEvent.click(screen.getByText('Nueva especie', { selector: 'button' }));

    fireEvent.change(nameInput(), { target: { value: '12' } });
    fireEvent.click(dialogButton('Crear la especie'));

    expect(
      await screen.findByText(
        'El nombre de la especie debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.',
      ),
    ).toBeInTheDocument();
    expect(nameInput()).toHaveAttribute('aria-invalid', 'true');
    expect(posts).toBe(0);
  });

  it('nueva especie: el nombre repetido (422 de la API) va al campo', async () => {
    server.use(
      http.post('/api/species', () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', {
          errors: { name: ['Ya existe una especie con ese nombre.'] },
        }),
      ),
    );
    await openPage();
    fireEvent.click(screen.getByText('Nueva especie', { selector: 'button' }));
    fireEvent.change(nameInput(), { target: { value: 'canino' } });
    fireEvent.click(dialogButton('Crear la especie'));

    expect(
      await screen.findByText('Ya existe una especie con ese nombre.', {
        selector: '.invalid-feedback',
      }),
    ).toBeInTheDocument();
    expect(dialog()).not.toBeNull();
  });

  it('nueva especie: manda el nombre sin los espacios de los extremos, avisa y vuelve a pedir el catálogo; los pacientes quedan viejos', async () => {
    let body: unknown;
    server.use(
      http.post('/api/species', async ({ request }) => {
        body = await request.json();

        return success(
          { id: 30, name: 'Hurón', is_active: true, is_global: false, breeds: [] },
          { status: 201, message: 'Especie creada correctamente.' },
        );
      }),
    );
    const { calls, queryClient } = await openPage();
    queryClient.setQueryData(['patients', 'detail', 1], { id: 1 });
    fireEvent.click(screen.getByText('Nueva especie', { selector: 'button' }));
    fireEvent.change(nameInput(), { target: { value: '  Hurón ' } });
    fireEvent.click(dialogButton('Crear la especie'));

    expect(await screen.findByText('Especie creada correctamente.')).toBeInTheDocument();
    expect(body).toEqual({ name: 'Hurón' });
    expect(dialog()).toBeNull();
    await waitFor(() => {
      expect(calls).toHaveLength(2);
    });
    expect(queryClient.getQueryState(['patients', 'detail', 1])?.isInvalidated).toBe(true);
  });

  it('un doble clic o dos Enter mandan un solo pedido', async () => {
    let posts = 0;
    let release: () => void = () => undefined;
    const answer = new Promise<void>((resolve) => {
      release = resolve;
    });
    server.use(
      http.post('/api/species', async () => {
        posts += 1;
        await answer;

        return success({}, { status: 201 });
      }),
    );
    await openPage();
    fireEvent.click(screen.getByText('Nueva especie', { selector: 'button' }));
    fireEvent.change(nameInput(), { target: { value: 'Hurón' } });

    fireEvent.click(dialogButton('Crear la especie'));
    fireEvent.click(dialogButton('Crear la especie'));
    const form = document.querySelector('.catalog-name-form');

    if (form !== null) {
      fireEvent.submit(form);
    }

    await waitFor(() => {
      expect(posts).toBe(1);
    });
    release();
    await waitFor(() => {
      expect(dialog()).toBeNull();
    });
    expect(posts).toBe(1);
  });

  it('nueva raza en una especie común: POST species/1/breeds', async () => {
    let url = '';
    server.use(
      http.post('/api/species/:id/breeds', ({ request }) => {
        url = new URL(request.url).pathname;

        return success({}, { status: 201, message: 'Raza creada correctamente.' });
      }),
    );
    await openPage('veterinario');
    expand('Canino');
    clickIn(speciesItem('Canino'), '.breed-new');

    expect(dialog()).toHaveTextContent('La raza nueva va en Canino.');
    fireEvent.change(nameInput(), { target: { value: 'Cimarrón' } });
    fireEvent.click(dialogButton('Crear la raza'));

    expect(await screen.findByText('Raza creada correctamente.')).toBeInTheDocument();
    expect(url).toBe('/api/species/1/breeds');
  });

  it('renombrar una raza propia: PATCH species/1/breeds/7 con el nombre, partiendo del guardado', async () => {
    let request: { url: string; body: unknown } | null = null;
    server.use(
      http.patch('/api/species/:species/breeds/:breed', async ({ request: received }) => {
        request = { url: new URL(received.url).pathname, body: await received.json() };

        return success({}, { message: 'Raza actualizada correctamente.' });
      }),
    );
    await openPage();
    expand('Canino');
    clickIn(breedItem('Ovejero Chaqueño'), '.breed-rename');

    expect(nameInput()).toHaveValue('Ovejero Chaqueño');
    fireEvent.change(nameInput(), { target: { value: 'Ovejero del Chaco' } });
    fireEvent.click(dialogButton('Guardar el nombre'));

    expect(await screen.findByText('Raza actualizada correctamente.')).toBeInTheDocument();
    expect(request).toEqual({
      url: '/api/species/1/breeds/7',
      body: { name: 'Ovejero del Chaco' },
    });
  });

  it('inactivar y activar: PATCH con is_active, sin confirmación', async () => {
    const bodies: unknown[] = [];
    server.use(
      http.patch('/api/species/:id', async ({ request }) => {
        bodies.push(await request.json());

        return success({}, { message: 'Especie actualizada correctamente.' });
      }),
    );
    await openPage('administrador', undefined, WITH_INACTIVE);

    clickIn(speciesItem('Pez'), '.species-toggle-active');
    expect(await screen.findByText('Especie actualizada correctamente.')).toBeInTheDocument();
    clickIn(speciesItem('Exótico'), '.species-toggle-active');

    await waitFor(() => {
      expect(bodies).toEqual([{ is_active: false }, { is_active: true }]);
    });
  });

  it('eliminar una especie propia: con confirmación, DELETE y el aviso', async () => {
    let deleted = '';
    server.use(
      http.delete('/api/species/:id', ({ params }) => {
        deleted = String(params.id);

        return success(null, { message: 'Especie eliminada correctamente.' });
      }),
    );
    await openPage();
    clickIn(speciesItem('Pez'), '.species-delete');

    expect(dialog()).toHaveTextContent('¿Querés eliminar la especie Pez?');
    fireEvent.click(dialogButton('Eliminar Pez'));

    expect(await screen.findByText('Especie eliminada correctamente.')).toBeInTheDocument();
    expect(deleted).toBe('20');
    expect(dialog()).toBeNull();
  });

  it('eliminar con SPECIES_IN_USE: el mensaje y "Inactivar", que la inactiva desde el diálogo', async () => {
    const patches: unknown[] = [];
    server.use(
      http.delete('/api/species/:id', () =>
        failure(
          409,
          'SPECIES_IN_USE',
          'La especie está asignada a pacientes y no puede eliminarse. Podés inactivarla.',
        ),
      ),
      http.patch('/api/species/:id', async ({ request }) => {
        patches.push(await request.json());

        return success({}, { message: 'Especie actualizada correctamente.' });
      }),
    );
    await openPage();
    clickIn(speciesItem('Pez'), '.species-delete');
    expect(dialog()).toHaveTextContent('¿Querés eliminar la especie Pez?');
    fireEvent.click(dialogButton('Eliminar Pez'));

    expect(await screen.findByRole('dialog')).toHaveTextContent(
      'La especie está asignada a pacientes y no puede eliminarse. Podés inactivarla.',
    );
    // Ya no se elimina: sin la pregunta ni "No se puede deshacer", solo el mensaje de la API.
    expect(dialog()).not.toHaveTextContent('¿Querés eliminar');
    expect(dialog()).not.toHaveTextContent('No se puede deshacer');
    fireEvent.click(
      await screen.findByText('Inactivar Pez', { selector: '[role="dialog"] button' }),
    );

    expect(await screen.findByText('Especie actualizada correctamente.')).toBeInTheDocument();
    expect(patches).toEqual([{ is_active: false }]);
    expect(dialog()).toBeNull();
  });

  it('BREED_IN_USE de una raza ya inactiva, o sin species.update: solo "Cerrar"', async () => {
    server.use(
      http.delete('/api/species/:id', () =>
        failure(
          409,
          'SPECIES_IN_USE',
          'La especie está asignada a pacientes y no puede eliminarse. Podés inactivarla.',
        ),
      ),
    );
    await openPage('administrador', undefined, WITH_INACTIVE);
    clickIn(speciesItem('Exótico'), '.species-delete');
    fireEvent.click(dialogButton('Eliminar Exótico'));

    expect(
      await screen.findByText('Cerrar', { selector: '[role="dialog"] button' }),
    ).toBeInTheDocument();
    expect(screen.queryByText('Inactivar Exótico')).not.toBeInTheDocument();
    expect(screen.queryByText('Eliminar Exótico', { selector: 'button' })).not.toBeInTheDocument();
  });

  it('renombrar una especie propia: PATCH species/20 con el nombre', async () => {
    let request: { url: string; body: unknown } | null = null;
    server.use(
      http.patch('/api/species/:id', async ({ request: received }) => {
        request = { url: new URL(received.url).pathname, body: await received.json() };

        return success({}, { message: 'Especie actualizada correctamente.' });
      }),
    );
    await openPage();
    clickIn(speciesItem('Pez'), '.species-rename');

    expect(nameInput()).toHaveValue('Pez');
    fireEvent.change(nameInput(), { target: { value: 'Peces' } });
    fireEvent.click(dialogButton('Guardar el nombre'));

    expect(await screen.findByText('Especie actualizada correctamente.')).toBeInTheDocument();
    expect(request).toEqual({ url: '/api/species/20', body: { name: 'Peces' } });
    expect(dialog()).toBeNull();
  });

  it('eliminar una raza con BREED_IN_USE: "Inactivar" la inactiva por la ruta anidada', async () => {
    const patches: { url: string; body: unknown }[] = [];
    server.use(
      http.delete('/api/species/:species/breeds/:breed', () =>
        failure(
          409,
          'BREED_IN_USE',
          'La raza está asignada a pacientes y no puede eliminarse. Podés inactivarla.',
        ),
      ),
      http.patch('/api/species/:species/breeds/:breed', async ({ request }) => {
        patches.push({ url: new URL(request.url).pathname, body: await request.json() });

        return success({}, { message: 'Raza actualizada correctamente.' });
      }),
    );
    await openPage();
    expand('Canino');
    clickIn(breedItem('Ovejero Chaqueño'), '.breed-delete');
    fireEvent.click(dialogButton('Eliminar Ovejero Chaqueño'));

    expect(await screen.findByRole('dialog')).toHaveTextContent(
      'La raza está asignada a pacientes y no puede eliminarse. Podés inactivarla.',
    );
    fireEvent.click(
      await screen.findByText('Inactivar Ovejero Chaqueño', {
        selector: '[role="dialog"] button',
      }),
    );

    expect(await screen.findByText('Raza actualizada correctamente.')).toBeInTheDocument();
    expect(patches).toEqual([{ url: '/api/species/1/breeds/7', body: { is_active: false } }]);
    expect(dialog()).toBeNull();
  });

  it('inactivar vacía la lista de los selectores del paciente (no está a la vista): el alta espera la nueva', async () => {
    server.use(
      http.patch('/api/species/:id', () =>
        success({}, { message: 'Especie actualizada correctamente.' }),
      ),
    );
    // Con las inactivas: la lista a la vista no es la de los selectores.
    const { calls, queryClient } = await openPage('administrador', undefined, WITH_INACTIVE);
    const selectorKey = ['species', { include_inactive: false }];
    queryClient.setQueryData(selectorKey, catalog(false));

    clickIn(speciesItem('Pez'), '.species-toggle-active');

    expect(await screen.findByText('Especie actualizada correctamente.')).toBeInTheDocument();
    // Vaciada, no solo vieja: sin datos, el alta del paciente espera el pedido en lugar de mostrar la lista anterior.
    expect(queryClient.getQueryData(selectorKey)).toBeUndefined();
    // El catálogo a la vista, en cambio, se vuelve a pedir.
    await waitFor(() => {
      expect(calls).toEqual(['1', '1']);
    });
  });

  it('un 404 al inactivar (otra persona la borró): aviso, el catálogo de nuevo y la lista de los selectores vaciada', async () => {
    server.use(
      http.patch('/api/species/:id', () =>
        failure(404, 'RESOURCE_NOT_FOUND', 'El recurso solicitado no existe.'),
      ),
    );
    const { calls, queryClient } = await openPage('administrador', undefined, WITH_INACTIVE);
    const selectorKey = ['species', { include_inactive: false }];
    queryClient.setQueryData(selectorKey, catalog(false));

    clickIn(speciesItem('Pez'), '.species-toggle-active');

    expect(await screen.findByText('El recurso solicitado no existe.')).toBeInTheDocument();
    expect(queryClient.getQueryData(selectorKey)).toBeUndefined();
    await waitFor(() => {
      expect(calls).toEqual(['1', '1']);
    });
  });

  it('CATALOG_GLOBAL_NOT_EDITABLE (403): el mensaje en el diálogo, no la página de sin permiso', async () => {
    server.use(
      http.patch('/api/species/:id', () =>
        failure(
          403,
          'CATALOG_GLOBAL_NOT_EDITABLE',
          'Este registro es del catálogo común: no se puede modificar ni eliminar.',
        ),
      ),
    );
    const { history } = await openPage();
    clickIn(speciesItem('Pez'), '.species-rename');
    fireEvent.change(nameInput(), { target: { value: 'Peces' } });
    fireEvent.click(dialogButton('Guardar el nombre'));

    expect(await screen.findByRole('dialog')).toHaveTextContent(
      'Este registro es del catálogo común: no se puede modificar ni eliminar.',
    );
    expect(history.location.pathname).toBe('/especies');
  });

  it('un 404 (otra persona la borró): aviso, el diálogo cerrado y el catálogo pedido de nuevo', async () => {
    server.use(
      http.delete('/api/species/:id', () =>
        failure(404, 'RESOURCE_NOT_FOUND', 'El recurso solicitado no existe.'),
      ),
    );
    const { calls } = await openPage();
    clickIn(speciesItem('Pez'), '.species-delete');
    fireEvent.click(dialogButton('Eliminar Pez'));

    expect(await screen.findByText('El recurso solicitado no existe.')).toBeInTheDocument();
    expect(dialog()).toBeNull();
    await waitFor(() => {
      expect(calls).toHaveLength(2);
    });
  });

  it('sin species.destroy: sin "Eliminar"; sin species.store: sin "Nueva especie" ni "Nueva raza"', async () => {
    const permissions = ROLE_PERMISSIONS.veterinario.filter(
      (permission) => permission !== 'species.destroy' && permission !== 'species.store',
    );
    await openPage('veterinario', permissions);
    expand('Canino');

    expect(speciesItem('Pez').querySelector('.species-rename')).not.toBeNull();
    expect(document.querySelector('.species-delete, .breed-delete')).toBeNull();
    expect(screen.queryByText('Nueva especie')).not.toBeInTheDocument();
    expect(document.querySelector('.breed-new')).toBeNull();
  });

  it('sin species.index, /especies va a /sin-permiso sin pedir el catálogo', async () => {
    const calls = catalogHandler();
    const permissions = ROLE_PERMISSIONS.recepcionista.filter(
      (permission) => permission !== 'species.index',
    );
    server.use(meHandler(meFor('recepcionista', { permissions })));
    const { history } = renderApp('/especies', { session: sessionFor() });

    await waitFor(() => {
      expect(history.location.pathname).toBe('/sin-permiso');
    });
    expect(calls).toHaveLength(0);
  });
});
