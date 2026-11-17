import { expect, test, type Page } from '@playwright/test';
import { login, USERS } from './support/session';

// Veterinarios (2.3) contra la API real: el administrador crea un usuario, le da el rol veterinario, crea su ficha desde el
// detalle del usuario (el código VET- lo pone la API) y la edita; el recepcionista la ve en el listado sin la comisión ni el
// enlace al detalle; el administrador la inactiva y la elimina. syncvet_e2e se recrea (o se vacía) en cada corrida.

const SUFFIX = String(Date.now()).slice(-6);
const EMAIL = `e2e-vet-${SUFFIX}@example.com`;
const FIRST_NAME = 'Marta';
const LAST_NAME = 'Veterinaria';
const NAME = `${FIRST_NAME} ${LAST_NAME}`;
// Generada en la corrida (no se repite entre corridas ni aparece en filtraciones).
const PASSWORD = `Prueba-${SUFFIX}-Vet`;
const LICENSE = `mp-e2e-${SUFFIX}`;

const dialog = (page: Page) => page.locator('.confirm-dialog');

test('la ficha de un usuario nuevo: crearla desde su detalle, editarla, verla como recepcionista, inactivarla y eliminarla', async ({
  page,
  browser,
}) => {
  test.setTimeout(150_000);
  await login(page, USERS.admin);

  // Un usuario nuevo (recepcionista) y, al editarlo, el rol veterinario.
  await page.goto('/configuracion/usuarios/nuevo');
  await page.getByLabel('Nombre', { exact: true }).fill(FIRST_NAME);
  await page.getByLabel('Apellido').fill(LAST_NAME);
  await page.getByLabel('Email').fill(EMAIL);
  await page.locator('.user-roles').getByLabel('Recepcionista').check();
  await page.locator('.user-branches-field input[type="checkbox"]').first().check();
  await page.getByLabel('Contraseña', { exact: true }).fill(PASSWORD);
  await page.getByLabel('Repetí la contraseña').fill(PASSWORD);
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByRole('heading', { name: NAME })).toBeVisible();
  await expect(page.locator('.user-create-veterinarian')).toHaveCount(0);
  const userUrl = page.url();

  await page.locator('.user-edit').click();
  await page.locator('.user-roles').getByLabel('Veterinario').check();
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Usuario actualizado correctamente.')).toBeVisible();

  // "Crear la ficha de veterinario": el usuario ya elegido; la matrícula en mayúsculas y la comisión con coma.
  await page.locator('.user-create-veterinarian').click();
  await expect(page.getByRole('heading', { name: 'Nuevo veterinario' })).toBeVisible();
  await expect(page.getByLabel('Usuario')).toHaveValue(/^\d+$/);
  await expect(page.getByLabel('Usuario').locator('option:checked')).toContainText(
    `${LAST_NAME}, ${FIRST_NAME}`,
  );
  await page.getByLabel('Matrícula').fill(`  ${LICENSE} `);
  await page.getByLabel('Especialidad').fill('Felinos');
  await page.getByLabel('Comisión (%)').fill('12,5');
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Veterinario creado correctamente.')).toBeVisible();
  const title = page.locator('.veterinarian-title');
  await expect(title).toHaveText(new RegExp(`^VET-\\d+ · ${NAME}$`));
  const code = (await title.textContent())?.split(' · ')[0] ?? '';
  const data = page.locator('.veterinarian-data');
  await expect(data).toContainText(LICENSE.toUpperCase());
  await expect(data).toContainText('12,5 %');

  // El detalle del usuario enlaza a la ficha.
  await page.goto(userUrl);
  await page.locator('.user-veterinarian-link', { hasText: code }).click();
  await expect(title).toHaveText(`${code} · ${NAME}`);
  const vetUrl = page.url();

  // Editarla.
  await page.locator('.veterinarian-edit').click();
  await expect(page.getByLabel('Comisión (%)')).toHaveValue('12,50');
  await page.getByLabel('Especialidad').fill('Felinos y exóticos');
  await page.getByLabel('Comisión (%)').fill('15,25');
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Veterinario actualizado correctamente.')).toBeVisible();
  await expect(data).toContainText('15,25 %');
  await expect(data).toContainText('Felinos y exóticos');

  // El recepcionista: el listado con la ficha, sin el enlace, sin "Nuevo veterinario" y sin la comisión.
  const other = await browser.newContext();
  const reception = await other.newPage();
  await login(reception, USERS.recepcion);
  await reception.getByRole('link', { name: 'Veterinarios' }).click();
  await reception.getByLabel('Buscar veterinarios').fill(LICENSE);
  const row = reception.locator('tbody tr', { hasText: code });
  await expect(row).toContainText(NAME);
  await expect(row).toContainText('Felinos y exóticos');
  await expect(row.getByRole('link')).toHaveCount(0);
  await expect(reception.getByText('Nuevo veterinario')).toHaveCount(0);
  await expect(reception.getByRole('columnheader', { name: 'Comisión' })).toHaveCount(0);
  await expect(reception.locator('.page-body')).not.toContainText('15,25');
  await other.close();

  // Inactivarla: fuera de "Activos", en "Inactivos".
  await page.locator('.veterinarian-status-action', { hasText: 'Inactivar' }).click();
  await dialog(page)
    .getByRole('button', { name: `Inactivar a ${NAME}` })
    .click();
  await expect(page.locator('.veterinarian-status')).toHaveText('Inactivo');
  await expect(page.locator('.veterinarian-status-action')).toHaveText('Activar');
  await page.goto(`/veterinarios?buscar=${LICENSE}`);
  await expect(page.getByText('No hay veterinarios que coincidan con la búsqueda.')).toBeVisible();
  await page.getByLabel('Estado').selectOption('inactivos');
  await expect(page.getByRole('link', { name: NAME })).toBeVisible();

  // Eliminarla: al listado, donde ya no está (tampoco con "Todos"); el usuario queda sin ficha.
  await page.goto(vetUrl);
  await page.locator('.veterinarian-delete').click();
  await dialog(page)
    .getByRole('button', { name: `Eliminar a ${NAME}` })
    .click();
  await expect(page.getByText('Veterinario eliminado correctamente.')).toBeVisible();
  await expect(page).toHaveURL(/\/veterinarios(\?.*)?$/);
  await page.goto(`/veterinarios?buscar=${LICENSE}&estado=todos`);
  await expect(page.getByText('No hay veterinarios que coincidan con la búsqueda.')).toBeVisible();
  await page.goto(userUrl);
  await expect(page.locator('.user-create-veterinarian')).toBeVisible();
});
