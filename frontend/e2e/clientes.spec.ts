import { expect, test } from '@playwright/test';
import { login, USERS } from './support/session';

// Los clientes de la Clínica A del DemoSeeder: Laura Martínez (Firulais y Mishi), Carlos Duarte (Rocky) y Granja San Jorge
// S.R.L. (Lucero).

test('buscar un cliente por el nombre de su mascota, abrir el detalle y volver a la búsqueda', async ({
  page,
}) => {
  await login(page, USERS.recepcion);
  await page.locator('.nav-link-title', { hasText: 'Clientes' }).click();
  await expect(page.getByRole('heading', { name: 'Clientes' })).toBeVisible();

  await page.getByLabel('Buscar clientes').fill('Firulais');
  await expect(page).toHaveURL(/\/clientes\?buscar=Firulais$/);
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await expect(page.locator('tbody tr').first()).toContainText('Laura Martínez');

  await page.getByRole('link', { name: 'Laura Martínez' }).click();
  await expect(page).toHaveURL(/\/clientes\/\d+$/);
  await expect(page.getByRole('heading', { name: 'Laura Martínez' })).toBeVisible();
  await expect(page.locator('.patient-name')).toHaveText(['Firulais', 'Mishi'], {
    useInnerText: true,
  });

  await page.goBack();
  await expect(page).toHaveURL(/\/clientes\?buscar=Firulais$/);
  await expect(page.getByLabel('Buscar clientes')).toHaveValue('Firulais');
  await expect(page.locator('tbody tr')).toHaveCount(1);
});

test('recargar /clientes?buscar= muestra el mismo resultado', async ({ page }) => {
  await login(page, USERS.admin);
  await page.goto('/clientes?buscar=Granja&tipo=empresa');

  await expect(page.getByLabel('Buscar clientes')).toHaveValue('Granja');
  await expect(page.getByLabel('Tipo de cliente')).toHaveValue('empresa');
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await expect(page.locator('tbody tr').first()).toContainText('Granja San Jorge S.R.L.');

  await page.reload();
  await expect(page.locator('tbody tr').first()).toContainText('Granja San Jorge S.R.L.');
  await expect(page.getByLabel('Buscar clientes')).toHaveValue('Granja');
});
