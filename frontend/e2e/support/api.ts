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

type Api = Awaited<ReturnType<typeof apiAs>>;

/** Los estados que ocupan el horario (`AppointmentStatus::occupiesSlot`): el atendido no se cancela y queda ocupando. */
const OCCUPYING = new Set(['pending_confirmation', 'confirmed', 'checked_in', 'attended']);

const minutesOf = (clock: string) => Number(clock.slice(0, 2)) * 60 + Number(clock.slice(3, 5));
const clockOf = (minutes: number) =>
  `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;

/** La hora `clock` (HH:mm) más `minutes` minutos, en el mismo día. */
export function addClock(clock: string, minutes: number): string {
  return clockOf(minutesOf(clock) + minutes);
}

/** "09:00 a 09:30": el rango de un turno de 30 minutos que empieza a `clock`, como lo muestran la grilla y el detalle. */
export const slotRange = (clock: string) => `${clock} a ${addClock(clock, 30)}`;

/** El día (yyyy-MM-dd) y los minutos desde la medianoche de un instante, en la hora de pared de Asunción. */
function wall(instant: Date): { day: string; minutes: number } {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone: 'America/Asuncion',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    })
      .formatToParts(instant)
      .map((part) => [part.type, part.value]),
  );

  return {
    day: `${parts.year ?? ''}-${parts.month ?? ''}-${parts.day ?? ''}`,
    minutes: Number(parts.hour) * 60 + Number(parts.minute),
  };
}

/**
 * El primer inicio (HH:mm, en pasos de 30 minutos entre `from` y `to`) en que el veterinario está libre en **todas** sus
 * agendas a las horas `start + offset` (turnos de 30 minutos), en cada uno de los `days`: lo que ya tiene (de este spec o de
 * otro, también un atendido, que no se cancela) no choca. Con `started`, el inicio ya pasó (un turno que "ya empezó", para el
 * ausente). Así ningún spec depende de lo que dejó otro, del orden ni de horas fijas (cierre de la F3, aislamiento).
 */
export async function freeStart(
  api: Api,
  veterinarianId: number,
  options: { days: string[]; offsets: number[]; from: string; to: string; started?: boolean },
): Promise<string> {
  const busy = new Map<string, [number, number][]>();

  for (const day of options.days) {
    const appointments = await api.get<
      { starts_at: string; ends_at: string; status: { value: string } }[]
    >(`appointments?veterinarian_id=${String(veterinarianId)}&from=${day}&to=${day}&per_page=500`);
    busy.set(
      day,
      appointments
        .filter((appointment) => OCCUPYING.has(appointment.status.value))
        .map((appointment) => {
          const start = wall(new Date(appointment.starts_at));
          const end = wall(new Date(appointment.ends_at));

          return [start.day === day ? start.minutes : 0, end.day === day ? end.minutes : 24 * 60];
        }),
    );
  }

  const now = wall(new Date());
  const last = Math.max(...options.offsets) + 30;

  for (let start = minutesOf(options.from); start + last <= minutesOf(options.to); start += 30) {
    if (options.started === true && (options.days[0] !== now.day || start > now.minutes)) {
      break;
    }

    const free = options.days.every((day) =>
      options.offsets.every((offset) =>
        (busy.get(day) ?? []).every(
          ([from, to]) => start + offset + 30 <= from || to <= start + offset,
        ),
      ),
    );

    if (free) {
      return clockOf(start);
    }
  }

  throw new Error(
    `Sin un horario libre del veterinario ${String(veterinarianId)} en ${options.days.join(', ')} (${options.from} a ${options.to}${options.started === true ? ', ya empezado' : ''}).`,
  );
}

/** Lo que arma `createAgendaDay`: la agenda, el día y los turnos (por su contacto). */
export interface AgendaDay {
  scheduleId: number;
  day: string;
  /** El primero (a `times.plain`), confirmado. */
  plain: string;
  /** Una hora después (`times.inBlock`), a confirmar, dentro del bloqueo de una hora que empieza con él. */
  inBlock: string;
  /** Dos horas después (`times.cancelled`, el fin del bloqueo), cancelado. */
  cancelled: string;
  /** Las horas de hoy (HH:mm): las de los tres turnos y una libre, fuera del bloqueo (`free`, una hora después del cancelado). */
  times: { plain: string; inBlock: string; cancelled: string; free: string };
  /** Mañana: dos horas libres de vet-a con una hora entre ellas (la del alta y la de la reprogramación de turno.spec.ts). */
  tomorrow: { day: string; first: string; later: string };
}

/**
 * La agenda del día de la 3.2 por la API, en syncvet_e2e (como el administrador): una agenda de `vet-a` en la Casa central,
 * abierta todos los días de 00:00 a 23:55 (así el caso no depende de la hora en que corre); tres turnos de hoy con un
 * contacto, a la primera hora libre de vet-a (`freeStart`), una y dos horas después (la API acepta hoy una hora que ya pasó);
 * el tercero, cancelado, y un bloqueo de una hora desde el segundo, creado después de él (la API lo crea igual y el turno queda
 * adentro). Hoy, Asunción es UTC-3 todo el año.
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
    const tomorrow = asuncionDay(1);
    const suffix = name.slice(-6);
    const first = await freeStart(api, vet.id, {
      days: [day],
      offsets: [0, 60, 120, 180],
      from: '00:00',
      to: '23:55',
    });
    const times = {
      plain: first,
      inBlock: addClock(first, 60),
      cancelled: addClock(first, 120),
      free: addClock(first, 180),
    };
    const next = await freeStart(api, vet.id, {
      days: [tomorrow],
      offsets: [0, 60],
      from: '00:00',
      to: '23:55',
    });
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
      times,
      tomorrow: { day: tomorrow, first: next, later: addClock(next, 60) },
    };

    const plain = await turn(times.plain, result.plain, '+595981100091');
    await api.post(`appointments/${String(plain.id)}/confirm`, {});
    await turn(times.inBlock, result.inBlock, '+595981100092');
    const cancelled = await turn(times.cancelled, result.cancelled, '+595981100093');
    await api.post(`appointments/${String(cancelled.id)}/cancel`, {
      cancellation_reason: 'Prueba de la agenda',
      scope: 'single',
    });
    await api.post(`schedules/${String(schedule.id)}/blocks`, {
      starts_at: `${day}T${times.inBlock}:00-03:00`,
      ends_at: `${day}T${times.cancelled}:00-03:00`,
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
  /** Media hora después del ausente, a confirmar, con Firulais (del DemoSeeder): se confirma, se ficha y lo atiende vet-a. */
  toAttend: number;
  /** El primero, confirmado, con un contacto: ya empezó (se marca ausente y se deshace). */
  toNoShow: number;
  /** Una hora después del ausente, confirmado, con un contacto: se cancela con motivo. */
  toCancel: number;
  /** El contacto de `toNoShow` y el de `toCancel`. */
  noShowContact: string;
  cancelContact: string;
}

/**
 * Los turnos de los estados (3.5) por la API, en syncvet_e2e (como el administrador): una agenda de `vet-a` en la Casa
 * central abierta todos los días de 00:00 a 23:55 y tres turnos de hoy seguidos, a la primera media hora libre de vet-a que ya
 * empezó (`freeStart` con `started`: la API acepta hoy una hora que ya pasó, y el primero se puede marcar ausente). La
 * limpieza es `retireAgendasNamed` (el atendido queda: lo saltea `freeStart` de los demás specs).
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
    const first = await freeStart(api, vet.id, {
      days: [day],
      offsets: [0, 30, 60],
      from: '00:00',
      to: '23:55',
      started: true,
    });
    const noShowContact = `Contacto ausente ${suffix}`;
    const cancelContact = `Contacto cancelar ${suffix}`;

    const toAttend = await api.post<{ id: number }>('appointments', {
      schedule_id: schedule.id,
      patient_id: patient.id,
      starts_at: at(addClock(first, 30)),
    });
    const toNoShow = await api.post<{ id: number }>('appointments', {
      schedule_id: schedule.id,
      contact_name: noShowContact,
      contact_phone: '+595981100094',
      starts_at: at(first),
    });
    const toCancel = await api.post<{ id: number }>('appointments', {
      schedule_id: schedule.id,
      contact_name: cancelContact,
      contact_phone: '+595981100095',
      starts_at: at(addClock(first, 60)),
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
  /** El turno que ocupa el tercer día de una serie a `times.clash` (la serie que choca). */
  busyDay: string;
  /** La serie de 4 turnos semanales a `times.api`, creada por la API: sus ids por fecha (se cancela desde el tercero). */
  seriesIds: number[];
  /**
   * Las horas (HH:mm), libres para vet-a en las cuatro semanas: la de la serie que da el spec (`first`), la que choca
   * (`clash`, una hora después) y la de la serie de la API (`api`, dos horas después).
   */
  times: { first: string; clash: string; api: string };
}

/**
 * Las series (3.6) por la API, en syncvet_e2e (como el administrador): una agenda de `vet-a` en la Casa central abierta todos
 * los días de 08:00 a 20:00; tres horas libres de vet-a en las cuatro semanas desde dentro de una semana (`freeStart`, una por
 * hora); un turno de un contacto dentro de tres semanas a la segunda (la serie de esa hora choca ahí) y una serie de 4 turnos
 * semanales a la tercera. La limpieza es `retireAgendasNamed(prefijo, 29)`.
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
    const first = await freeStart(api, vet.id, {
      days: [firstDay, asuncionDay(14), busyDay, asuncionDay(28)],
      offsets: [0, 60, 120],
      from: '08:00',
      to: '20:00',
    });
    const times = { first, clash: addClock(first, 60), api: addClock(first, 120) };

    await api.post('appointments', {
      schedule_id: schedule.id,
      contact_name: `Contacto ocupado ${suffix}`,
      contact_phone: '+595981100096',
      starts_at: `${busyDay}T${times.clash}:00-03:00`,
    });
    const series = await api.post<{ id: number }[]>('appointments', {
      schedule_id: schedule.id,
      contact_name: `Contacto serie ${suffix}`,
      contact_phone: '+595981100097',
      starts_at: `${firstDay}T${times.api}:00-03:00`,
      recurrence: { every_weeks: 1, count: 4 },
    });

    return {
      scheduleId: schedule.id,
      firstDay,
      busyDay,
      seriesIds: series.map((item) => item.id),
      times,
    };
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
  /** El primero (a la primera media hora libre de vet-a), Firulais en la agenda de vet-a, ya fichado: vet-a lo atiende desde la cola. */
  toAttendId: number;
  /** Media hora después, Rocky en la agenda de vet-a, confirmado: la recepción lo ficha desde la cola. */
  toCheckInId: number;
  /** Una hora después, un contacto en la agenda sin veterinario, confirmado. */
  unassignedContact: string;
}

/**
 * La cola del día (3.7) por la API, en syncvet_e2e (como el administrador): en la Casa central, una agenda de `vet-a` y otra
 * sin veterinario (con un servicio de la corrida: una agenda necesita veterinario o servicio), abiertas todos los días de 00:00 a 23:55, con tres turnos de hoy (la API acepta hoy una hora que ya pasó):
 * Firulais, fichado, a la primera media hora libre de vet-a (`freeStart`); Rocky, confirmado, media hora después; un contacto en
 * la agenda sin veterinario una hora después. La limpieza es `retireAgendasNamed`, que no cancela el atendido: los demás specs
 * lo saltean con `freeStart` (cierre de la F3, desvío 9).
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
    const first = await freeStart(api, vet.id, {
      days: [day],
      offsets: [0, 30],
      from: '00:00',
      to: '23:55',
    });
    const unassignedContact = `Contacto cola ${name.slice(-6)}`;

    const toAttend = await api.post<{ id: number }>('appointments', {
      schedule_id: own.id,
      patient_id: firulais.id,
      starts_at: at(first),
    });
    await api.post(`appointments/${String(toAttend.id)}/check-in`, {});
    const toCheckIn = await api.post<{ id: number }>('appointments', {
      schedule_id: own.id,
      patient_id: rocky.id,
      starts_at: at(addClock(first, 30)),
    });
    await api.post(`appointments/${String(toCheckIn.id)}/confirm`, {});
    const contact = await api.post<{ id: number }>('appointments', {
      schedule_id: unassigned.id,
      contact_name: unassignedContact,
      contact_phone: '+595981100097',
      starts_at: at(addClock(first, 60)),
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
