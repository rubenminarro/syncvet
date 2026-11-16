import { expect, test, type Page } from '@playwright/test';
import { login, USERS } from './support/session';

// Pacientes (1.3b) contra la API real, sobre un paciente recién creado en Laura Martínez (99100001, de la demo): el
// fallecimiento a una hora dada y su ida y vuelta (desvío 8 de la 0.1: se manda con -03:00 y se lee igual al recargar), las
// notas de un fallecido con peso cargado (H5), la anulación, el cambio de dueño a Carlos Duarte (99100002) visto en los dos
// clientes y la baja desde la interfaz. Si algo falla antes de la baja, el paciente se borra por la API.

const field = (page: Page, label: string) => page.getByLabel(label, { exact: true });
const dialog = (page: Page) => page.locator('.confirm-dialog');

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

async function openClient(page: Page, document: string, name: string) {
  await page.goto('/clientes');
  await page.getByLabel('Buscar clientes').fill(document);
  await page.getByRole('link', { name }).click();
  await expect(page.getByRole('heading', { name })).toBeVisible();
}

test('fallecimiento con su hora, notas de un fallecido, anulación, cambio de dueño y baja', async ({
  page,
}) => {
  // Un caso largo (siete pasos contra la API real): el límite de 60 s no alcanza con la PC cargada.
  test.setTimeout(180_000);
  let created: number | null = null;
  await login(page, USERS.admin);

  try {
    await openClient(page, '99100001', 'Laura Martínez');
    await page.getByRole('link', { name: 'Nuevo paciente' }).click();
    await field(page, 'Nombre').fill('Brisa');
    await field(page, 'Especie').selectOption({ label: 'Canino' });
    await field(page, 'Fecha de nacimiento').fill('2021-06-15');
    await field(page, 'Peso (kg)').fill('8,5');
    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByRole('heading', { name: 'Brisa' })).toBeVisible();
    created = patientIdOf(page);

    // El fallecimiento el 10/11/2026 a las 10:30 de Asunción: se manda con -03:00 y la API devuelve 13:30 en UTC.
    await page.getByRole('button', { name: 'Registrar el fallecimiento' }).click();
    await dialog(page).getByLabel('Fecha').fill('2026-11-10');
    await dialog(page).getByLabel('Hora').fill('10:30');
    const [request, response] = await Promise.all([
      page.waitForRequest((r) => r.method() === 'PATCH' && r.url().includes('/api/patients/')),
      page.waitForResponse((r) => r.request().method() === 'PATCH'),
      dialog(page).getByRole('button', { name: 'Registrar el fallecimiento' }).click(),
    ]);
    expect(request.postDataJSON()).toEqual({ deceased_at: '2026-11-10T10:30:00.000-03:00' });
    const body = (await response.json()) as { data: { deceased_at: string } };
    expect(body.data.deceased_at).toMatch(/^2026-11-10T13:30:00(\.0+)?Z$/);
    await expect(page.locator('.patient-deceased-at')).toContainText(
      'Falleció el 10/11/2026 10:30',
    );

    // Recargar (otro GET): la misma hora.
    await page.reload();
    await expect(page.locator('.patient-deceased-at')).toContainText(
      'Falleció el 10/11/2026 10:30',
    );
    await expect(page.locator('.patient-deceased')).toBeVisible();

    // Las notas de un fallecido con peso cargado (H5): los demás campos, bloqueados y mandados como estaban.
    await page.getByRole('link', { name: 'Editar' }).click();
    await expect(field(page, 'Nombre')).toBeDisabled();
    await expect(field(page, 'Peso (kg)')).toHaveValue('8,50');
    await field(page, 'Notas').fill('Cremación el 11/11.');
    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByText('Paciente actualizado correctamente.')).toBeVisible();
    await expect(page.locator('.patient-data')).toContainText('Cremación el 11/11.');

    // Anular el fallecimiento, con confirmación.
    await page.getByRole('button', { name: 'Anular el fallecimiento' }).click();
    await dialog(page).getByRole('button', { name: 'Anular el fallecimiento' }).click();
    await expect(page.locator('.patient-deceased-at')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Registrar el fallecimiento' })).toBeVisible();

    // Cambio de dueño a Carlos Duarte (el dueño actual no se ofrece).
    await page.getByRole('button', { name: 'Cambiar de dueño' }).click();
    await dialog(page).getByLabel('Buscar el cliente').fill('99100');
    await expect(dialog(page).getByText('Carlos Duarte')).toBeVisible();
    await expect(dialog(page).locator('.owner-options')).not.toContainText('Laura Martínez');
    await dialog(page).getByText('Carlos Duarte').click();
    await dialog(page).getByRole('button', { name: 'Pasar a Carlos Duarte' }).click();
    await expect(page.getByText('Dueño del paciente actualizado correctamente.')).toBeVisible();
    await expect(page.locator('.patient-owner-link')).toHaveText('Carlos Duarte');

    // En los dos clientes: Laura ya no lo tiene; Carlos, sí.
    await openClient(page, '99100001', 'Laura Martínez');
    await expect(page.locator('.patient-card', { hasText: 'Brisa' })).toHaveCount(0);
    await openClient(page, '99100002', 'Carlos Duarte');
    await expect(page.locator('.patient-card', { hasText: 'Brisa' })).toHaveCount(1);

    // La baja desde la interfaz: vuelve al detalle del dueño, sin el paciente.
    await page.getByRole('link', { name: 'Brisa' }).click();
    // Con el detalle del paciente ya dibujado: si no, "Eliminar" sería el del cliente.
    await expect(page.getByRole('heading', { name: 'Brisa' })).toBeVisible();
    await page.locator('.patient-delete').click();
    await dialog(page).getByRole('button', { name: 'Eliminar a Brisa' }).click();
    await expect(page.getByText('Paciente eliminado correctamente.')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Carlos Duarte' })).toBeVisible();
    await expect(page.locator('.patient-card', { hasText: 'Brisa' })).toHaveCount(0);
    created = null;
  } finally {
    if (created !== null) {
      const response = await page.request.delete(`/api/patients/${String(created)}`, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${await token(page)}` },
      });
      expect(response.status(), 'la baja del paciente creado').toBe(200);
    }
  }
});
