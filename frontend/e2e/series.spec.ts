import { expect, test, type Page } from '@playwright/test';
import { createSeriesAgenda, retireAgendasNamed, type SeriesAgenda } from './support/api';
import { login, USERS } from './support/session';

// Las series (3.6, F3) contra la API real: el ayudante crea por la API, en syncvet_e2e, una agenda de vet-a abierta de 08:00 a
// 20:00 todos los días, un turno dentro de tres semanas a las 10:00 y una serie de 4 turnos semanales a las 11:00. La
// recepción da desde el formulario una serie de 4 turnos semanales a Firulais (la vista previa, el 201 y la lista de la
// serie, entera, front-04); otra serie de las 10:00 choca con el turno de la tercera semana (la lista de conflictos y "Dar la
// serie con sobreturnos"); y cancela "Este y los siguientes" desde el tercer turno de la serie del ayudante. La limpieza
// cancela lo que sigue hasta dentro de 29 días e inactiva la agenda; la demo no se toca.

const PREFIX = 'Series E2E';
const NAME = `${PREFIX} ${String(Date.now()).slice(-6)}`;
const LOADED = { timeout: 30_000 };

let data: SeriesAgenda;

const dialog = (page: Page) => page.getByRole('dialog');

async function newAt(page: Page, day: string, clock: string) {
  await page.goto(`/turnos/nuevo?agenda=${String(data.scheduleId)}&inicio=${day}T${clock}`);
  await expect(page.getByLabel('Agenda', { exact: true })).toHaveValue(
    String(data.scheduleId),
    LOADED,
  );
}

async function repeat(page: Page, count: string) {
  await page.getByLabel('Repetir el turno (una serie)').check();
  await page.getByLabel('Cantidad de turnos', { exact: true }).fill(count);
}

test.beforeAll(async () => {
  data = await createSeriesAgenda(NAME);
});

test.afterAll(async () => {
  await retireAgendasNamed(PREFIX, 29);
});

test('una serie de 4 turnos semanales: la vista previa, el 201 y la lista de la serie entera', async ({
  page,
}) => {
  test.setTimeout(180_000);
  await login(page, USERS.recepcion);
  await newAt(page, data.firstDay, '09:00');
  await page.getByLabel('Buscar el paciente').fill('Firulais');
  await page.locator('.appointment-who-options button', { hasText: 'Firulais' }).first().click();
  await repeat(page, '4');

  await expect(page.locator('.appointment-series-summary')).toContainText('4 turnos, los ', LOADED);
  await expect(page.locator('.appointment-series-preview li')).toHaveCount(4);

  const post = page.waitForRequest(
    (request) => request.method() === 'POST' && request.url().endsWith('/api/appointments'),
  );
  await page.getByRole('button', { name: 'Dar el turno' }).click();
  expect((await post).postDataJSON()).toMatchObject({
    starts_at: `${data.firstDay}T09:00:00.000-03:00`,
    recurrence: { every_weeks: 1, count: 4, until: null },
  });
  await expect(page.getByText('Serie de 4 turnos creada correctamente.')).toBeVisible(LOADED);
  await expect(page).toHaveURL(/\/turnos\/\d+$/);
  await expect(page.locator('.appointment-series')).toContainText(
    'Turno 1 de 4 de una serie.',
    LOADED,
  );

  // La lista de la serie: los 4, aunque pasen de un mes desde hoy, sin "Desde" ni "Hasta".
  const list = page.waitForRequest(
    (request) =>
      request.url().includes('/api/appointments?') && request.url().includes('series_id='),
  );
  await page.getByRole('link', { name: 'Ver los turnos de la serie' }).click();
  const url = new URL((await list).url());
  expect(url.searchParams.has('from')).toBe(false);
  await expect(page.locator('tbody tr')).toHaveCount(4, LOADED);
  await expect(page.locator('tbody .agenda-item-series-flag')).toHaveCount(4);
  await expect(page.getByLabel('Desde')).toHaveCount(0);
});

test('una serie que choca con un turno: la lista de conflictos y "Dar la serie con sobreturnos"', async ({
  page,
}) => {
  test.setTimeout(120_000);
  await login(page, USERS.recepcion);
  await newAt(page, data.firstDay, '10:00');
  await page.getByText('Primera vez, sin cliente').click();
  await page.getByLabel('Nombre de contacto').fill('Serie que choca');
  await page.getByLabel('Teléfono de contacto').fill('+595981100098');
  await repeat(page, '3');
  await expect(page.locator('.appointment-series-preview li')).toHaveCount(3, LOADED);

  await page.getByRole('button', { name: 'Dar el turno' }).click();
  const conflicts = page.locator('.appointment-series-conflicts li');
  await expect(conflicts).toHaveCount(1, LOADED);
  await expect(conflicts).toContainText('10:00: El horario ya está ocupado');
  await expect(page.locator('.appointment-alert')).toBeFocused();

  await page.getByRole('button', { name: 'Dar la serie con sobreturnos' }).click();
  await expect(dialog(page)).toContainText('¿Querés dar la serie igual');
  await dialog(page).getByRole('button', { name: 'Dar la serie con sobreturnos' }).click();
  await expect(page.getByText('Serie de 3 turnos creada correctamente.')).toBeVisible(LOADED);
});

test('cancelar "Este y los siguientes" desde el tercer turno de la serie', async ({ page }) => {
  test.setTimeout(120_000);
  const third = data.seriesIds[2] ?? 0;
  await login(page, USERS.recepcion);
  await page.goto(`/turnos/${String(third)}`);
  await expect(page.locator('.appointment-series')).toContainText('Turno 3 de 4', LOADED);

  await page.getByRole('button', { name: 'Cancelar el turno' }).click();
  await dialog(page).getByLabel('Motivo de la cancelación').fill('El tratamiento terminó');
  await dialog(page).getByText('Este y los siguientes').click();
  await expect(dialog(page).locator('.appointment-cancel-scope-count')).toHaveText(
    'Se cancelan 2 turnos: este y 1 más de la serie.',
    LOADED,
  );
  await dialog(page).getByRole('button', { name: 'Cancelar 2 turnos' }).click();
  await expect(page.getByText('Se cancelaron 2 turnos de la serie.')).toBeVisible(LOADED);

  // En la lista de la serie: los dos primeros siguen, el tercero y el cuarto, cancelados.
  await page.getByRole('link', { name: 'Ver los turnos de la serie' }).click();
  const rows = page.locator('tbody tr');
  await expect(rows).toHaveCount(4, LOADED);
  await expect(rows.nth(0).locator('.appointment-status-label')).toHaveText('A confirmar');
  await expect(rows.nth(1).locator('.appointment-status-label')).toHaveText('A confirmar');
  await expect(rows.nth(2).locator('.appointment-status-label')).toHaveText('Cancelado');
  await expect(rows.nth(3).locator('.appointment-status-label')).toHaveText('Cancelado');
});
