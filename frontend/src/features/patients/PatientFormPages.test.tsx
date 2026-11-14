import { fireEvent, screen, waitFor } from '@testing-library/react';
import { delay, http } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { failure, success } from '../../test/api';
import { detail, patient, PERSON } from '../../test/clients';
import { patientDetail, SPECIES } from '../../test/patients';
import { meFor, ROLE_PERMISSIONS, type Role } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { PATIENT_MESSAGES } from './form';
import type { PatientDetail, PatientPayload } from './types';

afterEach(() => {
  vi.useRealTimers();
});

// Por etiqueta o por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
function field(label: string): HTMLInputElement {
  return screen.getByLabelText(label, { selector: 'input, textarea' });
}

function select(label: string): HTMLSelectElement {
  return screen.getByLabelText(label, { selector: 'select' });
}

function type(label: string, value: string) {
  fireEvent.change(field(label), { target: { value } });
}

function choose(label: string, value: string) {
  fireEvent.change(select(label), { target: { value } });
}

const optionLabels = (label: string) =>
  Array.from(select(label).options).map((option) => option.text);

const submit = () => {
  fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));
};

/**
 * Una fecha a medio escribir en el <input type="date">: el navegador da el valor '' con validity.badInput (jsdom no lo
 * simula: se pone a mano en el campo).
 */
function typePartialDate(input: HTMLInputElement) {
  Object.defineProperty(input, 'validity', {
    configurable: true,
    value: { badInput: true, valid: false },
  });
  fireEvent.change(input, { target: { value: '' } });
}

/** El campo vuelve a tener una fecha completa. */
function typeFullDate(input: HTMLInputElement, value: string) {
  Reflect.deleteProperty(input, 'validity');
  fireEvent.change(input, { target: { value } });
}

const errorOf = (element: HTMLElement) =>
  document.getElementById(`${element.id}-error`)?.textContent ?? null;

/** El paciente que devuelve la API después de guardar, a partir del cuerpo mandado. */
function saved(
  id: number,
  body: PatientPayload,
  base: PatientDetail = patientDetail(),
): PatientDetail {
  const species = SPECIES.find((item) => item.id === body.species_id);
  const breed = species?.breeds.find((item) => item.id === body.breed_id);

  return {
    ...base,
    ...body,
    id,
    code: `PAC-${String(id).padStart(6, '0')}`,
    species: species === undefined ? base.species : { id: species.id, name: species.name },
    breed: breed === undefined ? null : { id: breed.id, name: breed.name },
    sex: {
      value: body.sex,
      label: { male: 'Macho', female: 'Hembra', unknown: 'Sin determinar' }[body.sex],
    },
    weight_kg: body.weight_kg === null ? null : Number(body.weight_kg).toFixed(2),
  };
}

/** Un handler que guarda los cuerpos y responde lo que diga `respond`. */
function writeHandler(
  method: 'post' | 'patch',
  path: string,
  respond: (body: PatientPayload) => Response | Promise<Response>,
) {
  const bodies: PatientPayload[] = [];
  const handler = http[method](path, async ({ request }) => {
    const body = (await request.json()) as PatientPayload;
    bodies.push(body);

    return respond(body);
  });

  return { handler, bodies };
}

const speciesHandler = http.get('/api/species', () => success(SPECIES));

function storeHandler(respond?: (body: PatientPayload) => Response | Promise<Response>) {
  return writeHandler(
    'post',
    '/api/clients/:id/patients',
    respond ??
      ((body) =>
        success(saved(9, body), { status: 201, message: 'Paciente creado correctamente.' })),
  );
}

async function openCreate(options: { role?: Role; store?: ReturnType<typeof storeHandler> } = {}) {
  const store = options.store ?? storeHandler();
  server.use(
    meHandler(meFor(options.role ?? 'recepcionista')),
    speciesHandler,
    store.handler,
    http.get('/api/clients/:id', () => success(detail(PERSON, [patient()]))),
    http.get('/api/patients/:id', () => {
      const last = store.bodies.at(-1);

      return success(last === undefined ? patientDetail() : saved(9, last));
    }),
  );
  const app = renderApp('/clientes/1/pacientes/nuevo', { session: sessionFor() });
  await screen.findByText('Nuevo paciente', { selector: 'h2' });

  return { ...app, store };
}

describe('/clientes/$id/pacientes/nuevo: alta', () => {
  it('el cuerpo completo (peso con punto, fecha tal cual, opcionales en null) y al detalle del paciente nuevo', async () => {
    const { store, history } = await openCreate();

    expect(field('Sin determinar')).toBeChecked();
    type('Nombre', '  Toby ');
    choose('Especie', '1');
    choose('Raza', '3');
    fireEvent.click(field('Macho'));
    fireEvent.click(field('Castrado'));
    type('Fecha de nacimiento', '2020-02-29');
    fireEvent.click(field('Es aproximada'));
    type('Peso (kg)', '12,5');
    type('Microchip', 'ABC123');
    type('Alergias', 'Penicilina.');
    submit();

    expect(await screen.findByText('Toby', { selector: 'h2' })).toBeInTheDocument();
    expect(store.bodies).toEqual([
      {
        name: 'Toby',
        species_id: 1,
        breed_id: 3,
        sex: 'male',
        is_neutered: true,
        birth_date: '2020-02-29',
        birth_date_estimated: true,
        color: null,
        weight_kg: '12.5',
        microchip: 'ABC123',
        allergies: 'Penicilina.',
        notes: null,
      },
    ]);
    expect(history.location.pathname).toBe('/pacientes/9');
    expect(screen.getByText('Paciente creado correctamente.')).toBeInTheDocument();
    // Guardar reemplaza al formulario: "Atrás" no vuelve a él.
    expect(history.length).toBe(1);
  });

  it('guardar invalida los pacientes y los clientes (el detalle del cliente lista sus pacientes)', async () => {
    const { queryClient } = await openCreate();

    type('Nombre', 'Toby');
    choose('Especie', '1');
    submit();
    await screen.findByText('Toby', { selector: 'h2' });

    expect(queryClient.getQueryState(['clients', 'detail', 1])?.isInvalidated).toBe(true);
    expect(queryClient.getQueryState(['species', { include_inactive: false }])?.isInvalidated).toBe(
      false,
    );
  });

  it('la raza se filtra por la especie y se vacía al cambiar de especie; una especie sin razas, solo "Sin raza"', async () => {
    await openCreate();

    expect(optionLabels('Raza')).toEqual(['Sin raza']);
    choose('Especie', '1');
    expect(optionLabels('Raza')).toEqual(['Sin raza', 'Mestizo', 'Labrador Retriever']);
    choose('Raza', '3');
    expect(select('Raza').value).toBe('3');

    choose('Especie', '4');
    expect(optionLabels('Raza')).toEqual(['Sin raza', 'Siamés']);
    expect(select('Raza').value).toBe('');

    choose('Especie', '6');
    expect(optionLabels('Raza')).toEqual(['Sin raza']);
  });

  it('cambiar de especie manda la raza en null (no la de la especie anterior)', async () => {
    const { store } = await openCreate();

    type('Nombre', 'Toby');
    choose('Especie', '1');
    choose('Raza', '3');
    choose('Especie', '4');
    submit();
    await screen.findByText('Toby', { selector: 'h2' });

    expect(store.bodies[0]).toMatchObject({ species_id: 4, breed_id: null });
  });

  it('vacío: el nombre y la especie con su mensaje y el foco en el nombre, sin llamar a la API', async () => {
    const { store } = await openCreate();

    submit();

    await waitFor(() => {
      expect(errorOf(field('Nombre'))).toBe(PATIENT_MESSAGES.name.required);
    });
    expect(errorOf(select('Especie'))).toBe(PATIENT_MESSAGES.species_id.required);
    expect(field('Nombre')).toHaveFocus();
    expect(store.bodies).toHaveLength(0);
  });

  it('el peso con tres decimales y un microchip con espacios: sus mensajes, sin llamar a la API', async () => {
    const { store } = await openCreate();

    type('Nombre', 'Toby');
    choose('Especie', '1');
    type('Peso (kg)', '12,555');
    type('Microchip', 'ABC 123');
    submit();

    await waitFor(() => {
      expect(errorOf(field('Peso (kg)'))).toBe(PATIENT_MESSAGES.weight_kg.decimal);
    });
    expect(errorOf(field('Microchip'))).toBe(PATIENT_MESSAGES.microchip.regex);
    expect(store.bodies).toHaveLength(0);
  });

  it('una fecha a medio escribir (validity.badInput): el error de formato, con el foco, sin llamar a la API', async () => {
    const { store } = await openCreate();

    type('Nombre', 'Toby');
    choose('Especie', '1');
    typePartialDate(field('Fecha de nacimiento'));
    submit();

    await waitFor(() => {
      expect(errorOf(field('Fecha de nacimiento'))).toBe(PATIENT_MESSAGES.birth_date.date_format);
    });
    expect(field('Fecha de nacimiento')).toHaveFocus();
    expect(store.bodies).toHaveLength(0);

    // Con otro error del formulario también se marca la fecha.
    type('Nombre', '');
    submit();
    await waitFor(() => {
      expect(errorOf(field('Nombre'))).toBe(PATIENT_MESSAGES.name.required);
    });
    expect(errorOf(field('Fecha de nacimiento'))).toBe(PATIENT_MESSAGES.birth_date.date_format);
    expect(store.bodies).toHaveLength(0);

    type('Nombre', 'Toby');
    typeFullDate(field('Fecha de nacimiento'), '2021-06-15');
    submit();
    await screen.findByText('Toby', { selector: 'h2' });
    expect(store.bodies[0]?.birth_date).toBe('2021-06-15');
  });

  it('los textos sin maxLength del navegador: 1000 emojis (2000 unidades UTF-16) en las alergias se mandan; 1001, el error', async () => {
    const { store } = await openCreate();

    for (const label of ['Nombre', 'Color', 'Alergias', 'Notas']) {
      expect(field(label)).not.toHaveAttribute('maxlength');
    }
    type('Nombre', 'Toby');
    choose('Especie', '1');
    type('Alergias', '🐶'.repeat(1001));
    submit();
    await waitFor(() => {
      expect(errorOf(field('Alergias'))).toBe(PATIENT_MESSAGES.allergies.max);
    });
    expect(store.bodies).toHaveLength(0);

    type('Alergias', '🐶'.repeat(1000));
    submit();
    await screen.findByText('Toby', { selector: 'h2' });
    expect(store.bodies[0]?.allergies).toBe('🐶'.repeat(1000));
  });

  it('la fecha de mañana en Asunción, con la PC en UTC (ya es mañana en UTC): rechazada sin llamar a la API', async () => {
    // Solo el reloj de Date: los temporizadores siguen reales (la página y MSW los usan).
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-14T01:30:00Z'));
    const { store } = await openCreate();

    expect(field('Fecha de nacimiento')).toHaveAttribute('max', '2026-11-13');
    type('Nombre', 'Toby');
    choose('Especie', '1');
    type('Fecha de nacimiento', '2026-11-14');
    submit();

    await waitFor(() => {
      expect(errorOf(field('Fecha de nacimiento'))).toBe(
        PATIENT_MESSAGES.birth_date.before_or_equal,
      );
    });
    expect(store.bodies).toHaveLength(0);

    type('Fecha de nacimiento', '2026-11-13');
    submit();
    await screen.findByText('Toby', { selector: 'h2' });
    expect(store.bodies[0]?.birth_date).toBe('2026-11-13');
  });

  it('el 422 de la API: el microchip en uso en su campo, con el foco', async () => {
    const store = storeHandler(() =>
      failure(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', {
        errors: { microchip: [PATIENT_MESSAGES.microchip.unique] },
      }),
    );
    await openCreate({ store });

    type('Nombre', 'Toby');
    choose('Especie', '1');
    type('Microchip', '985112003456789');
    submit();

    await waitFor(() => {
      expect(errorOf(field('Microchip'))).toBe(PATIENT_MESSAGES.microchip.unique);
    });
    expect(field('Microchip')).toHaveFocus();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('el 422 de la API: la especie inactiva en su campo; una clave que no es de un campo, arriba', async () => {
    const store = storeHandler(() =>
      failure(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', {
        errors: {
          species_id: ['La especie seleccionada no existe o está inactiva.'],
          custom: ['Otro error.'],
        },
      }),
    );
    await openCreate({ store });

    type('Nombre', 'Toby');
    choose('Especie', '1');
    submit();

    await waitFor(() => {
      expect(errorOf(select('Especie'))).toBe('La especie seleccionada no existe o está inactiva.');
    });
    expect(select('Especie')).toHaveFocus();
    expect(screen.getByText('Otro error.', { selector: '.alert-title' })).toBeInTheDocument();
  });

  it('PATIENT_BREED_SPECIES_MISMATCH: el mensaje en el campo de la raza, no en la alerta', async () => {
    const message = 'La raza no corresponde a la especie del paciente.';
    const store = storeHandler(() => failure(422, 'PATIENT_BREED_SPECIES_MISMATCH', message));
    await openCreate({ store });

    type('Nombre', 'Toby');
    choose('Especie', '1');
    choose('Raza', '2');
    submit();

    await waitFor(() => {
      expect(errorOf(select('Raza'))).toBe(message);
    });
    expect(select('Raza')).toHaveFocus();
    expect(document.querySelector('.alert')).toBeNull();
  });

  it('dos clics seguidos en Guardar: un solo POST', async () => {
    const { store } = await openCreate();

    type('Nombre', 'Toby');
    choose('Especie', '1');
    submit();
    submit();

    await screen.findByText('Toby', { selector: 'h2' });
    expect(store.bodies).toHaveLength(1);
  });

  it('dos envíos seguidos del formulario: un solo POST', async () => {
    const store = storeHandler(async (body) => {
      await delay(50);

      return success(saved(9, body), { status: 201, message: 'Paciente creado correctamente.' });
    });
    await openCreate({ store });

    type('Nombre', 'Toby');
    choose('Especie', '1');
    const form = document.querySelector('form.patient-form');
    if (form === null) {
      throw new Error('Sin formulario.');
    }
    fireEvent.submit(form);
    fireEvent.submit(form);

    await screen.findByText('Toby', { selector: 'h2' });
    expect(store.bodies).toHaveLength(1);
  });

  it('un 404 al guardar (el dueño ya no está): aviso y al listado de clientes', async () => {
    server.use(
      http.get('/api/clients', () =>
        success([], {
          meta: { pagination: { total: 0, per_page: 20, current_page: 1, last_page: 1 } },
        }),
      ),
    );
    const store = storeHandler(() =>
      failure(404, 'RESOURCE_NOT_FOUND', 'El recurso solicitado no existe.'),
    );
    const { history } = await openCreate({ store });

    type('Nombre', 'Toby');
    choose('Especie', '1');
    submit();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/clientes');
    });
    expect(screen.getByText('El recurso solicitado no existe.')).toBeInTheDocument();
  });

  it('"Cancelar" vuelve al detalle del cliente sin llamar a la API', async () => {
    const { store, history } = await openCreate();

    type('Nombre', 'Toby');
    fireEvent.click(screen.getByText('Cancelar', { selector: 'a' }));

    expect(await screen.findByText('Laura Martínez', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/clientes/1');
    expect(store.bodies).toHaveLength(0);
  });

  it('sin patients.store, /clientes/1/pacientes/nuevo va a /sin-permiso', async () => {
    const permissions = ROLE_PERMISSIONS.recepcionista.filter(
      (permission) => permission !== 'patients.store',
    );
    server.use(meHandler(meFor('recepcionista', { permissions })), speciesHandler);
    const { history } = renderApp('/clientes/1/pacientes/nuevo', { session: sessionFor() });

    await waitFor(() => {
      expect(history.location.pathname).toBe('/sin-permiso');
    });
  });
});

function updateHandler(respond?: (body: PatientPayload) => Response | Promise<Response>) {
  return writeHandler(
    'patch',
    '/api/patients/:id',
    respond ??
      ((body) =>
        success(saved(1, body, patientDetail()), {
          message: 'Paciente actualizado correctamente.',
        })),
  );
}

async function openEdit(
  options: { data?: PatientDetail; update?: ReturnType<typeof updateHandler>; role?: Role } = {},
) {
  const data = options.data ?? patientDetail({ weight_kg: '12.50' });
  const update = options.update ?? updateHandler();
  server.use(
    meHandler(meFor(options.role ?? 'veterinario')),
    speciesHandler,
    update.handler,
    http.get('/api/patients/:id', () => success(data)),
  );
  const app = renderApp(`/pacientes/${String(data.id)}/editar`, { session: sessionFor() });
  await screen.findByText('Editar el paciente', { selector: 'h2' });

  return { ...app, update };
}

describe('/pacientes/$id/editar: edición', () => {
  it('el formulario con los datos guardados (el peso con coma); manda el formulario completo y vuelve al detalle', async () => {
    const { update, history } = await openEdit();

    expect(field('Nombre').value).toBe('Firulais');
    expect(select('Especie').value).toBe('1');
    expect(select('Raza').value).toBe('2');
    expect(field('Macho')).toBeChecked();
    expect(field('Fecha de nacimiento').value).toBe('2022-03-10');
    expect(field('Peso (kg)').value).toBe('12,50');
    expect(field('Microchip').value).toBe('985112003456789');
    type('Notas', 'Control en diciembre.');
    submit();

    expect(await screen.findByText('Firulais', { selector: 'h2' })).toBeInTheDocument();
    expect(update.bodies).toEqual([
      {
        name: 'Firulais',
        species_id: 1,
        breed_id: 2,
        sex: 'male',
        is_neutered: false,
        birth_date: '2022-03-10',
        birth_date_estimated: false,
        color: 'Marrón',
        // El texto de la API, con punto, tal como vino (H5).
        weight_kg: '12.50',
        microchip: '985112003456789',
        allergies: null,
        notes: 'Control en diciembre.',
      },
    ]);
    expect(history.location.pathname).toBe('/pacientes/1');
    expect(screen.getByText('Paciente actualizado correctamente.')).toBeInTheDocument();
    // El detalle muestra lo recién guardado (la respuesta del PATCH), aunque GET patients/{id} siga con lo de antes.
    expect(document.querySelector('.patient-data')).toHaveTextContent('Control en diciembre.');
    expect(history.length).toBe(1);
  });

  it('la especie y la raza inactivas del paciente se ofrecen con "(inactivada)" y siguen elegidas', async () => {
    const { update } = await openEdit({
      data: patientDetail({
        species: { id: 9, name: 'Reptil' },
        breed: { id: 10, name: 'Iguana verde' },
      }),
    });

    expect(select('Especie').value).toBe('9');
    expect(optionLabels('Especie')).toContain('Reptil (inactivada)');
    expect(select('Raza').value).toBe('10');
    expect(optionLabels('Raza')).toEqual(['Sin raza', 'Iguana verde (inactivada)']);
    submit();

    await screen.findByText('Firulais', { selector: 'h2' });
    expect(update.bodies[0]).toMatchObject({ species_id: 9, breed_id: 10 });
  });

  it('la raza inactiva de una especie activa también se ofrece', async () => {
    await openEdit({ data: patientDetail({ breed: { id: 8, name: 'Dogo' } }) });

    expect(select('Raza').value).toBe('8');
    expect(optionLabels('Raza')).toEqual([
      'Sin raza',
      'Mestizo',
      'Labrador Retriever',
      'Dogo (inactivada)',
    ]);
  });

  it('dos clics seguidos en Guardar: un solo PATCH', async () => {
    const { update } = await openEdit();

    submit();
    submit();

    await screen.findByText('Firulais', { selector: 'h2' });
    expect(update.bodies).toHaveLength(1);
  });

  it('mientras espera la API, Guardar está deshabilitado', async () => {
    let release: () => void = () => undefined;
    const update = updateHandler(async (body) => {
      await new Promise<void>((resolve) => {
        release = resolve;
      });

      return success(saved(1, body), { message: 'Paciente actualizado correctamente.' });
    });
    await openEdit({ update });

    submit();
    await waitFor(() => {
      expect(update.bodies).toHaveLength(1);
    });
    expect(screen.getByText('Guardar', { selector: 'button' })).toBeDisabled();
    release();
    await screen.findByText('Firulais', { selector: 'h2' });
  });

  it('una fecha guardada que queda a medio escribir no se borra: el error de formato y ningún PATCH', async () => {
    const { update } = await openEdit();

    expect(field('Fecha de nacimiento').value).toBe('2022-03-10');
    typePartialDate(field('Fecha de nacimiento'));
    submit();

    await waitFor(() => {
      expect(errorOf(field('Fecha de nacimiento'))).toBe(PATIENT_MESSAGES.birth_date.date_format);
    });
    expect(update.bodies).toHaveLength(0);

    typeFullDate(field('Fecha de nacimiento'), '2022-03-11');
    submit();
    await screen.findByText('Firulais', { selector: 'h2' });
    expect(update.bodies[0]?.birth_date).toBe('2022-03-11');
  });

  it('un 409 CONFLICT al guardar: el aviso con el mensaje y se vuelve a pedir el paciente', async () => {
    const message = 'El paciente cambió mientras se editaba.';
    await openEdit({ update: updateHandler(() => failure(409, 'CONFLICT', message)) });
    let reads = 0;
    server.use(
      http.get('/api/patients/:id', () => {
        reads++;

        return success(patientDetail({ weight_kg: '12.50' }));
      }),
    );

    submit();

    expect(
      await screen.findByText(
        `Alguien cambió estos datos mientras guardabas. Revisalos y probá de nuevo. ${message}`,
        {
          selector: '.alert-title',
        },
      ),
    ).toBeInTheDocument();
    await waitFor(() => {
      expect(reads).toBe(1);
    });
  });

  it('PATIENT_DECEASED: la alerta arriba y el formulario como estaba', async () => {
    const message =
      'El paciente figura como fallecido: solo se pueden editar la fecha de fallecimiento y las notas.';
    await openEdit({ update: updateHandler(() => failure(422, 'PATIENT_DECEASED', message)) });

    type('Nombre', 'Firu');
    submit();

    expect(await screen.findByText(message, { selector: '.alert-title' })).toBeInTheDocument();
    expect(field('Nombre').value).toBe('Firu');
  });

  it('un 404 al guardar (el paciente ya no está): aviso y al listado de pacientes', async () => {
    server.use(
      http.get('/api/patients', () =>
        success([], {
          meta: { pagination: { total: 0, per_page: 20, current_page: 1, last_page: 1 } },
        }),
      ),
    );
    const { history } = await openEdit({
      update: updateHandler(() =>
        failure(404, 'RESOURCE_NOT_FOUND', 'El recurso solicitado no existe.'),
      ),
    });

    submit();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/pacientes');
    });
    expect(screen.getByText('El recurso solicitado no existe.')).toBeInTheDocument();
  });

  it('"Cancelar" vuelve al detalle sin llamar a la API', async () => {
    const { update, history } = await openEdit();

    fireEvent.click(screen.getByText('Cancelar', { selector: 'a' }));

    expect(await screen.findByText('Firulais', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/pacientes/1');
    expect(update.bodies).toHaveLength(0);
  });

  it('sin patients.update, /pacientes/1/editar va a /sin-permiso', async () => {
    const permissions = ROLE_PERMISSIONS.veterinario.filter(
      (permission) => permission !== 'patients.update',
    );
    server.use(meHandler(meFor('veterinario', { permissions })));
    const { history } = renderApp('/pacientes/1/editar', { session: sessionFor() });

    await waitFor(() => {
      expect(history.location.pathname).toBe('/sin-permiso');
    });
  });
});
