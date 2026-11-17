import { expect, test } from '@playwright/test';
import { login, submitLogin, USERS } from './support/session';

const ROLES = [
  {
    role: 'administrador',
    user: USERS.admin,
    menu: [
      'Inicio',
      'Clientes',
      'Pacientes',
      'Especies',
      'Servicios',
      'Veterinarios',
      'Configuración',
    ],
  },
  {
    role: 'recepcionista',
    user: USERS.recepcion,
    menu: ['Inicio', 'Clientes', 'Pacientes', 'Especies', 'Servicios', 'Veterinarios'],
  },
  {
    role: 'veterinario',
    user: USERS.vet,
    menu: ['Inicio', 'Clientes', 'Pacientes', 'Especies', 'Servicios', 'Veterinarios'],
  },
] as const;

for (const { role, user, menu } of ROLES) {
  test(`${role}: ingresa y ve su menú`, async ({ page }) => {
    await login(page, user);

    await expect(page.locator('.nav-link-title')).toHaveText([...menu]);
  });
}

test('el administrador entra a /configuracion', async ({ page }) => {
  await login(page, USERS.admin);
  await page.locator('.nav-link-title', { hasText: 'Configuración' }).click();

  await expect(page).toHaveURL(/\/configuracion$/);
  // La página de entrada (2.1): una tarjeta por sección.
  await expect(page.getByRole('heading', { name: 'Configuración' })).toBeVisible();
  await expect(page.getByRole('link', { name: /Sucursales/ })).toBeVisible();
});

test('una ruta sin permiso lleva a /sin-permiso, dentro del layout', async ({ page }) => {
  await login(page, USERS.recepcion);
  await page.goto('/configuracion');

  await expect(page).toHaveURL(/\/sin-permiso$/);
  await expect(page.getByText('No tenés permiso para ver esta página')).toBeVisible();
  await expect(page.locator('.session-user')).toHaveText(USERS.recepcion.name);
});

test('una ruta protegida sin sesión lleva a /ingresar y, después de ingresar, vuelve', async ({
  page,
}) => {
  await page.goto('/perfil');

  await expect(page).toHaveURL(/\/ingresar\?volver=%2Fperfil$/);
  await submitLogin(page, USERS.vet);
  await expect(page).toHaveURL(/\/perfil$/);
  await expect(page.getByRole('heading', { name: 'Tu perfil' })).toBeVisible();
});
