import { expect, test, type Page } from '@playwright/test';
import { createQueueDay, retireAgendasNamed, type QueueDay } from './support/api';
import { login, logout, USERS } from './support/session';

// La cola del día (3.7, F3) contra la API real: el ayudante crea por la API, en syncvet_e2e, una agenda de vet-a y otra sin
// veterinario en la Casa central, con Firulais fichado a la primera media hora libre de vet-a (`freeStart`), Rocky confirmado
// media hora después y un contacto una hora después en la agenda sin veterinario. vet-a ve su vista por defecto (lo suyo y lo sin veterinario, con el aviso) y marca atendido a
// Firulais; la recepción ve toda la sucursal y ficha a Rocky. La limpieza cancela lo que sigue e inactiva las agendas; la demo
// no se toca.

const PREFIX = 'Cola E2E';
const NAME = `${PREFIX} ${String(Date.now()).slice(-6)}`;
const LOADED = { timeout: 30_000 };

let data: QueueDay;

/** La fila de la cola de un turno: por su agenda (única de esta corrida) y su paciente o contacto. */
const queueRow = (page: Page, schedule: string, who: string) =>
  page.locator('.queue-row').filter({ hasText: schedule }).filter({ hasText: who });

test.beforeAll(async () => {
  data = await createQueueDay(NAME);
});

test.afterAll(async () => {
  await retireAgendasNamed(PREFIX);
});

test('vet-a ve su turno fichado y lo sin veterinario, y lo marca atendido desde la cola', async ({
  page,
}) => {
  await login(page, USERS.vet);
  await page.goto(`/agenda/cola?fecha=${data.day}`);

  const firulais = queueRow(page, data.vetSchedule, 'Firulais');
  await expect(firulais).toBeVisible(LOADED);
  await expect(firulais.locator('.appointment-status-label')).toHaveText('Fichado');
  await expect(firulais.locator('.queue-row-waiting')).toContainText('Esperando desde las');
  await expect(page.locator('.queue-unassigned')).toHaveText(
    'Incluye las agendas sin veterinario.',
  );
  await expect(queueRow(page, data.unassignedSchedule, data.unassignedContact)).toBeVisible();
  await expect(page.locator('a.agenda-view-cola')).toHaveAttribute('aria-current', 'page');

  await firulais.getByRole('button', { name: 'Marcar atendido' }).click();
  await expect(page.getByText('Turno marcado como atendido.')).toBeVisible();
  await expect(firulais.locator('.appointment-status-label')).toHaveText('Atendido');
  await expect(firulais.locator('.queue-row-waiting')).toHaveCount(0);
  // El botón ya no está: el foco quedó en la fila, no en el body.
  await expect(firulais.locator('.queue-row-title')).toBeFocused();
  await logout(page);
});

test('la recepción ve toda la sucursal y ficha desde la cola', async ({ page }) => {
  await login(page, USERS.recepcion);
  await page.goto(`/agenda/cola?fecha=${data.day}`);

  const rocky = queueRow(page, data.vetSchedule, 'Rocky');
  await expect(rocky).toBeVisible(LOADED);
  await expect(queueRow(page, data.unassignedSchedule, data.unassignedContact)).toBeVisible();
  await expect(page.locator('.queue-unassigned')).toHaveCount(0);
  await expect(page.getByLabel('Ver toda la sucursal')).toHaveCount(0);
  // La recepción no marca atendido.
  await expect(page.getByRole('button', { name: 'Marcar atendido' })).toHaveCount(0);

  await rocky.getByRole('button', { name: 'Fichar', exact: true }).click();
  await expect(page.getByText('Turno fichado.')).toBeVisible();
  await expect(rocky.locator('.appointment-status-label')).toHaveText('Fichado');
  await expect(rocky.locator('.queue-row-waiting')).toContainText('Esperando desde las');
  await logout(page);
});
