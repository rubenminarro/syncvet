import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test, type Page } from '@playwright/test';
import { login, USERS } from './support/session';

// La configuración de la clínica (2.5) contra la API real: cambiar el teléfono de contacto y verlo después de recargar;
// subir el logo (los archivos de las colecciones de Postman), verlo en la barra después de recargar, reemplazarlo y
// quitarlo; y el menú plegado a 375 px sin que la página se salga de la pantalla. Lo guardado al empezar se restaura por la
// API al final (también el logo, si no había).

const FILES = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '../../docs/05-pruebas/postman/archivos',
);
const PNG = path.join(FILES, 'logo-clinica.png');
const WEBP = path.join(FILES, 'logo-clinica.webp');

interface Settings {
  clinic: { name: string; legal_name: string };
  settings: Record<string, string | null>;
  has_logo: boolean;
}

const field = (page: Page, label: string) => page.getByLabel(label, { exact: true });
const barLogo = (page: Page) => page.locator('header img.brand-logo');

async function token(page: Page): Promise<string> {
  const session = await page.evaluate(() => sessionStorage.getItem('syncvet.session'));

  return session === null ? '' : (JSON.parse(session) as { token: string }).token;
}

async function headers(page: Page) {
  return { Accept: 'application/json', Authorization: `Bearer ${await token(page)}` };
}

/** La imagen se dibujó de verdad (la URL de objeto sigue viva): su ancho natural no es 0. */
async function expectDrawn(page: Page) {
  await expect(barLogo(page)).toBeVisible();
  await expect
    .poll(() => barLogo(page).evaluate((image: HTMLImageElement) => image.naturalWidth))
    .toBeGreaterThan(0);
  await expect(barLogo(page)).toHaveAttribute('src', /^blob:/);
}

async function upload(page: Page, file: string, label: string) {
  await field(page, label).setInputFiles(file);
  await expect(page.getByRole('img', { name: 'Vista previa del logo nuevo' })).toBeVisible();
  const [response] = await Promise.all([
    page.waitForResponse(
      (r) => r.request().method() === 'POST' && r.url().endsWith('/system-settings/logo'),
    ),
    page.getByRole('button', { name: 'Subir el logo' }).click(),
  ]);
  expect(response.status()).toBe(200);
}

test('los datos de la clínica, el logo en la barra y el menú plegado a 375 px', async ({
  page,
}) => {
  test.setTimeout(150_000);
  await login(page, USERS.admin);
  const saved = await page.request.get('/api/system-settings', { headers: await headers(page) });
  expect(saved.status()).toBe(200);
  const original = ((await saved.json()) as { data: Settings }).data;

  try {
    await page.goto('/configuracion');
    await page
      .locator('.configuration-sections')
      .getByRole('link', { name: /^Clínica/ })
      .click();
    await expect(page.getByRole('heading', { name: 'Datos de la clínica' })).toBeVisible();
    const clinicName = await field(page, 'Nombre').inputValue();

    // El teléfono de contacto, con espacios y guiones: se manda sin ellos y se ve después de recargar.
    await field(page, 'Teléfono de contacto').fill('+595 21 110-0099');
    const [patch] = await Promise.all([
      page.waitForResponse(
        (r) => r.request().method() === 'PATCH' && r.url().endsWith('/system-settings'),
      ),
      page.getByRole('button', { name: 'Guardar' }).click(),
    ]);
    expect(patch.status()).toBe(200);
    const body = patch.request().postDataJSON() as Record<string, unknown>;
    expect(body).not.toHaveProperty('ruc');
    expect(body).not.toHaveProperty('ruc_dv');
    expect((body.settings as Record<string, unknown>).contact_phone).toBe('+595211100099');
    await page.reload();
    await expect(field(page, 'Teléfono de contacto')).toHaveValue('+595211100099');

    // El logo: subirlo, verlo en la barra después de recargar, reemplazarlo y quitarlo.
    await expect(barLogo(page)).toHaveCount(original.has_logo ? 1 : 0);
    await upload(page, PNG, original.has_logo ? 'Cambiar el logo' : 'Elegir un logo');
    await expectDrawn(page);
    await expect(barLogo(page)).toHaveAttribute('alt', clinicName);
    await page.reload();
    await expectDrawn(page);

    const before = await barLogo(page).getAttribute('src');
    const [replaced] = await Promise.all([
      page.waitForResponse(
        (r) => r.request().method() === 'GET' && r.url().endsWith('/system-settings/logo'),
      ),
      upload(page, WEBP, 'Cambiar el logo'),
    ]);
    expect(replaced.headers()['content-type']).toBe('image/webp');
    await expectDrawn(page);
    expect(await barLogo(page).getAttribute('src')).not.toBe(before);

    await page.getByRole('button', { name: 'Quitar el logo' }).click();
    await page.locator('.confirm-dialog').getByRole('button', { name: 'Quitar el logo' }).click();
    await expect(page.locator('.clinic-logo-empty')).toContainText('Sin logo');
    await expect(barLogo(page)).toHaveCount(0);
    await expect(page.locator('header .navbar-brand')).toContainText('SyncVet');

    // El menú a 375 px: plegado detrás de "Menú", sin desplazamiento horizontal de la página.
    await page.setViewportSize({ width: 375, height: 800 });
    const toggle = page.getByRole('button', { name: 'Menú' });
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('.main-menu-list')).toBeHidden();
    const overflow = () =>
      page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
      );
    expect(await overflow()).toBeLessThanOrEqual(0);

    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(
      page.locator('.main-menu-list').getByRole('link', { name: 'Servicios' }),
    ).toBeVisible();
    expect(await overflow()).toBeLessThanOrEqual(0);
    await page.keyboard.press('Tab');
    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toBeFocused();

    await toggle.click();
    await page.locator('.main-menu-list').getByRole('link', { name: 'Servicios' }).click();
    await expect(page).toHaveURL(/\/servicios$/);
    await expect(page.locator('.main-menu-list')).toBeHidden();
    expect(await overflow()).toBeLessThanOrEqual(0);
  } finally {
    // Lo de antes, por la API: los datos y las claves; el logo, quitado si no había.
    const restored = await page.request.patch('/api/system-settings', {
      headers: await headers(page),
      data: {
        name: original.clinic.name,
        legal_name: original.clinic.legal_name,
        settings: original.settings,
      },
    });
    expect(restored.status(), 'los datos de la clínica restaurados').toBe(200);

    if (!original.has_logo) {
      const current = await page.request.get('/api/system-settings', {
        headers: await headers(page),
      });
      const now = ((await current.json()) as { data: Settings }).data;

      if (now.has_logo) {
        const removed = await page.request.delete('/api/system-settings/logo', {
          headers: await headers(page),
        });
        expect(removed.status(), 'el logo quitado').toBe(200);
      }
    }
  }
});
