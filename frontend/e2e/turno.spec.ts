import { expect, test, type Page } from '@playwright/test';
import { createAgendaDay, retireAgendasNamed, slotRange, type AgendaDay } from './support/api';
import { login, USERS } from './support/session';

// El turno (3.4, F3) contra la API real: el ayudante de la 3.2 crea por la API, en syncvet_e2e, una agenda de vet-a abierta
// todo el día con tres turnos de hoy (uno dentro de un bloqueo). La recepción da un turno a Firulais (del DemoSeeder) desde una
// celda libre de mañana y lo ve en la grilla; otro en el mismo horario recibe el 409 y se da como sobreturno; el primero se
// reprograma a otra hora libre; el turno que quedó dentro del bloqueo se abre desde la lista, avisa "Dentro de un bloqueo" y se
// reprograma fuera de él. La limpieza cancela los turnos, elimina el bloqueo e inactiva la agenda; la demo no se toca.

const PREFIX = 'Turno E2E';
const NAME = `${PREFIX} ${String(Date.now()).slice(-6)}`;
const LOADED = { timeout: 30_000 };

let data: AgendaDay;

const turn = (page: Page, text: string) => page.locator('.agenda-item', { hasText: text });

async function openTomorrow(page: Page) {
  const tomorrow = data.tomorrow.day;
  await page.goto(`/agenda?fecha=${tomorrow}&agenda=${String(data.scheduleId)}`);
  await expect(page.locator('.agenda-column-name')).toHaveText([NAME], LOADED);
}

/** La celda libre de mañana a esa hora (su nombre accesible empieza con la hora y la agenda). */
const cell = (page: Page, clock: string) =>
  page.locator(`[role="gridcell"][aria-label^="${clock}, ${NAME}"]`);

test.beforeAll(async () => {
  data = await createAgendaDay(NAME);
});

test.afterAll(async () => {
  await retireAgendasNamed(PREFIX);
});

test('la recepción da un turno desde una celda, el 409 con el sobreturno y la reprogramación', async ({
  page,
}) => {
  test.setTimeout(180_000);
  // Dos horas libres de vet-a mañana, con una hora entre ellas (freeStart): el alta y la reprogramación.
  const { day: tomorrow, first, later } = data.tomorrow;
  await login(page, USERS.recepcion);
  await openTomorrow(page);

  // Enter en la celda libre: el alta con la agenda y el inicio.
  await cell(page, first).focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(
    new RegExp(
      `/turnos/nuevo\\?agenda=${String(data.scheduleId)}&inicio=${tomorrow}T${first.replace(':', '%3A')}`,
    ),
  );
  await expect(page.getByLabel('Agenda', { exact: true })).toHaveValue(
    String(data.scheduleId),
    LOADED,
  );
  await expect(page.locator(`.appointment-slot-options input[value="${first}"]`)).toBeChecked(
    LOADED,
  );
  await page.getByLabel('Buscar el paciente').fill('Firulais');
  await page.locator('.appointment-who-options button', { hasText: 'Firulais' }).first().click();
  const post = page.waitForRequest(
    (request) => request.method() === 'POST' && request.url().endsWith('/api/appointments'),
  );
  await page.getByRole('button', { name: 'Dar el turno' }).click();
  expect((await post).postDataJSON()).toMatchObject({
    schedule_id: data.scheduleId,
    starts_at: `${tomorrow}T${first}:00.000-03:00`,
    ends_at: `${tomorrow}T${slotRange(first).slice(-5)}:00.000-03:00`,
  });
  await expect(page).toHaveURL(/\/turnos\/\d+$/, LOADED);
  await expect(page.locator('.appointment-data')).toContainText(`${slotRange(first)} (30 minutos)`);
  const firstUrl = page.url();

  // En la grilla de mañana.
  await openTomorrow(page);
  await expect(turn(page, 'Firulais')).toBeVisible(LOADED);

  // Otro turno en el mismo horario: el 409 y "Dar como sobreturno".
  await page.goto(`/turnos/nuevo?agenda=${String(data.scheduleId)}&inicio=${tomorrow}T${first}`);
  await page.getByText('Primera vez, sin cliente').click();
  await page.getByLabel('Nombre de contacto').fill('Sobreturno E2E');
  await page.getByLabel('Teléfono de contacto').fill('+595981100099');
  await expect(page.getByLabel('Escribí la hora')).toHaveValue(first, LOADED);
  await page.getByRole('button', { name: 'Dar el turno' }).click();
  await expect(page.locator('.appointment-time-error')).toHaveText(
    'El horario ya está ocupado: elegí otro o dalo como sobreturno.',
    LOADED,
  );
  await expect(page.getByLabel('Nombre de contacto')).toHaveValue('Sobreturno E2E');
  await page.locator('.appointment-overbook').click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toContainText('¿Querés darlo igual como sobreturno?');
  await dialog.getByRole('button', { name: 'Dar como sobreturno' }).click();
  await expect(page).toHaveURL(/\/turnos\/\d+$/, LOADED);
  await expect(page.locator('.appointment-overbooked')).toHaveText('Sobreturno');

  // Reprogramar el primero una hora después (libre).
  await page.goto(firstUrl);
  await page.locator('.appointment-reschedule').click();
  await expect(page.getByRole('heading', { name: 'Reprogramar el turno' })).toBeVisible();
  await expect(page.getByLabel('Fecha', { exact: true })).toBeFocused();
  await page.locator('.appointment-slot-options label', { hasText: later }).click();
  const patch = page.waitForRequest((request) => request.method() === 'PATCH');
  await page.getByRole('button', { name: 'Reprogramar', exact: true }).click();
  const body = (await patch).postDataJSON() as Record<string, unknown>;
  expect(body).toMatchObject({ starts_at: `${tomorrow}T${later}:00.000-03:00` });
  expect(body).not.toHaveProperty('schedule_id');
  await expect(page.locator('.appointment-data')).toContainText(slotRange(later), LOADED);

  // La grilla de mañana se actualizó: Firulais en la hora nueva y el sobreturno en la primera.
  await openTomorrow(page);
  await expect(turn(page, 'Firulais')).toHaveAttribute(
    'aria-label',
    new RegExp(`^${slotRange(later)}, Firulais`),
    LOADED,
  );
  await expect(turn(page, 'Sobreturno E2E')).toContainText('Sobreturno');
});

test('el turno que quedó dentro de un bloqueo: desde la lista al detalle, con el aviso, y reprogramado fuera de él', async ({
  page,
}) => {
  test.setTimeout(120_000);
  await login(page, USERS.recepcion);
  await page.goto(`/turnos?desde=${data.day}&hasta=${data.day}&agenda=${String(data.scheduleId)}`);
  const row = page.locator('tbody tr', { hasText: data.inBlock });
  await expect(row).toContainText('Dentro de un bloqueo', LOADED);
  await row.locator('.appointment-open').click();
  await page.locator('.agenda-panel-actions .appointment-view').click();

  await expect(page.locator('.appointment-in-block')).toContainText('Dentro de un bloqueo', LOADED);
  await page.locator('.appointment-actions .appointment-reschedule').click();
  // Hoy, a una hora libre fuera del bloqueo (la API acepta hoy una hora que ya pasó).
  await page.locator('.appointment-slot-options label', { hasText: 'Otra hora' }).click();
  await page.getByLabel('Escribí la hora').fill(data.times.free);
  await page.getByRole('button', { name: 'Reprogramar', exact: true }).click();

  await expect(page.locator('.appointment-data')).toContainText(slotRange(data.times.free), LOADED);
  await expect(page.locator('.appointment-in-block')).toHaveCount(0, LOADED);
});
