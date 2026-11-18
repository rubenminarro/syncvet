import { expect, test, type Page } from '@playwright/test';
import { asuncionDay, createAgendaDay, retireAgendasNamed, type AgendaDay } from './support/api';
import { login, USERS } from './support/session';

// La agenda del día (3.2, F3) contra la API real: el ayudante crea por la API, en syncvet_e2e, una agenda de vet-a con tres
// turnos de hoy (uno común, uno dentro de un bloqueo y uno cancelado) y el bloqueo. El administrador entra por el menú, ve
// los turnos en su columna con la marca del bloqueo y sin el cancelado (hasta "Mostrar los cancelados"), va al día
// siguiente y vuelve con el teclado; vet-a los ve en su vista por defecto. La limpieza cancela los turnos, elimina el
// bloqueo e inactiva la agenda (los turnos no tienen baja); la demo no se toca.

const PREFIX = 'Agenda día E2E';
const NAME = `${PREFIX} ${String(Date.now()).slice(-6)}`;

/** Lo que espera un pedido a la API: hasta 30 s (el backend de Playwright a veces tarda; revisión de la 3.1b). */
const LOADED = { timeout: 30_000 };

let data: AgendaDay;

const turn = (page: Page, contact: string) => page.locator('.agenda-item', { hasText: contact });

test.beforeAll(async () => {
  data = await createAgendaDay(NAME);
});

test.afterAll(async () => {
  await retireAgendasNamed(PREFIX);
});

test('la agenda del día: los turnos en su columna, el bloqueo, los cancelados y el día siguiente con el teclado', async ({
  page,
}) => {
  test.setTimeout(120_000);
  await login(page, USERS.admin);
  await page.locator('.nav-link-title', { hasText: 'Agenda' }).click();

  await expect(page).toHaveURL(/\/agenda$/);
  await expect(page.locator('.agenda-column-name', { hasText: NAME })).toBeVisible(LOADED);
  await expect(turn(page, data.plain)).toBeVisible(LOADED);
  await expect(turn(page, data.plain)).toHaveAttribute(
    'aria-label',
    `09:00 a 09:30, ${data.plain}, Confirmado, ${NAME}`,
  );
  // El de las 10:00 quedó adentro del bloqueo: la marca, en el texto y en el nombre accesible.
  await expect(turn(page, data.inBlock)).toContainText('Dentro de un bloqueo');
  await expect(turn(page, data.inBlock)).toHaveAttribute('aria-label', /dentro de un bloqueo$/);
  await expect(turn(page, data.plain)).not.toContainText('Dentro de un bloqueo');
  await expect(page.locator('.agenda-block-label', { hasText: 'Capacitación E2E' })).toBeVisible();
  // El cancelado, oculto hasta "Mostrar los cancelados".
  await expect(turn(page, data.cancelled)).toHaveCount(0);
  await page.getByLabel('Mostrar los cancelados').check();
  await expect(page).toHaveURL(/cancelados=1/);
  await expect(turn(page, data.cancelled)).toBeVisible();
  await expect(turn(page, data.cancelled)).not.toContainText('Dentro de un bloqueo');

  // El teclado: el turno común con el foco, Av Pág al día siguiente (sin los turnos) y Re Pág de vuelta.
  await turn(page, data.plain).focus();
  await page.keyboard.press('PageDown');
  await expect(page).toHaveURL(new RegExp(`fecha=${asuncionDay(1)}`));
  await expect(turn(page, data.plain)).toHaveCount(0, LOADED);
  await page.keyboard.press('PageUp');
  await expect(page).toHaveURL(new RegExp(`fecha=${data.day}`));
  await expect(turn(page, data.plain)).toBeVisible(LOADED);
  // Un solo punto de tabulación en la grilla.
  await expect(page.locator('[role="grid"] [tabindex="0"]')).toHaveCount(1);
});

test('vet-a ve los turnos de su agenda en su vista por defecto', async ({ page }) => {
  test.setTimeout(90_000);
  await login(page, USERS.vet);
  await page.goto('/agenda');

  await expect(page.locator('.agenda-column-name', { hasText: NAME })).toBeVisible(LOADED);
  await expect(turn(page, data.plain)).toBeVisible(LOADED);
  await expect(turn(page, data.inBlock)).toContainText('Dentro de un bloqueo');
  await expect(page.getByLabel('Ver toda la sucursal')).not.toBeChecked();
});
