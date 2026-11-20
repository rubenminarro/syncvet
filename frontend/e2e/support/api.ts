import { request, type APIRequestContext } from '@playwright/test';
import { BACKEND_URL } from './env';
import { demoPassword, USERS, type User } from './session';

interface Envelope<T> {
  success: boolean;
  message: string;
  data: T;
}

/**
 * La API del backend de las pruebas (syncvet_e2e, nunca la demo), con la sesión de un usuario del DemoSeeder: lo que un
 * spec crea por la API o tiene que limpiar si un paso falló (pregunta 13 del diseño de la F3). `dispose()` cierra la sesión
 * (revoca el token) y el contexto.
 */
export async function apiAs(user: User = USERS.admin) {
  const context: APIRequestContext = await request.newContext({
    baseURL: `${BACKEND_URL}/api/`,
    extraHTTPHeaders: { Accept: 'application/json' },
  });

  async function call<T>(method: string, path: string, data?: unknown, token?: string): Promise<T> {
    const response = await context.fetch(path, {
      method,
      ...(data === undefined ? {} : { data }),
      ...(token === undefined ? {} : { headers: { Authorization: `Bearer ${token}` } }),
    });
    const json = (await response.json()) as Envelope<T>;

    if (!response.ok() || !json.success) {
      throw new Error(
        `${method} /api/${path} respondió ${String(response.status())}: ${json.message}`,
      );
    }

    return json.data;
  }

  const { token } = await call<{ token: string }>('POST', 'login', {
    email: user.email,
    password: demoPassword(),
  });

  return {
    get: <T>(path: string) => call<T>('GET', path, undefined, token),
    post: <T>(path: string, data: unknown) => call<T>('POST', path, data, token),
    patch: <T>(path: string, data: unknown) => call<T>('PATCH', path, data, token),
    delete: (path: string) => call<null>('DELETE', path, undefined, token),
    async dispose() {
      await call<null>('POST', 'logout', undefined, token).catch(() => undefined);
      await context.dispose();
    },
  };
}

/**
 * Crea por la API (como el administrador) una agenda de `vet-a` en la Casa central, con el horario de lunes a domingo de
 * 08:00 a 16:00, y devuelve su id (desde la 3.1b: los specs que necesitan una agenda sin pasar por su formulario).
 */
export async function createScheduleFor(name: string): Promise<number> {
  const api = await apiAs(USERS.admin);

  try {
    const branches = await api.get<{ id: number; name: string }[]>('branches?per_page=100');
    const branch = branches.find((item) => item.name === 'Casa central');
    const vets = await api.get<{ id: number; user: { name: string } }[]>(
      'veterinarians?per_page=100',
    );
    const vet = vets.find((item) => item.user.name === USERS.vet.name);

    if (branch === undefined || vet === undefined) {
      throw new Error('Sin la Casa central o sin la ficha de vet-a en syncvet_e2e.');
    }

    const schedule = await api.post<{ id: number }>('schedules', {
      branch_id: branch.id,
      name,
      veterinarian_id: vet.id,
      slot_minutes: 30,
      hours: [1, 2, 3, 4, 5, 6, 7].map((weekday) => ({
        weekday,
        starts_at: '08:00',
        ends_at: '16:00',
      })),
    });

    return schedule.id;
  } finally {
    await api.dispose();
  }
}

/** Elimina las agendas cuyo nombre empieza con `prefix` (también las inactivas): la limpieza de un spec que se cortó. */
export async function deleteSchedulesNamed(prefix: string): Promise<void> {
  const api = await apiAs(USERS.admin);

  try {
    const schedules = await api.get<{ id: number; name: string }[]>(
      `schedules?include_inactive=1&per_page=100&search=${encodeURIComponent(prefix)}`,
    );

    for (const schedule of schedules.filter((item) => item.name.startsWith(prefix))) {
      await api.delete(`schedules/${String(schedule.id)}`);
    }
  } finally {
    await api.dispose();
  }
}

/** "Hoy" en Asunción (yyyy-MM-dd), como lo calcula la agenda; y el día `offset` días después. */
export function asuncionDay(offset = 0): string {
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Asuncion' }).format(
    new Date(),
  );
  const date = new Date(`${today}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + offset);

  return date.toISOString().slice(0, 10);
}

/** Lo que arma `createAgendaDay`: la agenda, el día y los turnos (por su contacto). */
export interface AgendaDay {
  scheduleId: number;
  day: string;
  /** 09:00 a 09:30, confirmado. */
  plain: string;
  /** 10:00 a 10:30, a confirmar, dentro del bloqueo de 10:00 a 11:00. */
  inBlock: string;
  /** 11:00 a 11:30, cancelado. */
  cancelled: string;
}

/**
 * La agenda del día de la 3.2 por la API, en syncvet_e2e (como el administrador): una agenda de `vet-a` en la Casa central,
 * abierta todos los días de 00:00 a 23:55 (así el caso no depende de la hora en que corre); tres turnos de hoy con un
 * contacto (09:00, 10:00 y 11:00; la API acepta hoy una hora que ya pasó); el de las 11:00, cancelado, y un bloqueo de 10:00
 * a 11:00 creado después del de las 10:00 (la API lo crea igual y el turno queda adentro). Hoy, Asunción es UTC-3 todo el año.
 */
export async function createAgendaDay(name: string): Promise<AgendaDay> {
  const api = await apiAs(USERS.admin);

  try {
    const branches = await api.get<{ id: number; name: string }[]>('branches?per_page=100');
    const branch = branches.find((item) => item.name === 'Casa central');
    const vets = await api.get<{ id: number; user: { name: string } }[]>(
      'veterinarians?per_page=100',
    );
    const vet = vets.find((item) => item.user.name === USERS.vet.name);

    if (branch === undefined || vet === undefined) {
      throw new Error('Sin la Casa central o sin la ficha de vet-a en syncvet_e2e.');
    }

    const schedule = await api.post<{ id: number }>('schedules', {
      branch_id: branch.id,
      name,
      veterinarian_id: vet.id,
      slot_minutes: 30,
      hours: [1, 2, 3, 4, 5, 6, 7].map((weekday) => ({
        weekday,
        starts_at: '00:00',
        ends_at: '23:55',
      })),
    });
    const day = asuncionDay();
    const suffix = name.slice(-6);
    const turn = (at: string, contact: string, phone: string) =>
      api.post<{ id: number }>('appointments', {
        schedule_id: schedule.id,
        contact_name: contact,
        contact_phone: phone,
        starts_at: `${day}T${at}:00-03:00`,
      });
    const result: AgendaDay = {
      scheduleId: schedule.id,
      day,
      plain: `Contacto nueve ${suffix}`,
      inBlock: `Contacto diez ${suffix}`,
      cancelled: `Contacto once ${suffix}`,
    };

    const plain = await turn('09:00', result.plain, '+595981100091');
    await api.post(`appointments/${String(plain.id)}/confirm`, {});
    await turn('10:00', result.inBlock, '+595981100092');
    const cancelled = await turn('11:00', result.cancelled, '+595981100093');
    await api.post(`appointments/${String(cancelled.id)}/cancel`, {
      cancellation_reason: 'Prueba de la agenda',
      scope: 'single',
    });
    await api.post(`schedules/${String(schedule.id)}/blocks`, {
      starts_at: `${day}T10:00:00-03:00`,
      ends_at: `${day}T11:00:00-03:00`,
      reason: 'Capacitación E2E',
    });

    return result;
  } finally {
    await api.dispose();
  }
}

/**
 * La limpieza de `createAgendaDay` (o de una corrida que se cortó): los turnos no se eliminan (la API no tiene baja: se
 * cancelan) y una agenda con turnos tampoco (`SCHEDULE_IN_USE`), así que se cancelan los que siguen, se eliminan los
 * bloqueos y se inactiva la agenda. syncvet_e2e se vacía en la próxima corrida; la demo no se toca. `untilOffset`: hasta qué día
 * (desde hoy) se buscan los turnos para cancelarlos (las series de la 3.6 van varias semanas adelante; el listado acepta hasta
 * 31 días).
 */
export async function retireAgendasNamed(prefix: string, untilOffset = 1): Promise<void> {
  const api = await apiAs(USERS.admin);

  try {
    const schedules = await api.get<{ id: number; name: string }[]>(
      `schedules?per_page=100&search=${encodeURIComponent(prefix)}`,
    );

    for (const schedule of schedules.filter((item) => item.name.startsWith(prefix))) {
      const id = String(schedule.id);
      const appointments = await api.get<{ id: number; status: { value: string } }[]>(
        `appointments?schedule_id=${id}&from=${asuncionDay(-1)}&to=${asuncionDay(untilOffset)}&per_page=500`,
      );

      for (const appointment of appointments) {
        if (
          ['pending_confirmation', 'confirmed', 'checked_in'].includes(appointment.status.value)
        ) {
          await api.post(`appointments/${String(appointment.id)}/cancel`, {
            cancellation_reason: 'Limpieza de la prueba',
            scope: 'single',
          });
        }
      }

      for (const block of await api.get<{ id: number }[]>(`schedules/${id}/blocks?per_page=100`)) {
        await api.delete(`schedule-blocks/${String(block.id)}`);
      }

      await api.patch(`schedules/${id}`, { is_active: false });
    }
  } finally {
    await api.dispose();
  }
}

/** Lo que arma `createStatesDay`: la agenda, el día y los ids de los tres turnos. */
export interface StatesDay {
  scheduleId: number;
  day: string;
  /** 00:30 a 01:00, a confirmar, con Firulais (del DemoSeeder): se confirma, se ficha y lo atiende vet-a. */
  toAttend: number;
  /** 00:00 a 00:30, confirmado, con un contacto: ya empezó (se marca ausente y se deshace). */
  toNoShow: number;
  /** 01:00 a 01:30, confirmado, con un contacto: se cancela con motivo. */
  toCancel: number;
  /** El contacto de `toNoShow` y el de `toCancel`. */
  noShowContact: string;
  cancelContact: string;
}

/**
 * Los turnos de los estados (3.5) por la API, en syncvet_e2e (como el administrador): una agenda de `vet-a` en la Casa
 * central abierta todos los días de 00:00 a 23:55 y tres turnos de hoy a las 00:00, 00:30 y 01:00 (la API acepta hoy una
 * hora que ya pasó; así el de las 00:00 ya empezó y se puede marcar ausente sin depender de la hora en que corre). La
 * limpieza es `retireAgendasNamed`.
 */
export async function createStatesDay(name: string): Promise<StatesDay> {
  const api = await apiAs(USERS.admin);

  try {
    const branches = await api.get<{ id: number; name: string }[]>('branches?per_page=100');
    const branch = branches.find((item) => item.name === 'Casa central');
    const vets = await api.get<{ id: number; user: { name: string } }[]>(
      'veterinarians?per_page=100',
    );
    const vet = vets.find((item) => item.user.name === USERS.vet.name);
    const patients = await api.get<{ id: number; name: string }[]>(
      'patients?search=Firulais&per_page=10',
    );
    const patient = patients.find((item) => item.name === 'Firulais');

    if (branch === undefined || vet === undefined || patient === undefined) {
      throw new Error('Sin la Casa central, la ficha de vet-a o Firulais en syncvet_e2e.');
    }

    const schedule = await api.post<{ id: number }>('schedules', {
      branch_id: branch.id,
      name,
      veterinarian_id: vet.id,
      slot_minutes: 30,
      hours: [1, 2, 3, 4, 5, 6, 7].map((weekday) => ({
        weekday,
        starts_at: '00:00',
        ends_at: '23:55',
      })),
    });
    const day = asuncionDay();
    const suffix = name.slice(-6);
    const at = (clock: string) => `${day}T${clock}:00-03:00`;
    const noShowContact = `Contacto ausente ${suffix}`;
    const cancelContact = `Contacto cancelar ${suffix}`;

    const toAttend = await api.post<{ id: number }>('appointments', {
      schedule_id: schedule.id,
      patient_id: patient.id,
      starts_at: at('00:30'),
    });
    const toNoShow = await api.post<{ id: number }>('appointments', {
      schedule_id: schedule.id,
      contact_name: noShowContact,
      contact_phone: '+595981100094',
      starts_at: at('00:00'),
    });
    const toCancel = await api.post<{ id: number }>('appointments', {
      schedule_id: schedule.id,
      contact_name: cancelContact,
      contact_phone: '+595981100095',
      starts_at: at('01:00'),
    });

    for (const turn of [toNoShow, toCancel]) {
      await api.post(`appointments/${String(turn.id)}/confirm`, {});
    }

    return {
      scheduleId: schedule.id,
      day,
      toAttend: toAttend.id,
      toNoShow: toNoShow.id,
      toCancel: toCancel.id,
      noShowContact,
      cancelContact,
    };
  } finally {
    await api.dispose();
  }
}

/** Lo que arma `createSeriesAgenda` (3.6). */
export interface SeriesAgenda {
  scheduleId: number;
  /** El día del primer turno de las series: dentro de una semana. */
  firstDay: string;
  /** El turno que ocupa el tercer día de una serie a las 10:00 (la serie que choca). */
  busyDay: string;
  /** La serie de 4 turnos semanales a las 11:00, creada por la API: sus ids por fecha (se cancela desde el tercero). */
  seriesIds: number[];
}

/**
 * Las series (3.6) por la API, en syncvet_e2e (como el administrador): una agenda de `vet-a` en la Casa central abierta todos
 * los días de 08:00 a 20:00; un turno de un contacto dentro de tres semanas a las 10:00 (la serie de las 10:00 choca ahí) y una
 * serie de 4 turnos semanales a las 11:00 desde dentro de una semana. La limpieza es `retireAgendasNamed(prefijo, 29)`.
 */
export async function createSeriesAgenda(name: string): Promise<SeriesAgenda> {
  const api = await apiAs(USERS.admin);

  try {
    const branches = await api.get<{ id: number; name: string }[]>('branches?per_page=100');
    const branch = branches.find((item) => item.name === 'Casa central');
    const vets = await api.get<{ id: number; user: { name: string } }[]>(
      'veterinarians?per_page=100',
    );
    const vet = vets.find((item) => item.user.name === USERS.vet.name);

    if (branch === undefined || vet === undefined) {
      throw new Error('Sin la Casa central o la ficha de vet-a en syncvet_e2e.');
    }

    const schedule = await api.post<{ id: number }>('schedules', {
      branch_id: branch.id,
      name,
      veterinarian_id: vet.id,
      slot_minutes: 30,
      hours: [1, 2, 3, 4, 5, 6, 7].map((weekday) => ({
        weekday,
        starts_at: '08:00',
        ends_at: '20:00',
      })),
    });
    const firstDay = asuncionDay(7);
    const busyDay = asuncionDay(21);
    const suffix = name.slice(-6);

    await api.post('appointments', {
      schedule_id: schedule.id,
      contact_name: `Contacto ocupado ${suffix}`,
      contact_phone: '+595981100096',
      starts_at: `${busyDay}T10:00:00-03:00`,
    });
    const series = await api.post<{ id: number }[]>('appointments', {
      schedule_id: schedule.id,
      contact_name: `Contacto serie ${suffix}`,
      contact_phone: '+595981100097',
      starts_at: `${firstDay}T11:00:00-03:00`,
      recurrence: { every_weeks: 1, count: 4 },
    });

    return { scheduleId: schedule.id, firstDay, busyDay, seriesIds: series.map((item) => item.id) };
  } finally {
    await api.dispose();
  }
}

/** Lo que arma `createQueueDay` (3.7): las dos agendas y los turnos de hoy, por su paciente o su contacto. */
export interface QueueDay {
  day: string;
  /** La agenda de vet-a y la agenda sin veterinario (la que vet-a ve por defecto: `includes_unassigned`). */
  vetSchedule: string;
  unassignedSchedule: string;
  /** 02:00, Firulais en la agenda de vet-a, ya fichado: vet-a lo marca atendido desde la cola. */
  toAttendId: number;
  /** 02:30, Rocky en la agenda de vet-a, confirmado: la recepción lo ficha desde la cola. */
  toCheckInId: number;
  /** 03:00, un contacto en la agenda sin veterinario, confirmado. */
  unassignedContact: string;
}

/**
 * La cola del día (3.7) por la API, en syncvet_e2e (como el administrador): en la Casa central, una agenda de `vet-a` y otra
 * sin veterinario (con un servicio de la corrida: una agenda necesita veterinario o servicio), abiertas todos los días de 00:00 a 23:55, con tres turnos de hoy (la API acepta hoy una hora que ya pasó):
 * Firulais a las 02:00, fichado; Rocky a las 02:30, confirmado; un contacto a las 03:00 en la agenda sin veterinario. La
 * limpieza es `retireAgendasNamed`, que no cancela el atendido: por eso las horas no se pisan con las de vet-a de
 * `createStatesDay` (00:00 a 01:30), que corre después en la corrida completa (cierre de la F3, desvío 9).
 */
export async function createQueueDay(name: string): Promise<QueueDay> {
  const api = await apiAs(USERS.admin);

  try {
    const branches = await api.get<{ id: number; name: string }[]>('branches?per_page=100');
    const branch = branches.find((item) => item.name === 'Casa central');
    const vets = await api.get<{ id: number; user: { name: string } }[]>(
      'veterinarians?per_page=100',
    );
    const vet = vets.find((item) => item.user.name === USERS.vet.name);
    const patientNamed = async (patientName: string) =>
      (
        await api.get<{ id: number; name: string }[]>(
          `patients?search=${encodeURIComponent(patientName)}&per_page=10`,
        )
      ).find((item) => item.name === patientName);
    const firulais = await patientNamed('Firulais');
    const rocky = await patientNamed('Rocky');

    if (
      branch === undefined ||
      vet === undefined ||
      firulais === undefined ||
      rocky === undefined
    ) {
      throw new Error('Sin la Casa central, la ficha de vet-a, Firulais o Rocky en syncvet_e2e.');
    }

    const hours = [1, 2, 3, 4, 5, 6, 7].map((weekday) => ({
      weekday,
      starts_at: '00:00',
      ends_at: '23:55',
    }));
    const vetSchedule = `${name} vet-a`;
    const unassignedSchedule = `${name} sin veterinario`;
    const own = await api.post<{ id: number }>('schedules', {
      branch_id: branch.id,
      name: vetSchedule,
      veterinarian_id: vet.id,
      slot_minutes: 30,
      hours,
    });
    // Una agenda sin veterinario necesita un servicio (P10): uno propio de la corrida.
    const service = await api.post<{ id: number }>('services', {
      name: `${name} servicio`,
      category: 'consultation',
      standard_price: '100000',
    });
    const unassigned = await api.post<{ id: number }>('schedules', {
      branch_id: branch.id,
      name: unassignedSchedule,
      service_id: service.id,
      slot_minutes: 30,
      hours,
    });
    const day = asuncionDay();
    const at = (clock: string) => `${day}T${clock}:00-03:00`;
    const unassignedContact = `Contacto cola ${name.slice(-6)}`;

    const toAttend = await api.post<{ id: number }>('appointments', {
      schedule_id: own.id,
      patient_id: firulais.id,
      starts_at: at('02:00'),
    });
    await api.post(`appointments/${String(toAttend.id)}/check-in`, {});
    const toCheckIn = await api.post<{ id: number }>('appointments', {
      schedule_id: own.id,
      patient_id: rocky.id,
      starts_at: at('02:30'),
    });
    await api.post(`appointments/${String(toCheckIn.id)}/confirm`, {});
    const contact = await api.post<{ id: number }>('appointments', {
      schedule_id: unassigned.id,
      contact_name: unassignedContact,
      contact_phone: '+595981100097',
      starts_at: at('03:00'),
    });
    await api.post(`appointments/${String(contact.id)}/confirm`, {});

    return {
      day,
      vetSchedule,
      unassignedSchedule,
      toAttendId: toAttend.id,
      toCheckInId: toCheckIn.id,
      unassignedContact,
    };
  } finally {
    await api.dispose();
  }
}
