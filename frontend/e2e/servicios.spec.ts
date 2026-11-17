import { expect, test, type Page } from '@playwright/test';
import { login, USERS } from './support/session';

// Servicios (2.4) contra la API real: el administrador crea un servicio con el precio "150.000" (con puntos de miles; el
// cuerpo lo manda como texto de solo dígitos y la tabla lo muestra "Gs. 150.000" después de recargar), lo edita, el
// recepcionista lo ve sin acciones y el administrador lo inactiva y lo elimina. syncvet_e2e se recrea (o se vacía) en cada
// corrida.

const SUFFIX = String(Date.now()).slice(-6);
const NAME = `Control E2E ${SUFFIX}`;
const RENAMED = `Control E2E ${SUFFIX} (anual)`;

const dialog = (page: Page) => page.locator('.confirm-dialog');
/** Intl separa "Gs." del importe con un espacio duro. */
const plain = (text: string | null) => (text ?? '').replace(/\s/g, ' ');

test('un servicio nuevo: crearlo con "150.000", editarlo, verlo como recepcionista, inactivarlo y eliminarlo', async ({
  page,
  browser,
}) => {
  test.setTimeout(120_000);
  await login(page, USERS.admin);
  await page.getByRole('link', { name: 'Servicios' }).click();
  await page.locator('.service-new').click();
  await expect(page.getByRole('heading', { name: 'Nuevo servicio' })).toBeVisible();

  await page.getByLabel('Nombre').fill(NAME);
  await page.getByLabel('Categoría').selectOption('consultation');

  // El precio con coma decimal: rechazado en el navegador, sin pedido.
  let posted = 0;
  page.on('request', (request) => {
    if (request.method() === 'POST' && request.url().endsWith('/api/services')) {
      posted += 1;
    }
  });
  await page.getByLabel('Precio (Gs.)').fill('150.000,50');
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('El precio debe ser un número entero, sin decimales')).toBeVisible();
  expect(posted).toBe(0);

  // "150.000": el cuerpo lleva "150000" como texto.
  await page.getByLabel('Precio (Gs.)').fill('150.000');
  const request = page.waitForRequest(
    (sent) => sent.method() === 'POST' && sent.url().endsWith('/api/services'),
  );
  await page.getByRole('button', { name: 'Guardar' }).click();
  const body = (await request).postDataJSON() as Record<string, unknown>;
  expect(body).toMatchObject({
    name: NAME,
    category: 'consultation',
    standard_price: '150000',
    duration_minutes: 30,
    tax_rate: 10,
  });
  await expect(page.getByText('Servicio creado correctamente.')).toBeVisible();
  const title = page.locator('.service-title');
  await expect(title).toHaveText(new RegExp(`^SRV-\\d+ · ${NAME}$`));
  const code = (await title.textContent())?.split(' · ')[0] ?? '';
  const serviceUrl = page.url();

  // Después de recargar, en el listado: "Gs. 150.000".
  await page.goto(`/servicios?buscar=${encodeURIComponent(NAME)}`);
  await page.reload();
  const row = page.locator('tbody tr', { hasText: code });
  expect(plain(await row.locator('.service-price').textContent())).toBe('Gs. 150.000');

  // Editarlo: el precio vuelve con sus puntos; nombre, precio e IVA nuevos.
  await page.goto(serviceUrl);
  await page.locator('.service-edit').click();
  await expect(page.getByLabel('Precio (Gs.)')).toHaveValue('150.000');
  await page.getByLabel('Nombre').fill(RENAMED);
  await page.getByLabel('Precio (Gs.)').fill('175.500');
  await page.locator('.service-form').getByText('5 %', { exact: true }).click();
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Servicio actualizado correctamente.')).toBeVisible();
  await expect(title).toHaveText(`${code} · ${RENAMED}`);
  const data = page.locator('.service-data');
  expect(plain(await data.textContent())).toContain('Gs. 175.500');
  await expect(data).toContainText('5 %');

  // El recepcionista: en el listado y en el detalle, sin "Nuevo servicio" ni acciones.
  const other = await browser.newContext();
  const reception = await other.newPage();
  await login(reception, USERS.recepcion);
  await reception.getByRole('link', { name: 'Servicios' }).click();
  await reception.getByLabel('Buscar servicios').fill(code);
  await expect(reception.locator('tbody tr', { hasText: code })).toContainText(RENAMED);
  await expect(reception.getByText('Nuevo servicio')).toHaveCount(0);
  await reception.getByRole('link', { name: RENAMED }).click();
  await expect(reception.locator('.service-title')).toHaveText(`${code} · ${RENAMED}`);
  await expect(reception.locator('.service-edit')).toHaveCount(0);
  await expect(reception.locator('.service-status-action')).toHaveCount(0);
  await expect(reception.locator('.service-delete')).toHaveCount(0);
  await other.close();

  // Inactivarlo: fuera de "Activos", en "Inactivos".
  await page.locator('.service-status-action', { hasText: 'Inactivar' }).click();
  await dialog(page)
    .getByRole('button', { name: `Inactivar ${RENAMED}` })
    .click();
  await expect(page.locator('.service-status')).toHaveText('Inactivo');
  await page.goto(`/servicios?buscar=${code}`);
  await expect(page.getByText('No hay servicios que coincidan con la búsqueda.')).toBeVisible();
  await page.getByLabel('Estado').selectOption('inactivos');
  await expect(page.getByRole('link', { name: RENAMED })).toBeVisible();

  // Eliminarlo (sin uso): al listado, donde ya no está (tampoco con "Todos").
  await page.goto(serviceUrl);
  await page.locator('.service-delete').click();
  await dialog(page)
    .getByRole('button', { name: `Eliminar ${RENAMED}` })
    .click();
  await expect(page.getByText('Servicio eliminado correctamente.')).toBeVisible();
  await expect(page).toHaveURL(/\/servicios(\?.*)?$/);
  await page.goto(`/servicios?buscar=${code}&estado=todos`);
  await expect(page.getByText('No hay servicios que coincidan con la búsqueda.')).toBeVisible();
});
