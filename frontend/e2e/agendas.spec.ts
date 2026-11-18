import { expect, test, type Page } from '@playwright/test';
import { createScheduleFor, deleteSchedulesNamed } from './support/api';
import { login, USERS } from './support/session';

// Agendas (3.1a, F3) contra la API real: el administrador crea una agenda de vet-a con dos tramos del lunes que se tocan y la
// ve después de recargar, edita el horario, la inactiva y la ve con "Incluir las inactivas", y la elimina (sin turnos). Si un
// paso falla, la limpieza la elimina por la API en syncvet_e2e (la demo no se toca).

const PREFIX = 'Agenda E2E';
const NAME = `${PREFIX} ${String(Date.now()).slice(-6)}`;

const dialog = (page: Page) => page.locator('.confirm-dialog');
const day = (page: Page, weekday: number) =>
  page.locator(`.schedule-form .schedule-day-${String(weekday)}`);

async function addHour(page: Page, weekday: number, starts: string, ends: string) {
  await day(page, weekday).locator('.schedule-hour-add').click();
  await day(page, weekday).locator('.hour-start').last().fill(starts);
  await day(page, weekday).locator('.hour-end').last().fill(ends);
}

test.afterAll(async () => {
  await deleteSchedulesNamed(PREFIX);
});

test('una agenda nueva: crearla con dos tramos que se tocan, editar el horario, inactivarla y eliminarla', async ({
  page,
}) => {
  test.setTimeout(120_000);
  await login(page, USERS.admin);
  await page.locator('.nav-link-title', { hasText: 'Configuración' }).click();
  await page.getByRole('link', { name: /Agendas/ }).click();
  await page.locator('.schedule-new').click();
  await expect(page.getByRole('heading', { name: 'Nueva agenda' })).toBeVisible();

  await page.getByLabel('Sucursal', { exact: true }).selectOption({ label: 'Casa central' });
  await page.getByLabel('Veterinario', { exact: true }).selectOption({ label: USERS.vet.name });
  await page.getByLabel('Nombre', { exact: true }).fill(NAME);
  await addHour(page, 1, '08:00', '12:00');
  await addHour(page, 1, '12:00', '16:00');

  const request = page.waitForRequest(
    (sent) => sent.method() === 'POST' && sent.url().endsWith('/api/schedules'),
  );
  await page.getByRole('button', { name: 'Guardar' }).click();
  expect((await request).postDataJSON()).toMatchObject({
    name: NAME,
    slot_minutes: 30,
    color: null,
    hours: [
      { weekday: 1, starts_at: '08:00', ends_at: '12:00' },
      { weekday: 1, starts_at: '12:00', ends_at: '16:00' },
    ],
  });
  await expect(page.getByText('Agenda creada correctamente.')).toBeVisible();
  await expect(page.locator('.schedule-title')).toHaveText(NAME);

  // Después de recargar, lo guardado: el veterinario y los dos tramos del lunes.
  await page.reload();
  await expect(page.locator('.schedule-day-1')).toHaveText('08:00 a 12:00, 12:00 a 16:00');
  await expect(page.locator('.schedule-data')).toContainText(USERS.vet.name);
  await expect(page.locator('.schedule-day-3')).toHaveText('Sin atención');

  // Editar el horario: el lunes queda con un tramo y el miércoles suma uno.
  await page.locator('.schedule-edit').click();
  await expect(page.getByRole('heading', { name: 'Editar la agenda' })).toBeVisible();
  await day(page, 1).locator('.schedule-hour-remove').last().click();
  await day(page, 1).locator('.hour-end').first().fill('13:00');
  await addHour(page, 3, '09:00', '11:00');
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Agenda actualizada correctamente.')).toBeVisible();
  await page.reload();
  await expect(page.locator('.schedule-day-1')).toHaveText('08:00 a 13:00');
  await expect(page.locator('.schedule-day-3')).toHaveText('09:00 a 11:00');

  // Inactivarla: no está entre las activas; con "Incluir las inactivas", sí, con su marca.
  await page.locator('.schedule-status-action').click();
  await dialog(page)
    .getByRole('button', { name: `Inactivar ${NAME}` })
    .click();
  await expect(page.locator('.schedule-inactive')).toHaveText('Inactivada');
  await page.locator('.page-pretitle').getByRole('link', { name: 'Agendas' }).click();
  await page.getByLabel('Buscar agendas').fill(NAME);
  await expect(page.getByText('No hay agendas que coincidan con la búsqueda.')).toBeVisible();
  await page.getByLabel('Incluir las inactivas').check();
  const row = page.locator('tr', { hasText: NAME });
  await expect(row.locator('.schedule-inactive')).toHaveText('Inactivada');

  // Eliminarla: sin turnos, se elimina y vuelve al listado.
  await row.getByRole('link', { name: NAME }).click();
  await page.locator('.schedule-delete').click();
  await dialog(page)
    .getByRole('button', { name: `Eliminar ${NAME}` })
    .click();
  await expect(page.getByText('Agenda eliminada correctamente.')).toBeVisible();
  await expect(page).toHaveURL(/\/configuracion\/agendas(\?|$)/);
  await expect(page.locator('tr', { hasText: NAME })).toHaveCount(0);
});

/** Una fecha `yyyy-MM-dd` de Asunción, `days` días después de hoy (sumando de a días de calendario). */
function dayInAsuncion(days: number): string {
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Asuncion' }).format(
    new Date(),
  );
  const [year, month, day] = today.split('-').map(Number) as [number, number, number];

  return new Date(Date.UTC(year, month - 1, day + days)).toISOString().slice(0, 10);
}

// Bloqueos (3.1b): la agenda se crea por la API (createScheduleFor, en syncvet_e2e); en la interfaz, un bloqueo de una hora
// (09:00 a 10:00 de Asunción, dentro de una semana) se ve en la tarjeta después de recargar y se elimina. Los turnos que
// quedan adentro, con MSW (ScheduleBlocks.test.tsx) y, con turnos desde la interfaz, en la 3.4.
test('los bloqueos de una agenda: crear uno de una hora, verlo después de recargar y eliminarlo', async ({
  page,
}) => {
  test.setTimeout(90_000);
  const name = `${PREFIX} bloqueos ${String(Date.now()).slice(-6)}`;
  const id = await createScheduleFor(name);
  const date = dayInAsuncion(7);
  const [year, month, day] = date.split('-');
  const period = `${String(day)}/${String(month)}/${String(year)}, de 09:00 a 10:00`;

  await login(page, USERS.admin);
  await page.goto(`/configuracion/agendas/${String(id)}`);
  await expect(page.locator('.schedule-title')).toHaveText(name);
  await expect(page.getByText('La agenda no tiene bloqueos desde hoy.')).toBeVisible();

  await page.locator('.block-new').click();
  await page.getByLabel('Fecha desde').fill(date);
  await page.getByLabel('Hora desde').fill('09:00');
  await page.getByLabel('Fecha hasta').fill(date);
  await page.getByLabel('Hora hasta').fill('10:00');
  await page.getByLabel('Motivo').fill('Capacitación E2E');
  const request = page.waitForRequest(
    (sent) =>
      sent.method() === 'POST' && sent.url().endsWith(`/api/schedules/${String(id)}/blocks`),
  );
  await dialog(page).getByRole('button', { name: 'Crear el bloqueo' }).click();
  expect((await request).postDataJSON()).toEqual({
    starts_at: `${date}T09:00:00.000-03:00`,
    ends_at: `${date}T10:00:00.000-03:00`,
    reason: 'Capacitación E2E',
  });
  await expect(page.getByText('Bloqueo creado correctamente.')).toBeVisible();
  await expect(dialog(page)).toHaveCount(0);

  // Después de recargar, el bloqueo guardado, en la hora de Asunción.
  await page.reload();
  const row = page.locator('tr', { hasText: period });
  await expect(row.locator('.block-reason')).toHaveText('Capacitación E2E');

  await row.locator('.block-delete').click();
  await dialog(page).getByRole('button', { name: 'Eliminar el bloqueo' }).click();
  await expect(page.getByText('Bloqueo eliminado correctamente.')).toBeVisible();
  await expect(page.getByText('La agenda no tiene bloqueos desde hoy.')).toBeVisible();
});
