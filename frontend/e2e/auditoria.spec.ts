import { expect, test, type Page } from '@playwright/test';
import { login, USERS } from './support/session';

// La auditoría (2.6) contra la API real: un cambio hecho en la pantalla se encuentra filtrando por la fecha de hoy en
// Asunción, el tipo y el número del registro, y su detalle muestra el antes y el después. Carlos Duarte es de la demo; sus
// notas se dejan como estaban.

const field = (page: Page, label: string) => page.getByLabel(label, { exact: true });

/** La fecha y la hora de pared de Asunción de un instante (`yyyy-MM-dd` y `HH:mm`), sin la zona de la PC. */
function asuncion(date: Date): { day: string; time: string } {
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
      .formatToParts(date)
      .map((part) => [part.type, part.value]),
  );

  return {
    day: `${parts.year}-${parts.month}-${parts.day}`,
    time: `${parts.hour}:${parts.minute}`,
  };
}

/** Los parámetros del próximo GET audit-logs (el listado, no un detalle). */
function nextListRequest(page: Page) {
  return page
    .waitForRequest((request) => new URL(request.url()).pathname === '/api/audit-logs')
    .then((request) => new URL(request.url()).searchParams);
}

async function editNotes(page: Page, notes: string) {
  await page.getByRole('link', { name: 'Editar' }).click();
  await expect(page.getByRole('heading', { name: 'Editar el cliente' })).toBeVisible();
  const previous = await field(page, 'Notas').inputValue();
  await field(page, 'Notas').fill(notes);
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Cliente actualizado correctamente.')).toBeVisible();

  return previous;
}

test('un cambio en las notas de un cliente: en la auditoría de hoy, por su tipo y su número, con el antes y el después', async ({
  page,
}) => {
  await login(page, USERS.admin);
  await page.goto('/clientes');
  await page.getByRole('link', { name: 'Carlos Duarte' }).click();
  await expect(page.getByRole('heading', { name: 'Carlos Duarte' })).toBeVisible();
  const clientId = /\/clientes\/(\d+)$/.exec(page.url())?.[1] ?? '';
  expect(clientId).not.toBe('');

  const marker = `Auditoría e2e ${String(Date.now())}`;
  const original = await editNotes(page, marker);
  const changedAt = asuncion(new Date());

  // De la tarjeta de /configuracion a la auditoría.
  await page.goto('/configuracion');
  await page.getByRole('link', { name: /Auditoría/ }).click();
  await expect(page.getByRole('heading', { name: 'Auditoría' })).toBeVisible();

  // Hoy en Asunción, solo la fecha: el día completo, mandado en UTC.
  await field(page, 'Tipo de registro').selectOption('client');
  await expect(page).toHaveURL(/entidad=client/);
  await field(page, 'Desde').fill(changedAt.day);
  await field(page, 'Hasta').fill(changedAt.day);
  await field(page, 'N.º de registro').fill(clientId);
  const request = nextListRequest(page);
  await page.getByRole('button', { name: 'Filtrar' }).click();
  const params = await request;
  expect(params.get('from')).toMatch(/^\d{4}-\d{2}-\d{2}T03:00:00\.000Z$/);
  expect(params.get('to')).toMatch(/^\d{4}-\d{2}-\d{2}T02:59:59\.000Z$/);
  expect(params.get('auditable_type')).toBe('client');
  expect(params.get('auditable_id')).toBe(clientId);
  await expect(page).toHaveURL(new RegExp(`desde=${changedAt.day}&hasta=${changedAt.day}`));

  // El cambio, primero (por fecha descendente), y su detalle.
  const row = page.locator('tbody tr').first();
  await expect(row).toContainText('Modificado');
  await expect(row).toContainText(`Cliente n.º ${clientId}`);
  await expect(row).toContainText(USERS.admin.name);
  await row.locator('.audit-link').click();
  await expect(page.getByRole('heading', { name: /Registro de auditoría n\.º \d+/ })).toBeVisible();
  const notes = page.locator('.audit-changes tr', {
    has: page.locator('code', { hasText: /^notes$/ }),
  });
  await expect(notes).toContainText(marker);
  if (original !== '') {
    await expect(notes).toContainText(original);
  }

  // De vuelta al listado con los filtros; con "desde" un minuto después del cambio, la lista queda vacía. (A las 23:59
  // no hay un minuto después en el mismo día: se salta.)
  await page.getByRole('link', { name: 'Auditoría', exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`registro=${clientId}`));
  const [hours = 0, minutes = 0] = changedAt.time.split(':').map(Number);
  const later = hours * 60 + minutes + 1;
  if (later < 24 * 60) {
    const time = `${String(Math.floor(later / 60)).padStart(2, '0')}:${String(later % 60).padStart(2, '0')}`;
    await field(page, 'Hora desde').fill(time);
    await page.getByRole('button', { name: 'Filtrar' }).click();
    await expect(page.getByText('No hay registros que coincidan con los filtros.')).toBeVisible();
  }

  // Las notas, como estaban.
  await page.goto(`/clientes/${clientId}`);
  await editNotes(page, original);
});
