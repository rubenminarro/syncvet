import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test, type Page } from '@playwright/test';
import { login, USERS } from './support/session';

// La foto del paciente (1.4) contra la API real, sobre un paciente recién creado en Laura Martínez (99100001, de la demo):
// subir la imagen de las colecciones de Postman, verla después de recargar (descargada con el token como Blob), reemplazarla
// por otra y quitarla. Al final el paciente se borra por la API (la baja deja el archivo: lo levanta files:prune-orphans).

const FILES = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '../../docs/05-pruebas/postman/archivos',
);
const FIRST = path.join(FILES, 'foto-paciente.png');
const SECOND = path.join(FILES, 'logo-clinica.webp');

const field = (page: Page, label: string) => page.getByLabel(label, { exact: true });
const photo = (page: Page) => page.getByRole('img', { name: 'Foto de Nube' });

async function token(page: Page): Promise<string> {
  const session = await page.evaluate(() => sessionStorage.getItem('syncvet.session'));

  return session === null ? '' : (JSON.parse(session) as { token: string }).token;
}

function patientIdOf(page: Page): number {
  const match = /\/pacientes\/(\d+)$/.exec(new URL(page.url()).pathname);

  if (match === null) {
    throw new Error(`No es el detalle de un paciente: ${page.url()}`);
  }

  return Number(match[1]);
}

/** La imagen se dibujó de verdad (la URL de objeto sigue viva): su ancho natural no es 0. */
async function expectDrawn(page: Page) {
  await expect(photo(page)).toBeVisible();
  await expect
    .poll(() => photo(page).evaluate((image: HTMLImageElement) => image.naturalWidth))
    .toBeGreaterThan(0);
  await expect(photo(page)).toHaveAttribute('src', /^blob:/);
}

async function upload(page: Page, file: string, label: string) {
  await field(page, label).setInputFiles(file);
  await expect(page.getByRole('img', { name: 'Vista previa de la foto nueva' })).toBeVisible();
  const [response] = await Promise.all([
    page.waitForResponse((r) => r.request().method() === 'POST' && r.url().endsWith('/photo')),
    page.getByRole('button', { name: 'Subir la foto' }).click(),
  ]);
  expect(response.status()).toBe(200);
}

test('subir la foto, verla al recargar, reemplazarla y quitarla', async ({ page }) => {
  test.setTimeout(120_000);
  let created: number | null = null;
  await login(page, USERS.admin);

  try {
    await page.goto('/clientes');
    await page.getByLabel('Buscar clientes').fill('99100001');
    await page.getByRole('link', { name: 'Laura Martínez' }).click();
    await page.getByRole('link', { name: 'Nuevo paciente' }).click();
    await field(page, 'Nombre').fill('Nube');
    await field(page, 'Especie').selectOption({ label: 'Felino' });
    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByRole('heading', { name: 'Nube' })).toBeVisible();
    created = patientIdOf(page);
    await expect(page.locator('.patient-photo-empty')).toContainText('Sin foto');

    // Subir: la vista previa local, "Subir la foto" y la foto que devuelve la API.
    await upload(page, FIRST, 'Elegir una foto');
    await expectDrawn(page);

    // Al recargar, otra descarga con el token.
    const [download] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'GET' && r.url().endsWith('/photo')),
      page.reload(),
    ]);
    expect(download.status()).toBe(200);
    expect(download.headers()['content-type']).toBe('image/png');
    await expectDrawn(page);

    // Reemplazar por un WEBP: se descarga la nueva.
    const before = await photo(page).getAttribute('src');
    const [replaced] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'GET' && r.url().endsWith('/photo')),
      upload(page, SECOND, 'Cambiar la foto'),
    ]);
    expect(replaced.headers()['content-type']).toBe('image/webp');
    await expectDrawn(page);
    expect(await photo(page).getAttribute('src')).not.toBe(before);

    // Quitar, con confirmación.
    await page.getByRole('button', { name: 'Quitar la foto' }).click();
    await page.locator('.confirm-dialog').getByRole('button', { name: 'Quitar la foto' }).click();
    await expect(page.locator('.patient-photo-empty')).toContainText('Sin foto');
    await expect(photo(page)).toHaveCount(0);
    await page.reload();
    await expect(page.locator('.patient-photo-empty')).toContainText('Sin foto');
  } finally {
    if (created !== null) {
      const response = await page.request.delete(`/api/patients/${String(created)}`, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${await token(page)}` },
      });
      expect(response.status(), 'la baja del paciente creado').toBe(200);
    }
  }
});
