import { expect, test, type Page } from '@playwright/test';
import { createAgendaDay, retireAgendasNamed, type AgendaDay } from './support/api';
import { login, USERS } from './support/session';

// La semana y la lista de turnos (3.3, F3) contra la API real: el ayudante de la 3.2 crea por la API, en syncvet_e2e, una
// agenda de vet-a con tres turnos de hoy (uno confirmado, uno dentro de un bloqueo y uno cancelado) y el bloqueo. El
// administrador pasa del día a la semana (la fecha en la URL), elige la agenda, ve los turnos en la columna de hoy con la
// marca del bloqueo y sin el cancelado, cambia de semana con el teclado y pasa a la lista conservando la agenda, donde filtra
// por estado y busca por el contacto. La limpieza cancela los turnos, elimina el bloqueo e inactiva la agenda.

const PREFIX = 'Semana lista E2E';
const NAME = `${PREFIX} ${String(Date.now()).slice(-6)}`;

/** Lo que espera un pedido a la API: hasta 30 s (el backend de Playwright a veces tarda). */
const LOADED = { timeout: 30_000 };

let data: AgendaDay;

const turn = (page: Page, contact: string) => page.locator('.agenda-item', { hasText: contact });
const row = (page: Page, contact: string) => page.locator('tbody tr', { hasText: contact });

/** El lunes de la semana del día (yyyy-MM-dd), contado sin horas. */
function mondayOf(day: string): string {
  const date = new Date(`${day}T12:00:00Z`);
  const weekday = date.getUTCDay() === 0 ? 7 : date.getUTCDay();
  date.setUTCDate(date.getUTCDate() + 1 - weekday);

  return date.toISOString().slice(0, 10);
}

function plusDays(day: string, amount: number): string {
  const date = new Date(`${day}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + amount);

  return date.toISOString().slice(0, 10);
}

test.beforeAll(async () => {
  data = await createAgendaDay(NAME);
});

test.afterAll(async () => {
  await retireAgendasNamed(PREFIX);
});

test('la semana de una agenda: los turnos de hoy, el bloqueo, los cancelados y la semana siguiente con el teclado', async ({
  page,
}) => {
  test.setTimeout(120_000);
  await login(page, USERS.admin);
  await page.goto(`/agenda?fecha=${data.day}`);
  await page.locator('a.agenda-view-semana').click();

  await expect(page).toHaveURL(new RegExp(`vista=semana&fecha=${data.day}`));
  await page.getByLabel('Agenda', { exact: true }).selectOption({ label: NAME });
  await expect(page).toHaveURL(new RegExp(`agenda=${String(data.scheduleId)}`));
  await expect(page.locator('.agenda-column-name')).toHaveCount(7, LOADED);
  await expect(page.locator('.agenda-column-name').first()).toHaveText(/^Lun \d+$/);
  await expect(page.locator('.agenda-column-head', { hasText: 'Hoy' })).toHaveCount(1);

  await expect(turn(page, data.plain)).toBeVisible(LOADED);
  await expect(turn(page, data.plain)).toHaveAttribute(
    'aria-label',
    new RegExp(`^09:00 a 09:30, ${data.plain}, Confirmado, ${NAME}, `),
  );
  await expect(turn(page, data.inBlock)).toContainText('Dentro de un bloqueo');
  await expect(turn(page, data.plain)).not.toContainText('Dentro de un bloqueo');
  await expect(turn(page, data.cancelled)).toHaveCount(0);
  await page.getByLabel('Mostrar los cancelados').check();
  await expect(turn(page, data.cancelled)).toBeVisible();

  // Av Pág a la semana siguiente (sin los turnos) y Re Pág de vuelta; un solo punto de tabulación.
  await turn(page, data.plain).focus();
  await page.keyboard.press('PageDown');
  await expect(page).toHaveURL(new RegExp(`fecha=${plusDays(data.day, 7)}`));
  await expect(turn(page, data.plain)).toHaveCount(0, LOADED);
  await page.keyboard.press('PageUp');
  await expect(page).toHaveURL(new RegExp(`fecha=${data.day}`));
  await expect(turn(page, data.plain)).toBeVisible(LOADED);
  await expect(page.locator('[role="grid"] [tabindex="0"]')).toHaveCount(1);
});

test('la lista: desde la semana con la agenda, filtrada por estado y con la búsqueda', async ({
  page,
}) => {
  test.setTimeout(120_000);
  await login(page, USERS.admin);
  await page.goto(`/agenda?vista=semana&fecha=${data.day}&agenda=${String(data.scheduleId)}`);
  await expect(turn(page, data.plain)).toBeVisible(LOADED);
  await page.locator('a.agenda-view-lista').click();

  const monday = mondayOf(data.day);
  await expect(page).toHaveURL(
    new RegExp(
      `/turnos\\?desde=${monday}&hasta=${plusDays(monday, 6)}&agenda=${String(data.scheduleId)}`,
    ),
  );
  await expect(row(page, data.plain)).toBeVisible(LOADED);
  await expect(row(page, data.inBlock)).toContainText('Dentro de un bloqueo');
  // La lista muestra los cancelados (pregunta 5).
  await expect(row(page, data.cancelled)).toBeVisible();

  await page.getByLabel('Confirmado', { exact: true }).check();
  await expect(page).toHaveURL(/estados=confirmado/);
  await expect(row(page, data.plain)).toBeVisible(LOADED);
  await expect(row(page, data.inBlock)).toHaveCount(0, LOADED);
  await expect(row(page, data.cancelled)).toHaveCount(0);
  await page.getByLabel('Confirmado', { exact: true }).uncheck();

  await page.getByLabel('Buscar turnos').fill(data.inBlock);
  await expect(page).toHaveURL(/buscar=/);
  await expect(row(page, data.inBlock)).toBeVisible(LOADED);
  await expect(row(page, data.plain)).toHaveCount(0, LOADED);
  await expect(page.locator('tbody tr')).toHaveCount(1);
});
