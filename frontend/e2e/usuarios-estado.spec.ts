import { expect, test, type Page } from '@playwright/test';
import { login, submitLogin, USERS } from './support/session';

// Usuarios (2.2b) contra la API real: el administrador crea un usuario, que entra en otro contexto; lo inactiva (la sesión
// abierta deja de servir y el login responde USER_INACTIVE), lo reactiva (vuelve a entrar) y lo elimina. Después, la baja de
// vet-a (tiene ficha de veterinario): USER_IN_USE y "Inactivar" en el diálogo, sin confirmarlo. syncvet_e2e se recrea (o se
// vacía) en cada corrida.

const SUFFIX = String(Date.now()).slice(-6);
const EMAIL = `e2e-estado-${SUFFIX}@example.com`;
const FIRST_NAME = 'Marta';
const LAST_NAME = 'Estado';
const NAME = `${FIRST_NAME} ${LAST_NAME}`;
const USER = { email: EMAIL, name: NAME };
// Generada en la corrida (no se repite entre corridas ni aparece en filtraciones).
const PASSWORD = `Prueba-${SUFFIX}-Estado`;
const INACTIVE_MESSAGE = 'Tu usuario está inactivo: consultá con el administrador de la clínica.';

const dialog = (page: Page) => page.locator('.confirm-dialog');

/** Confirma el diálogo de inactivar o reactivar del detalle y espera el cambio. */
async function toggleStatus(page: Page, action: 'Inactivar' | 'Reactivar') {
  await page.locator('.user-status', { hasText: action }).click();
  await dialog(page)
    .getByRole('button', { name: `${action} a ${NAME}` })
    .click();
  await expect(page.getByText('Usuario actualizado correctamente.').last()).toBeVisible();
  await expect(dialog(page)).toHaveCount(0);
}

test('un usuario: inactivarlo cierra su sesión, reactivarlo y eliminarlo; vet-a con USER_IN_USE', async ({
  page,
  browser,
}) => {
  test.setTimeout(150_000);
  await login(page, USERS.admin);

  // Alta de un recepcionista con la primera sucursal.
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
  const detailUrl = page.url();

  // El usuario entra en otro contexto.
  const other = await browser.newContext();
  const otherPage = await other.newPage();
  await otherPage.goto('/ingresar');
  await submitLogin(otherPage, USER, PASSWORD);

  // Inactivarlo: el aviso de las sesiones y la marca.
  await page.locator('.user-status', { hasText: 'Inactivar' }).click();
  await expect(dialog(page)).toContainText(
    'Se van a cerrar todas sus sesiones y no va a poder ingresar hasta que lo reactives.',
  );
  await dialog(page)
    .getByRole('button', { name: `Inactivar a ${NAME}` })
    .click();
  await expect(page.locator('.user-inactive')).toBeVisible();
  await expect(page.locator('.user-status')).toHaveText('Reactivar');

  // Su sesión abierta deja de servir y el login responde USER_INACTIVE.
  await otherPage.reload();
  await expect(otherPage).toHaveURL(/\/ingresar/);
  await expect(otherPage.getByText('Tu sesión terminó. Ingresá de nuevo.')).toBeVisible();
  await otherPage.getByLabel('Email', { exact: true }).fill(EMAIL);
  await otherPage.getByLabel('Contraseña', { exact: true }).fill(PASSWORD);
  await otherPage.getByRole('button', { name: 'Ingresar' }).click();
  await expect(otherPage.getByText(INACTIVE_MESSAGE)).toBeVisible();

  // Fuera del listado por defecto; con "Incluir los inactivos", con su marca.
  await page.goto('/configuracion/usuarios');
  await page.getByLabel('Buscar usuarios').fill(EMAIL);
  await expect(page.getByText('No hay usuarios que coincidan con la búsqueda.')).toBeVisible();
  await page.getByLabel('Incluir los inactivos').check();
  await expect(page.getByRole('link', { name: `${LAST_NAME}, ${FIRST_NAME}` })).toBeVisible();
  await expect(page.locator('.user-inactive')).toBeVisible();

  // Reactivarlo: vuelve a entrar.
  await page.goto(detailUrl);
  await toggleStatus(page, 'Reactivar');
  await expect(page.locator('.user-inactive')).toHaveCount(0);
  await submitLogin(otherPage, USER, PASSWORD);
  await other.close();

  // Eliminarlo: al listado, donde ya no está (tampoco con los inactivos).
  await page.locator('.user-delete').click();
  await expect(dialog(page)).toContainText(`¿Querés eliminar a ${NAME}? No se puede deshacer.`);
  await dialog(page)
    .getByRole('button', { name: `Eliminar a ${NAME}` })
    .click();
  await expect(page.getByText('Usuario eliminado correctamente.')).toBeVisible();
  await expect(page).toHaveURL(/\/configuracion\/usuarios(\?.*)?$/);
  await page.getByLabel('Buscar usuarios').fill(EMAIL);
  await page.getByLabel('Incluir los inactivos').check();
  await expect(page.getByText('No hay usuarios que coincidan con la búsqueda.')).toBeVisible();

  // vet-a tiene ficha: USER_IN_USE y "Inactivar" en el diálogo, sin confirmarlo.
  await page.goto('/configuracion/usuarios');
  await page.getByLabel('Buscar usuarios').fill(USERS.vet.email);
  await page.getByRole('link', { name: 'Acosta, Víctor' }).click();
  await page.locator('.user-delete').click();
  await dialog(page)
    .getByRole('button', { name: `Eliminar a ${USERS.vet.name}` })
    .click();
  await expect(dialog(page)).toContainText(
    'El usuario tiene registros a su nombre y no se puede eliminar: inactivalo.',
  );
  await expect(dialog(page).getByRole('button', { name: 'Inactivar', exact: true })).toBeVisible();
  await dialog(page).getByRole('button', { name: 'Cancelar' }).click();
  await expect(dialog(page)).toHaveCount(0);
  await expect(page.locator('.user-inactive')).toHaveCount(0);
});
