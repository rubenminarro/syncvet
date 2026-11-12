import { expect, test } from '@playwright/test';
import { SECOND_BRANCH } from './support/env';
import { demoPassword, login, logout, openSecondTab, submitLogin, USERS } from './support/session';

test('cambio de sucursal: la barra, el inicio y la otra pestaña (y vuelve a la original)', async ({
  page,
  context,
}) => {
  await login(page, USERS.admin);
  const selector = page.getByLabel('Sucursal actual');
  const original = (await selector.locator('option:checked').textContent()) ?? '';
  expect(original).not.toBe('');

  // Una segunda pestaña toma la sesión de la primera (y se espera a que llegue).
  const other = await openSecondTab(context, USERS.admin);
  const otherSelector = other.getByLabel('Sucursal actual');
  await expect(otherSelector.locator('option:checked')).toHaveText(original);

  await selector.selectOption({ label: SECOND_BRANCH.name });
  await expect(page.getByText(`Tu sucursal actual es ${SECOND_BRANCH.name}.`)).toBeVisible();
  await expect(page.getByText(`Estás trabajando en ${SECOND_BRANCH.name}.`)).toBeVisible();
  // branch-changed: la otra pestaña vuelve a pedir me.
  await expect(otherSelector.locator('option:checked')).toHaveText(SECOND_BRANCH.name);

  // La guardó la API: al recargar sigue.
  await page.reload();
  await expect(selector.locator('option:checked')).toHaveText(SECOND_BRANCH.name);

  await selector.selectOption({ label: original });
  await expect(page.getByText(`Estás trabajando en ${original}.`)).toBeVisible();
  await expect(otherSelector.locator('option:checked')).toHaveText(original);
});

test('cambio de contraseña: el error en su campo, la sesión sigue y se ingresa con la nueva (y vuelve a la original)', async ({
  page,
}) => {
  const original = demoPassword();
  const changed = `${original}-Nueva1`;

  const change = async (from: string, to: string) => {
    await page.getByLabel('Contraseña actual', { exact: true }).fill(from);
    await page.getByLabel('Contraseña nueva', { exact: true }).fill(to);
    await page.getByLabel('Repetí la contraseña nueva', { exact: true }).fill(to);
    await page.getByRole('button', { name: 'Cambiar la contraseña' }).click();
  };

  await login(page, USERS.vet);
  await page.locator('.session-user').click();
  await expect(page.getByRole('heading', { name: 'Tu perfil' })).toBeVisible();

  // La actual incorrecta: el 422 de la API en su campo.
  await change('incorrecta', changed);
  await expect(page.getByLabel('Contraseña actual', { exact: true })).toHaveAttribute(
    'aria-invalid',
    'true',
  );

  // El botón muestra la contraseña nueva.
  await page.getByLabel('Contraseña nueva', { exact: true }).fill(changed);
  await page.getByRole('button', { name: 'Mostrar la contraseña' }).nth(1).click();
  await expect(page.getByLabel('Contraseña nueva', { exact: true })).toHaveAttribute(
    'type',
    'text',
  );

  await change(original, changed);
  await expect(page.getByText('Cambiaste tu contraseña.', { exact: false })).toBeVisible();

  // La sesión sigue: otra página y una recarga.
  await page.locator('.nav-link-title', { hasText: 'Inicio' }).click();
  await page.reload();
  await expect(page.locator('.session-user')).toHaveText(USERS.vet.name);

  // Con la nueva se ingresa; después vuelve a la original.
  await logout(page);
  await submitLogin(page, USERS.vet, changed);
  await page.goto('/perfil');
  await change(changed, original);
  await expect(page.getByText('Cambiaste tu contraseña.', { exact: false })).toBeVisible();
  await logout(page);
  await submitLogin(page, USERS.vet, original);
});

test('salir en una pestaña cierra la otra', async ({ page, context }) => {
  await login(page, USERS.recepcion);
  const other = await openSecondTab(context, USERS.recepcion);

  await logout(page);

  await expect(other).toHaveURL(/\/ingresar$/);
  await expect(other.getByRole('heading', { name: 'Ingresá a tu cuenta' })).toBeVisible();
});

test('credenciales incorrectas: el mensaje de la API, la contraseña borrada y sin sesión', async ({
  page,
}) => {
  await page.goto('/ingresar');
  await page.getByLabel('Email', { exact: true }).fill(USERS.recepcion.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(`${demoPassword()}-mal`);
  await page.getByRole('button', { name: 'Ingresar' }).click();

  await expect(page.locator('.alert-danger')).toContainText(
    'El email o la contraseña no son correctos.',
  );
  await expect(page.getByLabel('Contraseña', { exact: true })).toHaveValue('');
  await expect(page).toHaveURL(/\/ingresar$/);
  expect(await page.evaluate(() => sessionStorage.getItem('syncvet.session'))).toBeNull();

  // Con la correcta entra (y el contador del límite vuelve a cero).
  await submitLogin(page, USERS.recepcion);
});

test('vencimiento: expires_at pasado en sessionStorage cierra la sesión en las pestañas con ese token', async ({
  page,
  context,
}) => {
  await login(page, USERS.recepcion);
  const other = await openSecondTab(context, USERS.recepcion);

  // Forzado: la sesión guardada vence hace un minuto; al volver a la pestaña (focus) se relee y se cierra.
  await page.evaluate(() => {
    const stored = JSON.parse(sessionStorage.getItem('syncvet.session') ?? '{}') as Record<
      string,
      unknown
    >;
    stored.expires_at = new Date(Date.now() - 60_000).toISOString();
    sessionStorage.setItem('syncvet.session', JSON.stringify(stored));
    window.dispatchEvent(new Event('focus'));
  });

  await expect(page).toHaveURL(/\/ingresar/);
  await expect(page.getByText('Tu sesión terminó. Ingresá de nuevo.')).toBeVisible();
  // La otra pestaña, con el mismo token, también se cierra.
  await expect(other).toHaveURL(/\/ingresar/);
  await expect(other.getByText('Tu sesión terminó. Ingresá de nuevo.')).toBeVisible();
});
