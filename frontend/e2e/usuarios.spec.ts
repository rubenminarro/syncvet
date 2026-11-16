import { expect, test, type Page } from '@playwright/test';
import { login, submitLogin, USERS } from './support/session';

// Usuarios (2.2a) contra la API real: el administrador crea un usuario con un rol y una sucursal, le cambia los roles y la
// contraseña (el usuario entra con la nueva en otro contexto) y, al quitarse a sí mismo el rol de administrador siendo el
// único, recibe USER_LAST_ADMIN. El usuario creado queda en syncvet_e2e, que se recrea (o se vacía) en cada corrida.

const SUFFIX = String(Date.now()).slice(-6);
const EMAIL = `e2e-usuario-${SUFFIX}@example.com`;
const FIRST_NAME = 'Lucía';
const LAST_NAME = 'Prueba';
const NAME = `${FIRST_NAME} ${LAST_NAME}`;
// Contraseñas generadas en la corrida (no se repiten entre corridas ni aparecen en filtraciones).
const FIRST_PASSWORD = `Prueba-${SUFFIX}-Inicial`;
const NEW_PASSWORD = `Prueba-${SUFFIX}-Cambiada`;

const dialog = (page: Page) => page.locator('.confirm-dialog');
const roles = (page: Page) => page.locator('.user-roles');

test('un usuario: alta con rol y sucursal, sus roles, su contraseña y USER_LAST_ADMIN', async ({
  page,
  browser,
}) => {
  test.setTimeout(120_000);
  await login(page, USERS.admin);

  // Desde la página de entrada de la configuración.
  await page.locator('.nav-link-title', { hasText: 'Configuración' }).click();
  await page.getByRole('link', { name: /Usuarios/ }).click();
  await page.getByRole('link', { name: 'Nuevo usuario' }).click();

  // Alta: el email con mayúsculas (se manda en minúsculas), recepcionista y la primera sucursal.
  await page.getByLabel('Nombre', { exact: true }).fill(FIRST_NAME);
  await page.getByLabel('Apellido').fill(LAST_NAME);
  await page.getByLabel('Email').fill(EMAIL.toUpperCase());
  await roles(page).getByLabel('Recepcionista').check();
  const branch = page.locator('.user-branches-field input[type="checkbox"]').first();
  await branch.check();
  await page.getByLabel('Contraseña', { exact: true }).fill(FIRST_PASSWORD);
  await page.getByLabel('Repetí la contraseña').fill(FIRST_PASSWORD);
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Usuario creado correctamente.')).toBeVisible();
  await expect(page.getByRole('heading', { name: NAME })).toBeVisible();
  await expect(page.locator('.user-data')).toContainText(EMAIL);
  await expect(page.locator('.user-access')).toContainText('Recepcionista');
  await expect(page.locator('.user-current-branch')).toBeVisible();

  // Sus roles: suma veterinario. Sin "Cambiar la contraseña", la contraseña no cambia.
  await page.locator('.user-edit').click();
  await roles(page).getByLabel('Veterinario').check();
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Usuario actualizado correctamente.')).toBeVisible();
  await expect(page.locator('.user-access')).toContainText('Recepcionista, Veterinario');

  // Su contraseña, con el aviso de que se cierran sus sesiones.
  await page.locator('.user-edit').click();
  await page.getByLabel('Cambiar la contraseña').check();
  await expect(page.getByText('Se van a cerrar todas sus sesiones.')).toBeVisible();
  await page.getByLabel('Contraseña nueva').fill(NEW_PASSWORD);
  await page.getByLabel('Repetí la contraseña').fill(NEW_PASSWORD);
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(page.getByText('Usuario actualizado correctamente.')).toBeVisible();

  // El usuario entra con la nueva en otro contexto (la anterior ya no sirve).
  const other = await browser.newContext();
  const otherPage = await other.newPage();
  await otherPage.goto('/ingresar');
  await otherPage.getByLabel('Email', { exact: true }).fill(EMAIL);
  await otherPage.getByLabel('Contraseña', { exact: true }).fill(FIRST_PASSWORD);
  await otherPage.getByRole('button', { name: 'Ingresar' }).click();
  await expect(otherPage.locator('.alert')).toBeVisible();
  await submitLogin(otherPage, { email: EMAIL, name: NAME }, NEW_PASSWORD);
  await other.close();

  // El único administrador se quita el rol: la confirmación y, con el 409, la alerta arriba del formulario.
  await page.goto('/configuracion/usuarios');
  await page.getByLabel('Buscar usuarios').fill(USERS.admin.email);
  await page.getByRole('link', { name: 'Gómez, Ana' }).click();
  await expect(page.getByText('(vos)')).toBeVisible();
  await page.locator('.user-edit').click();
  await expect(page.getByText('Tu contraseña se cambia en tu')).toBeVisible();
  await roles(page).getByLabel('Recepcionista').check();
  await roles(page).getByLabel('Administrador').uncheck();
  await page.getByRole('button', { name: 'Guardar' }).click();
  await expect(dialog(page)).toContainText('Vas a dejar de ser administrador');
  await dialog(page).getByRole('button', { name: 'Quitarme el rol de administrador' }).click();
  await expect(
    page.getByText('La clínica tiene que tener al menos un administrador activo.'),
  ).toBeVisible();
  await expect(page).toHaveURL(/\/configuracion\/usuarios\/\d+\/editar$/);
});
