import { expect, test, type Page } from '@playwright/test';
import { createStatesDay, retireAgendasNamed, type StatesDay } from './support/api';
import { login, logout, USERS } from './support/session';

// Los estados del turno (3.5, F3) contra la API real: el ayudante crea por la API, en syncvet_e2e, una agenda de vet-a abierta
// todo el día con tres turnos de hoy (00:00, 00:30 y 01:00: ya empezaron). La recepción confirma y ficha el de Firulais desde
// el panel de la agenda y vet-a, en otro contexto, lo marca atendido desde el detalle; la recepción marca ausente el de las
// 00:00 y lo deshace; cancela el de las 01:00 con motivo desde el panel y lo ve oculto en la grilla, tachado con "Mostrar los
// cancelados" y en la lista. La limpieza cancela lo que sigue, inactiva la agenda; la demo no se toca.

const PREFIX = 'Estados E2E';
const NAME = `${PREFIX} ${String(Date.now()).slice(-6)}`;
const LOADED = { timeout: 30_000 };

let data: StatesDay;

const turn = (page: Page, text: string) => page.locator('.agenda-item', { hasText: text });
const panel = (page: Page) => page.locator('.agenda-panel');
const dialog = (page: Page) => page.getByRole('dialog');

async function openToday(page: Page, extra = '') {
  await page.goto(`/agenda?fecha=${data.day}&agenda=${String(data.scheduleId)}${extra}`);
  await expect(page.locator('.agenda-column-name')).toHaveText([NAME], LOADED);
}

test.beforeAll(async () => {
  data = await createStatesDay(NAME);
});

test.afterAll(async () => {
  await retireAgendasNamed(PREFIX);
});

test('la recepción confirma y ficha desde el panel; vet-a lo marca atendido en otro contexto', async ({
  page,
  browser,
}) => {
  test.setTimeout(180_000);
  await login(page, USERS.recepcion);
  await openToday(page);

  await turn(page, 'Firulais').click();
  await expect(panel(page).locator('.appointment-status-label')).toHaveText('A confirmar');
  await panel(page).getByRole('button', { name: 'Confirmar', exact: true }).click();
  await expect(page.getByText('Turno confirmado.')).toBeVisible();
  await expect(panel(page).locator('.appointment-status-label')).toHaveText('Confirmado');
  // La grilla, sin recargar.
  await expect(turn(page, 'Firulais')).toContainText('Confirmado');

  await panel(page).getByRole('button', { name: 'Fichar', exact: true }).click();
  await expect(page.getByText('Turno fichado.')).toBeVisible();
  await expect(turn(page, 'Firulais')).toContainText('Fichado');
  // La recepción no marca atendido.
  await expect(panel(page).getByRole('button', { name: 'Marcar atendido' })).toHaveCount(0);

  const other = await browser.newContext();

  try {
    const vet = await other.newPage();
    await login(vet, USERS.vet);
    await vet.goto(`/turnos/${String(data.toAttend)}`);
    await expect(vet.locator('.appointment-title .appointment-status-label')).toHaveText(
      'Fichado',
      LOADED,
    );
    await vet.getByRole('button', { name: 'Marcar atendido' }).click();
    await expect(vet.getByText('Turno marcado como atendido.')).toBeVisible();
    await expect(vet.locator('.appointment-title .appointment-status-label')).toHaveText(
      'Atendido',
    );
    await expect(vet.locator('.appointment-transitions')).toHaveCount(0);
    await logout(vet);
  } finally {
    await other.close();
  }
});

test('ausente en un turno ya empezado y deshacerlo, desde el detalle', async ({ page }) => {
  await login(page, USERS.recepcion);
  await page.goto(`/turnos/${String(data.toNoShow)}`);
  const status = page.locator('.appointment-title .appointment-status-label');
  await expect(status).toHaveText('Confirmado', LOADED);

  await page.getByRole('button', { name: 'Ausente', exact: true }).click();
  await expect(page.getByText('Turno marcado como ausente.')).toBeVisible();
  await expect(status).toHaveText('Ausente');

  await page.getByRole('button', { name: 'Deshacer el ausente' }).click();
  await expect(dialog(page)).toContainText('El turno vuelve a confirmado.');
  await dialog(page).getByRole('button', { name: 'Deshacer el ausente' }).click();
  await expect(
    page.getByText('Se deshizo el ausente: el turno volvió a confirmado.'),
  ).toBeVisible();
  await expect(status).toHaveText('Confirmado');
});

test('cancelar con motivo desde el panel: oculto en la grilla, tachado con los cancelados y en la lista', async ({
  page,
}) => {
  await login(page, USERS.recepcion);
  await openToday(page);

  await turn(page, data.cancelContact).click();
  await panel(page).getByRole('button', { name: 'Cancelar el turno' }).click();
  const reason = dialog(page).getByLabel('Motivo de la cancelación');
  await expect(reason).toBeFocused();
  // Sin motivo, el mensaje y ningún pedido.
  await dialog(page).getByRole('button', { name: 'Cancelar el turno' }).click();
  await expect(dialog(page)).toContainText('Ingresá el motivo de la cancelación.');
  await reason.fill('El cliente avisó por teléfono');
  await dialog(page).getByRole('button', { name: 'Cancelar el turno' }).click();
  await expect(page.getByText('Turno cancelado.')).toBeVisible();
  await expect(dialog(page)).toHaveCount(0);

  // Oculto en la grilla (sin recargar); el panel sigue con su estado.
  await expect(turn(page, data.cancelContact)).toHaveCount(0);
  await expect(panel(page).locator('.appointment-status-label')).toHaveText('Cancelado');

  await openToday(page, '&cancelados=1');
  await expect(turn(page, data.cancelContact)).toHaveClass(/agenda-item-cancelled/);

  await page.goto(`/turnos?desde=${data.day}&hasta=${data.day}&agenda=${String(data.scheduleId)}`);
  const row = page.locator('tbody tr', { hasText: data.cancelContact });
  await expect(row).toHaveClass('appointment-row-cancelled', LOADED);
  await expect(row.locator('.appointment-status-label')).toHaveText('Cancelado');
});
