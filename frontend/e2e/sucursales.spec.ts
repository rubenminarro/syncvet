import { expect, test, type Page } from '@playwright/test';
import { login, USERS } from './support/session';

// Sucursales y depósitos (2.1) contra la API real: el administrador crea una sucursal con su depósito por defecto, la
// edita, le suma un depósito, lo marca por defecto (el anterior deja de serlo) e inactiva la sucursal. Las sucursales no
// se borran: queda inactivada en syncvet_e2e, que se recrea (o se vacía) en cada corrida.

const SUFFIX = String(Date.now()).slice(-5);
const CODE = `E2E-${SUFFIX}`;
// Un establecimiento libre: los del DemoSeeder son los primeros (001, 002…).
const ESTABLISHMENT = String(500 + (Number(SUFFIX) % 400));
const NAME = `Sucursal E2E ${SUFFIX}`;

const dialog = (page: Page) => page.locator('.confirm-dialog');
const warehouseRow = (page: Page, code: string) => page.locator('tr', { hasText: code });

test('una sucursal: alta con su depósito, edición, un depósito nuevo por defecto y la inactivación', async ({
  page,
}) => {
  test.setTimeout(120_000);
  await login(page, USERS.admin);

  // Desde la página de entrada de la configuración.
  await page.locator('.nav-link-title', { hasText: 'Configuración' }).click();
  await page.getByRole('link', { name: /Sucursales/ }).click();
  await page.getByRole('link', { name: 'Nueva sucursal' }).click();

  // Alta: el código en minúsculas (la API y el navegador lo pasan a mayúsculas) y el depósito sin nombre ("Principal").
  await page.getByLabel('Código', { exact: true }).fill(CODE.toLowerCase());
  await page.getByLabel('Nombre', { exact: true }).fill(NAME);
  await page.getByLabel('Establecimiento').fill(ESTABLISHMENT);
  await page.getByLabel('Código del depósito').fill(`${CODE}-a`);
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Sucursal creada correctamente.')).toBeVisible();
  await expect(page.getByRole('heading', { name: NAME })).toBeVisible();
  await expect(page.locator('.branch-data')).toContainText(CODE);
  await expect(warehouseRow(page, `${CODE}-A`)).toContainText('Principal');
  await expect(warehouseRow(page, `${CODE}-A`)).toContainText('Por defecto');

  // Edición: la dirección y el teléfono.
  await page.locator('.branch-edit').click();
  await page.getByLabel('Dirección').fill('Ruta 2 km 20');
  await page.getByLabel('Teléfono').fill('+595 21 555-0001');
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Sucursal actualizada correctamente.')).toBeVisible();
  await expect(page.locator('.branch-data')).toContainText('+595215550001');
  await expect(page.locator('.branch-data')).toContainText('Ruta 2 km 20');

  // Un depósito nuevo, marcado por defecto: el anterior deja de serlo.
  await page.getByRole('button', { name: 'Nuevo depósito' }).click();
  await dialog(page).getByLabel('Código').fill(`${CODE}-b`);
  await dialog(page).getByLabel('Nombre').fill('Farmacia');
  await dialog(page).getByRole('button', { name: 'Crear el depósito' }).click();
  await expect(page.getByText('Depósito creado correctamente.')).toBeVisible();
  await expect(warehouseRow(page, `${CODE}-B`)).toContainText('Farmacia');
  await expect(warehouseRow(page, `${CODE}-B`)).not.toContainText('Por defecto');

  await page.getByRole('button', { name: 'Marcar Farmacia como por defecto' }).click();
  await dialog(page).getByRole('button', { name: 'Marcar como por defecto' }).click();
  await expect(warehouseRow(page, `${CODE}-B`)).toContainText('Por defecto');
  await expect(warehouseRow(page, `${CODE}-A`)).not.toContainText('Por defecto');

  // El anterior ya se puede inactivar; el por defecto, no (sin el botón).
  await expect(page.getByRole('button', { name: 'Inactivar Farmacia' })).toHaveCount(0);
  await page.getByRole('button', { name: 'Inactivar Principal' }).click();
  await dialog(page).getByRole('button', { name: 'Inactivar Principal' }).click();
  await expect(warehouseRow(page, `${CODE}-A`)).toContainText('Inactivado');

  // Inactivar la sucursal: el aviso de los usuarios que la tienen como actual, y sin "Nuevo depósito" después.
  await page.locator('.branch-deactivate').click();
  await expect(dialog(page)).toContainText('van a tener que elegir otra');
  await dialog(page)
    .getByRole('button', { name: `Inactivar ${NAME}` })
    .click();
  await expect(page.locator('.branch-activate')).toBeVisible();
  await expect(page.locator('.branch-data')).toContainText('Inactivada');
  await expect(page.getByRole('button', { name: 'Nuevo depósito' })).toHaveCount(0);

  // En el listado, solo con "Incluir las inactivas".
  await page.getByRole('link', { name: 'Sucursales' }).click();
  await page.getByLabel('Buscar sucursales').fill(CODE);
  await expect(page.getByText('No hay sucursales que coincidan con la búsqueda.')).toBeVisible();
  await page.getByLabel('Incluir las inactivas').check();
  await expect(page.getByRole('link', { name: NAME })).toBeVisible();
});
