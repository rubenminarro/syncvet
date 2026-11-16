import { expect, test, type Page } from '@playwright/test';
import { login, USERS } from './support/session';

// Especies y razas (1.5) contra la API real: el administrador crea una raza propia en "Canino" (la especie común del
// SpeciesSeeder; el diseño decía "Perro"), la usa en un paciente nuevo de Laura Martínez (99100001), intenta borrarla
// (BREED_IN_USE) y la inactiva desde el mismo diálogo; el recepcionista ve el catálogo sin botones y sin la inactivada. El
// paciente se borra por la API al final; la raza queda inactivada (la usa un paciente eliminado: no se puede borrar), en
// syncvet_e2e, que se recrea en cada corrida.

const BREED = `Raza E2E ${String(Date.now()).slice(-6)}`;

const field = (page: Page, label: string) => page.getByLabel(label, { exact: true });
const canino = (page: Page) => page.locator('.species-item[data-species="Canino"]');
const breed = (page: Page) => page.locator(`.breed-item[data-breed="${BREED}"]`);
const dialog = (page: Page) => page.locator('.confirm-dialog');

async function token(page: Page): Promise<string> {
  const session = await page.evaluate(() => sessionStorage.getItem('syncvet.session'));

  return session === null ? '' : (JSON.parse(session) as { token: string }).token;
}

test('una raza propia: alta, en uso en un paciente, la baja rechazada y la inactivación; el recepcionista solo lista', async ({
  page,
  browser,
}) => {
  test.setTimeout(150_000);
  let patientId: number | null = null;
  await login(page, USERS.admin);

  try {
    // Alta de la raza propia en una especie común.
    await page.goto('/especies');
    await canino(page).locator('.species-expand').click();
    await canino(page).getByRole('button', { name: 'Nueva raza' }).click();
    await dialog(page).getByLabel('Nombre').fill(BREED);
    await dialog(page).getByRole('button', { name: 'Crear la raza' }).click();
    await expect(page.getByText('Raza creada correctamente.')).toBeVisible();
    await expect(breed(page)).toContainText('Propia');

    // Usarla en un paciente nuevo.
    await page.goto('/clientes');
    await page.getByLabel('Buscar clientes').fill('99100001');
    await page.getByRole('link', { name: 'Laura Martínez' }).click();
    await page.getByRole('link', { name: 'Nuevo paciente' }).click();
    await field(page, 'Nombre').fill('Lucero');
    await field(page, 'Especie').selectOption({ label: 'Canino' });
    await field(page, 'Raza').selectOption({ label: BREED });
    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByRole('heading', { name: 'Lucero' })).toBeVisible();
    patientId = Number(/\/pacientes\/(\d+)$/.exec(new URL(page.url()).pathname)?.[1]);
    await expect(page.locator('.patient-data')).toContainText(BREED);

    // La baja, rechazada (BREED_IN_USE), y la inactivación desde el mismo diálogo.
    await page.goto('/especies');
    await canino(page).locator('.species-expand').click();
    await breed(page).getByRole('button', { name: 'Eliminar' }).click();
    const [rejected] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'DELETE'),
      dialog(page)
        .getByRole('button', { name: `Eliminar ${BREED}` })
        .click(),
    ]);
    expect(rejected.status()).toBe(409);
    await expect(dialog(page)).toContainText(
      'La raza está asignada a pacientes y no puede eliminarse. Podés inactivarla.',
    );
    await dialog(page)
      .getByRole('button', { name: `Inactivar ${BREED}` })
      .click();
    await expect(dialog(page)).toHaveCount(0);
    await expect(breed(page)).toContainText('Inactivada');

    // El recepcionista: el catálogo sin botones y sin la raza inactivada.
    const context = await browser.newContext();
    const reception = await context.newPage();
    await login(reception, USERS.recepcion);
    await reception.goto('/especies');
    await canino(reception).locator('.species-expand').click();
    await expect(canino(reception).locator('.breed-item').first()).toBeVisible();
    await expect(breed(reception)).toHaveCount(0);
    await expect(
      reception.locator(
        '.species-new, .breed-new, .species-rename, .breed-rename, .species-delete',
      ),
    ).toHaveCount(0);
    await context.close();
  } finally {
    if (patientId !== null) {
      const response = await page.request.delete(`/api/patients/${String(patientId)}`, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${await token(page)}` },
      });
      expect(response.status(), 'la baja del paciente creado').toBe(200);
    }
  }
});

test('una especie propia con una raza: alta, cambio de nombre, inactivación y baja (se lleva la raza)', async ({
  page,
}) => {
  test.setTimeout(120_000);
  const suffix = String(Date.now()).slice(-6);
  const name = `Especie E2E ${suffix}`;
  const renamed = `Especie Renombrada ${suffix}`;
  const breedName = `Raza Propia ${suffix}`;
  const species = (label: string) => page.locator(`.species-item[data-species="${label}"]`);
  await login(page, USERS.admin);
  await page.goto('/especies');

  // Alta de la especie y de una raza en ella (POST species/{id}/breeds).
  await page.locator('.species-new').click();
  await dialog(page).getByLabel('Nombre').fill(name);
  await dialog(page).getByRole('button', { name: 'Crear la especie' }).click();
  await expect(page.getByText('Especie creada correctamente.')).toBeVisible();
  await expect(species(name)).toContainText('Propia');
  await species(name).locator('.species-expand').click();
  await species(name).getByRole('button', { name: 'Nueva raza' }).click();
  await dialog(page).getByLabel('Nombre').fill(breedName);
  await dialog(page).getByRole('button', { name: 'Crear la raza' }).click();
  await expect(page.getByText('Raza creada correctamente.')).toBeVisible();
  await expect(species(name).locator(`.breed-item[data-breed="${breedName}"]`)).toContainText(
    'Propia',
  );

  // Cambio de nombre.
  await species(name).locator('.species-rename').click();
  await dialog(page).getByLabel('Nombre').fill(renamed);
  await dialog(page).getByRole('button', { name: 'Guardar el nombre' }).click();
  await expect(page.getByText('Especie actualizada correctamente.')).toBeVisible();
  await expect(species(renamed)).toHaveCount(1);
  await expect(species(name)).toHaveCount(0);

  // Inactivación, sin confirmación.
  await species(renamed).locator('.species-toggle-active').click();
  await expect(species(renamed)).toContainText('Inactivada');
  await expect(species(renamed).locator('.species-toggle-active')).toHaveText('Activar');

  // Baja con confirmación: sin pacientes, se va con su raza.
  await species(renamed).locator('.species-delete').click();
  await dialog(page)
    .getByRole('button', { name: `Eliminar ${renamed}` })
    .click();
  await expect(page.getByText('Especie eliminada correctamente.')).toBeVisible();
  await expect(species(renamed)).toHaveCount(0);
});
