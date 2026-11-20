import { fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { CENTRAL, listItem, NORTH } from '../../test/branches';
import { pagination } from '../../test/clients';
import { meFor, type Role } from '../../test/roles';
import { GROOMING, ROOM_ONE, scheduleListItem } from '../../test/schedules';
import { server } from '../../test/server';
import { serviceListItem, VACCINE } from '../../test/services';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { OTHER_VET_DETAIL, VET_DETAIL, veterinarianListItem } from '../../test/veterinarians';
import type { ScheduleDetail } from './types';

// Por etiqueta o por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
function field(label: string): HTMLInputElement | HTMLSelectElement {
  const found = Array.from(document.querySelectorAll('label')).find(
    (element) => element.textContent.replace('*', '').trim() === label,
  );
  const input = found?.htmlFor ? document.getElementById(found.htmlFor) : null;

  if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement)) {
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

/** Los campos de hora de los tramos de un día (1 = lunes), en el orden de la pantalla. */
function dayInputs(weekday: number, kind: 'start' | 'end'): HTMLInputElement[] {
  return Array.from(
    document.querySelectorAll<HTMLInputElement>(`.schedule-day-${String(weekday)} .hour-${kind}`),
  );
}

function addHour(weekday: number, starts: string, ends: string) {
  fireEvent.click(required(`.schedule-day-${String(weekday)} .schedule-hour-add`));
  const starts_ = dayInputs(weekday, 'start');
  const ends_ = dayInputs(weekday, 'end');
  const start = starts_.at(-1);
  const end = ends_.at(-1);

  if (start === undefined || end === undefined) {
    throw new Error('Sin el tramo nuevo');
  }

  fireEvent.change(start, { target: { value: starts } });
  fireEvent.change(end, { target: { value: ends } });
}

const ADMIN = meFor('administrador');
const IN_USE_MESSAGE = 'La agenda tiene turnos y no se puede eliminar: inactivala.';
const NOT_EDITABLE_MESSAGE =
  'La agenda ya tiene turnos y no puede cambiar de sucursal ni de veterinario: creá otra agenda e inactivá esta.';

/** Las listas de los selectores del formulario: las sucursales, los veterinarios activos y los servicios activos. */
function optionHandlers() {
  return [
    http.get('/api/branches', () =>
      success([listItem(CENTRAL), listItem(NORTH)], { meta: pagination({ total: 2 }) }),
    ),
    http.get('/api/veterinarians', () =>
      success([veterinarianListItem(VET_DETAIL), veterinarianListItem(OTHER_VET_DETAIL)], {
        meta: pagination({ total: 2 }),
      }),
    ),
    http.get('/api/services', () =>
      success([serviceListItem(VACCINE)], { meta: pagination({ total: 1 }) }),
    ),
  ];
}

function listHandler(urls: URL[] = [], items = [ROOM_ONE, GROOMING]) {
  return http.get('/api/schedules', ({ request }) => {
    urls.push(new URL(request.url));

    return success(items.map(scheduleListItem), { meta: pagination({ total: items.length }) });
  });
}

function detailHandler(current: () => ScheduleDetail) {
  return http.get('/api/schedules/:id', ({ params }) =>
    String(params.id) === String(current().id)
      ? success(current())
      : failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'),
  );
}

/** Los bloqueos de la tarjeta del detalle (3.1b): ninguno. Sus casos están en ScheduleBlocks.test.tsx. */
function noBlocksHandler() {
  return http.get('/api/schedules/:id/blocks', () =>
    success([], { meta: pagination({ total: 0 }) }),
  );
}

async function openDetail(schedule: ScheduleDetail, extra: Parameters<typeof server.use> = []) {
  server.use(
    ...extra,
    meHandler(ADMIN),
    detailHandler(() => schedule),
    noBlocksHandler(),
  );
  const app = renderApp(`/configuracion/agendas/${String(schedule.id)}`, { session: sessionFor() });
  await screen.findByText(schedule.name, { selector: '.schedule-title' });

  return app;
}

/** Espera las opciones de los selectores (sin ellas, elegir un valor no hace nada). */
async function waitForOptions() {
  await waitFor(() => {
    expect(Array.from((field('Veterinario') as HTMLSelectElement).options).length).toBeGreaterThan(
      0,
    );
    expect(
      Array.from((field('Sucursal') as HTMLSelectElement).options).map((o) => o.text),
    ).toContain('Casa central');
  });
}

describe('/configuracion: la tarjeta de Agendas', () => {
  it('el administrador la ve y lleva al listado', async () => {
    server.use(meHandler(ADMIN), listHandler(), ...optionHandlers());
    const { history } = renderApp('/configuracion', { session: sessionFor() });

    fireEvent.click(
      await screen.findByText('Agendas', { selector: '.configuration-sections span' }),
    );

    await screen.findByText('Agendas', { selector: 'h2' });
    expect(history.location.pathname).toBe('/configuracion/agendas');
  });

  it.each(
    (['recepcionista', 'veterinario'] as Role[]).flatMap((role) =>
      [
        '/configuracion/agendas',
        '/configuracion/agendas/7',
        '/configuracion/agendas/nueva',
        '/configuracion/agendas/7/editar',
      ].map((url) => [role, url] as const),
    ),
  )('%s en %s va a /sin-permiso', async (role, url) => {
    server.use(meHandler(meFor(role)));
    const { history } = renderApp(url, { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
    expect(history.location.pathname).toBe('/sin-permiso');
  });
});

describe('/configuracion/agendas: listado', () => {
  it('la tabla con el veterinario, el servicio, la duración y la marca "Inactivada"; sucursal e inactivas en la URL y en el pedido', async () => {
    const urls: URL[] = [];
    server.use(meHandler(ADMIN), listHandler(urls), ...optionHandlers());
    const { history } = renderApp('/configuracion/agendas', { session: sessionFor() });

    expect(await screen.findByText('Consultorio 1', { selector: 'a' })).toHaveAttribute(
      'href',
      '/configuracion/agendas/7',
    );
    expect(screen.getByText('Inactivada', { selector: '.schedule-inactive' })).toBeInTheDocument();
    expect(screen.getByText('60 min')).toBeInTheDocument();
    expect(required('.schedule-new')).toHaveAttribute('href', '/configuracion/agendas/nueva');
    expect(urls[0]?.searchParams.get('include_inactive')).toBeNull();

    await waitFor(() => {
      expect(
        Array.from((field('Sucursal') as HTMLSelectElement).options).map((o) => o.text),
      ).toContain('Sucursal Norte');
    });
    type('Sucursal', '11');
    await waitFor(() => {
      expect(history.location.search).toContain('sucursal=11');
    });
    fireEvent.click(field('Incluir las inactivas'));

    await waitFor(() => {
      expect(urls.at(-1)?.searchParams.get('include_inactive')).toBe('1');
    });
    expect(urls.at(-1)?.searchParams.get('branch_id')).toBe('11');
    expect(history.location.search).toContain('inactivas=1');
  });
});

describe('/configuracion/agendas/$id: detalle', () => {
  it('los datos y el horario semanal de lunes a domingo, con "Sin atención" en los días sin tramos', async () => {
    await openDetail(ROOM_ONE);

    expect(required('.schedule-day-1').textContent).toBe('08:00 a 12:00, 12:00 a 16:00');
    expect(required('.schedule-day-3').textContent).toBe('14:00 a 18:00');
    expect(required('.schedule-day-2').textContent).toBe('Sin atención');
    expect(required('.schedule-data').textContent).toContain('Víctor Acosta');
    expect(required('.schedule-data').textContent).toContain('30 min');
    expect(required('.schedule-status-action').textContent).toBe('Inactivar');
    expect(required('.schedule-edit')).toHaveAttribute('href', '/configuracion/agendas/7/editar');
  });

  it('un id que no existe: la página de no encontrado', async () => {
    server.use(
      meHandler(ADMIN),
      detailHandler(() => ROOM_ONE),
      noBlocksHandler(),
    );
    renderApp('/configuracion/agendas/99', { session: sessionFor() });

    expect(await screen.findByText('No encontramos esta agenda')).toBeInTheDocument();
  });
});

describe('/configuracion/agendas/nueva: alta', () => {
  it('dos tramos del lunes que se tocan: el cuerpo con el horario, un solo pedido con doble clic, y al detalle', async () => {
    const bodies: unknown[] = [];
    const created: ScheduleDetail = { ...ROOM_ONE, id: 9, name: 'Consultorio 2' };
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      http.post('/api/schedules', async ({ request }) => {
        bodies.push(await request.json());

        return success(created, { status: 201, message: 'Agenda creada correctamente.' });
      }),
      detailHandler(() => created),
      noBlocksHandler(),
    );
    const { history } = renderApp('/configuracion/agendas/nueva', { session: sessionFor() });

    await screen.findByText('Nueva agenda', { selector: 'h2' });
    await waitForOptions();
    type('Sucursal', '10');
    // De la Casa central, solo el veterinario activo con el usuario activo.
    await waitFor(() => {
      expect(
        Array.from((field('Veterinario') as HTMLSelectElement).options).map((o) => o.text),
      ).toEqual(['Sin veterinario', 'Víctor Acosta']);
    });
    type('Veterinario', '4');
    type('Nombre', ' Consultorio 2 ');
    addHour(1, '08:00', '12:00');
    addHour(1, '12:00', '16:00');
    save();
    save();

    await screen.findByText('Consultorio 2', { selector: '.schedule-title' });
    expect(history.location.pathname).toBe('/configuracion/agendas/9');
    expect(bodies).toEqual([
      {
        branch_id: 10,
        name: 'Consultorio 2',
        veterinarian_id: 4,
        service_id: null,
        slot_minutes: 30,
        color: null,
        hours: [
          { weekday: 1, starts_at: '08:00', ends_at: '12:00' },
          { weekday: 1, starts_at: '12:00', ends_at: '16:00' },
        ],
      },
    ]);
  });

  it('sin veterinario ni servicio y con tramos superpuestos: los mensajes, sin pedido', async () => {
    const bodies: unknown[] = [];
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      http.post('/api/schedules', () => {
        bodies.push(1);

        return success(ROOM_ONE, { status: 201 });
      }),
    );
    renderApp('/configuracion/agendas/nueva', { session: sessionFor() });

    await screen.findByText('Nueva agenda', { selector: 'h2' });
    await waitForOptions();
    type('Sucursal', '10');
    type('Nombre', 'Consultorio 2');
    addHour(2, '08:00', '12:00');
    addHour(2, '11:00', '13:00');
    save();

    await waitFor(() => {
      expect(fieldError('Veterinario')).toBe('Elegí un veterinario, un servicio o los dos.');
    });
    expect(
      screen.getByText('El horario del martes tiene tramos que se superponen.'),
    ).toBeInTheDocument();
    expect(bodies).toEqual([]);
  });

  it('"Copiar el lunes a los días hábiles" pone los tramos del lunes del martes al viernes; el sábado no cambia', async () => {
    server.use(meHandler(ADMIN), ...optionHandlers());
    renderApp('/configuracion/agendas/nueva', { session: sessionFor() });

    await screen.findByText('Nueva agenda', { selector: 'h2' });
    expect((required('.schedule-copy-monday') as HTMLButtonElement).disabled).toBe(true);
    addHour(1, '08:00', '12:00');
    addHour(3, '15:00', '16:00');
    addHour(6, '09:00', '11:00');
    fireEvent.click(required('.schedule-copy-monday'));

    await waitFor(() => {
      for (const weekday of [2, 3, 4, 5]) {
        expect(dayInputs(weekday, 'start').map((input) => input.value)).toEqual(['08:00']);
        expect(dayInputs(weekday, 'end').map((input) => input.value)).toEqual(['12:00']);
      }
    });
    expect(dayInputs(6, 'start').map((input) => input.value)).toEqual(['09:00']);
    expect(dayInputs(7, 'start')).toEqual([]);
  });

  it('"Sumar un tramo" lleva el foco a "Desde" del tramo nuevo; "Quitar" lo devuelve a "Sumar un tramo" del día', async () => {
    server.use(meHandler(ADMIN), ...optionHandlers());
    renderApp('/configuracion/agendas/nueva', { session: sessionFor() });

    await screen.findByText('Nueva agenda', { selector: 'h2' });
    addHour(1, '08:00', '12:00');
    fireEvent.click(required('.schedule-day-3 .schedule-hour-add'));

    await waitFor(() => {
      expect(document.activeElement).toBe(dayInputs(3, 'start')[0]);
    });
    fireEvent.click(required('.schedule-day-3 .schedule-hour-remove'));
    expect(document.activeElement).toBe(required('.schedule-day-3 .schedule-hour-add'));
  });

  it('"Sumar un tramo" se desactiva con 6 tramos en el día', async () => {
    server.use(meHandler(ADMIN), ...optionHandlers());
    renderApp('/configuracion/agendas/nueva', { session: sessionFor() });

    await screen.findByText('Nueva agenda', { selector: 'h2' });
    for (let i = 0; i < 6; i++) {
      addHour(4, `0${String(i)}:00`, `0${String(i)}:30`);
    }

    expect(required('.schedule-day-4 .schedule-hour-add')).toHaveAttribute('aria-disabled', 'true');
    expect(dayInputs(4, 'start')).toHaveLength(6);
  });

  it('422 con hours.1.ends_at: el mensaje en el fin del segundo tramo; la clave hours, en la alerta', async () => {
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      http.post('/api/schedules', () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos no son válidos.', {
          errors: {
            'hours.1.ends_at': ['La hora de fin del tramo debe ser posterior a la de inicio.'],
            hours: ['El horario del lunes tiene tramos que se superponen.'],
          },
        }),
      ),
    );
    renderApp('/configuracion/agendas/nueva', { session: sessionFor() });

    await screen.findByText('Nueva agenda', { selector: 'h2' });
    await waitForOptions();
    type('Sucursal', '10');
    type('Veterinario', '4');
    type('Nombre', 'Consultorio 2');
    addHour(1, '08:00', '12:00');
    addHour(1, '13:00', '14:00');
    save();

    const second = dayInputs(1, 'end')[1];
    await waitFor(() => {
      expect(second?.getAttribute('aria-invalid')).toBe('true');
    });
    expect(document.getElementById(`${second?.id ?? ''}-error`)?.textContent).toBe(
      'La hora de fin del tramo debe ser posterior a la de inicio.',
    );
    expect(dayInputs(1, 'end')[0]?.getAttribute('aria-invalid')).toBeNull();
    expect(
      screen.getByText('El horario del lunes tiene tramos que se superponen.', {
        selector: '.alert-title',
      }),
    ).toBeInTheDocument();
  });
});

describe('/configuracion/agendas/$id/editar', () => {
  it('quitar todos los tramos manda `hours: []` (vacía el horario) con el formulario completo', async () => {
    const bodies: unknown[] = [];
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      detailHandler(() => ROOM_ONE),
      noBlocksHandler(),
      http.patch('/api/schedules/:id', async ({ request }) => {
        bodies.push(await request.json());

        return success(
          { ...ROOM_ONE, hours: [] },
          { message: 'Agenda actualizada correctamente.' },
        );
      }),
    );
    const { history } = renderApp('/configuracion/agendas/7/editar', { session: sessionFor() });

    await screen.findByText('Editar la agenda', { selector: 'h2' });
    expect(
      screen.getByText(/El horario nuevo no cambia los turnos que ya están dados/),
    ).toBeInTheDocument();
    await waitForOptions();
    while (document.querySelector('.schedule-hour-remove') !== null) {
      fireEvent.click(required('.schedule-hour-remove'));
    }
    save();

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/agendas/7');
    });
    expect(bodies).toEqual([
      {
        branch_id: 10,
        name: 'Consultorio 1',
        veterinarian_id: 4,
        service_id: 3,
        slot_minutes: 30,
        color: '#206BC4',
        hours: [],
      },
    ]);
  });

  it('SCHEDULE_NOT_EDITABLE: el mensaje de la API en la alerta de arriba', async () => {
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      detailHandler(() => ROOM_ONE),
      noBlocksHandler(),
      http.patch('/api/schedules/:id', () =>
        failure(422, 'SCHEDULE_NOT_EDITABLE', NOT_EDITABLE_MESSAGE),
      ),
    );
    renderApp('/configuracion/agendas/7/editar', { session: sessionFor() });

    await screen.findByText('Editar la agenda', { selector: 'h2' });
    await waitForOptions();
    type('Sucursal', '11');
    save();

    expect(
      await screen.findByText(NOT_EDITABLE_MESSAGE, { selector: '.alert-title' }),
    ).toBeInTheDocument();
  });

  it('cambiar a una sucursal donde el veterinario no trabaja lo suelta: el cuerpo va sin veterinario', async () => {
    const bodies: unknown[] = [];
    server.use(
      meHandler(ADMIN),
      ...optionHandlers(),
      detailHandler(() => ROOM_ONE),
      noBlocksHandler(),
      http.patch('/api/schedules/:id', async ({ request }) => {
        bodies.push(await request.json());

        return success(ROOM_ONE, { message: 'Agenda actualizada correctamente.' });
      }),
    );
    renderApp('/configuracion/agendas/7/editar', { session: sessionFor() });

    await screen.findByText('Editar la agenda', { selector: 'h2' });
    await waitForOptions();
    expect(field('Veterinario').value).toBe('4');
    type('Sucursal', '11');

    await waitFor(() => {
      expect(field('Veterinario').value).toBe('');
    });
    // Sin la opción, el <select> se ve vacío aunque el formulario guardara el veterinario: lo que cuenta es el cuerpo.
    save();
    await waitFor(() => {
      expect(bodies).toHaveLength(1);
    });
    expect(bodies[0]).toMatchObject({ branch_id: 11, veterinarian_id: null, service_id: 3 });
  });
});

describe('/configuracion/agendas/$id: inactivar, activar y eliminar', () => {
  function statusHandler(bodies: unknown[], base: ScheduleDetail) {
    return http.patch('/api/schedules/:id', async ({ request }) => {
      const body = (await request.json()) as { is_active: boolean };
      bodies.push(body);

      return success(
        { ...base, is_active: body.is_active },
        { message: 'Agenda actualizada correctamente.' },
      );
    });
  }

  it('inactivar: el aviso de los turnos que siguen, el cuerpo solo con is_active y la marca nueva', async () => {
    const bodies: unknown[] = [];
    await openDetail(ROOM_ONE, [statusHandler(bodies, ROOM_ONE)]);

    fireEvent.click(required('.schedule-status-action'));
    expect(dialog()?.textContent).toContain(
      'Los turnos que ya tiene siguen: reprogramalos o cancelalos.',
    );
    fireEvent.click(await findDialogButton('Inactivar Consultorio 1'));

    await waitFor(() => {
      expect(required('.schedule-status-action').textContent).toBe('Activar');
    });
    expect(bodies).toEqual([{ is_active: false }]);
    expect(screen.getByText('Inactivada', { selector: '.schedule-inactive' })).toBeInTheDocument();
  });

  it('activar una inactiva: { is_active: true }', async () => {
    const bodies: unknown[] = [];
    await openDetail(GROOMING, [statusHandler(bodies, GROOMING)]);

    fireEvent.click(required('.schedule-status-action'));
    fireEvent.click(await findDialogButton('Activar Peluquería'));

    await waitFor(() => {
      expect(bodies).toEqual([{ is_active: true }]);
    });
  });

  it('eliminar sin turnos: al listado, con el aviso', async () => {
    const calls: string[] = [];
    const { history } = await openDetail(ROOM_ONE, [
      http.delete('/api/schedules/:id', ({ params }) => {
        calls.push(String(params.id));

        return success(null, { message: 'Agenda eliminada correctamente.' });
      }),
      listHandler([], [GROOMING]),
      ...optionHandlers(),
    ]);

    fireEvent.click(required('.schedule-delete'));
    fireEvent.click(await findDialogButton('Eliminar Consultorio 1'));

    await waitFor(() => {
      expect(history.location.pathname).toBe('/configuracion/agendas');
    });
    expect(await screen.findByText('Agenda eliminada correctamente.')).toBeInTheDocument();
    expect(calls).toEqual(['7']);
  });

  it('eliminar con SCHEDULE_IN_USE: el mensaje y "Inactivar", que abre el diálogo de inactivar', async () => {
    await openDetail(ROOM_ONE, [
      http.delete('/api/schedules/:id', () => failure(409, 'SCHEDULE_IN_USE', IN_USE_MESSAGE)),
    ]);

    fireEvent.click(required('.schedule-delete'));
    fireEvent.click(await findDialogButton('Eliminar Consultorio 1'));

    expect(await screen.findByText(IN_USE_MESSAGE)).toBeInTheDocument();
    fireEvent.click(await findDialogButton('Inactivar'));
    expect(screen.queryByText(IN_USE_MESSAGE)).toBeNull();
    expect(await findDialogButton('Inactivar Consultorio 1')).toBeInTheDocument();
  });

  it('SCHEDULE_IN_USE en una ya inactiva: el mensaje y solo "Cerrar"', async () => {
    await openDetail(GROOMING, [
      http.delete('/api/schedules/:id', () => failure(409, 'SCHEDULE_IN_USE', IN_USE_MESSAGE)),
    ]);

    fireEvent.click(required('.schedule-delete'));
    fireEvent.click(await findDialogButton('Eliminar Peluquería'));

    expect(await screen.findByText(IN_USE_MESSAGE)).toBeInTheDocument();
    expect(await findDialogButton('Cerrar')).toBeInTheDocument();
    expect(dialog()?.textContent).not.toContain('¿Querés eliminar');
  });
});
