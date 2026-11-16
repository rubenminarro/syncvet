import { expect, test, type Page } from '@playwright/test';
import { login, USERS } from './support/session';

// Pacientes (1.3a) contra la API real: alta desde un cliente de la demo, edición, el microchip repetido (422 en su campo) y
// la búsqueda por el dueño en /pacientes. Laura Martínez (99100001) es de la demo y tiene a Firulais y a Mishi. Cada caso
// borra por la API los pacientes que crea (la baja desde la interfaz llega en la 1.3b).

const field = (page: Page, label: string) => page.getByLabel(label, { exact: true });

/** Borra por la API (con el token de la sesión de la pestaña, un administrador) los pacientes creados. */
async function deletePatients(page: Page, ids: number[]) {
  const session = await page.evaluate(() => sessionStorage.getItem('syncvet.session'));
  const token = session === null ? null : (JSON.parse(session) as { token: string }).token;

  for (const id of ids) {
    const response = await page.request.delete(`/api/patients/${String(id)}`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${String(token)}` },
    });
    expect(response.status(), `la baja del paciente ${String(id)}`).toBe(200);
  }
}

/** El id del paciente de la URL del detalle. */
function patientIdOf(page: Page): number {
  const match = /\/pacientes\/(\d+)$/.exec(new URL(page.url()).pathname);

  if (match === null) {
    throw new Error(`No es el detalle de un paciente: ${page.url()}`);
  }

  return Number(match[1]);
}

async function openLauraMartinez(page: Page) {
  await page.goto('/clientes');
  await page.getByLabel('Buscar clientes').fill('99100001');
  await page.getByRole('link', { name: 'Laura Martínez' }).click();
  await expect(page.getByRole('heading', { name: 'Laura Martínez' })).toBeVisible();
}

test('alta desde un cliente de la demo, edición, microchip repetido y búsqueda por el dueño', async ({
  page,
}) => {
  const created: number[] = [];
  await login(page, USERS.admin);

  try {
    // Alta desde el detalle del cliente.
    await openLauraMartinez(page);
    await page.getByRole('link', { name: 'Nuevo paciente' }).click();
    await expect(page).toHaveURL(/\/clientes\/\d+\/pacientes\/nuevo$/);
    await expect(page.getByRole('heading', { name: 'Nuevo paciente' })).toBeVisible();

    await field(page, 'Nombre').fill('Pampa');
    await field(page, 'Especie').selectOption({ label: 'Canino' });
    await field(page, 'Raza').selectOption({ label: 'Mestizo' });
    await page.getByText('Hembra', { exact: true }).click();
    await field(page, 'Fecha de nacimiento').fill('2021-06-15');
    await field(page, 'Peso (kg)').fill('8,5');
    await field(page, 'Microchip').fill('E2E900000000001');
    await page.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.getByRole('heading', { name: 'Pampa' })).toBeVisible();
    await expect(page.getByText('Paciente creado correctamente.')).toBeVisible();
    created.push(patientIdOf(page));
    const data = page.locator('.patient-data');
    // La fecha sin correrse un día y el peso con coma (se mandó "8.5").
    await expect(data).toContainText('15/06/2021');
    await expect(data).toContainText('8,5 kg');
    await expect(data).toContainText('Mestizo');
    await expect(data).toContainText('E2E900000000001');

    // En la tarjeta de pacientes del dueño (el alta invalidó el detalle del cliente).
    await page.locator('.patient-owner-link').click();
    await expect(page.getByRole('heading', { name: 'Laura Martínez' })).toBeVisible();
    await expect(page.locator('.patient-card', { hasText: 'Pampa' })).toContainText(
      'Canino · Mestizo · Hembra',
    );

    // Edición desde el detalle del paciente.
    await page.getByRole('link', { name: 'Pampa' }).click();
    await page.getByRole('link', { name: 'Editar' }).click();
    await expect(page.getByRole('heading', { name: 'Editar el paciente' })).toBeVisible();
    await expect(field(page, 'Peso (kg)')).toHaveValue('8,50');
    await field(page, 'Peso (kg)').fill('9,25');
    await field(page, 'Color').fill('Negro con blanco');
    await page.getByRole('button', { name: 'Guardar' }).click();

    await expect(page.getByText('Paciente actualizado correctamente.')).toBeVisible();
    await expect(data).toContainText('9,25 kg');
    await expect(data).toContainText('Negro con blanco');
    await expect(data).toContainText('15/06/2021');

    // Otro paciente con el mismo microchip: el 422 de la API, en su campo, con el foco.
    await openLauraMartinez(page);
    await page.getByRole('link', { name: 'Nuevo paciente' }).click();
    await field(page, 'Nombre').fill('Chispa');
    await field(page, 'Especie').selectOption({ label: 'Felino' });
    await field(page, 'Microchip').fill('E2E900000000001');
    await page.getByRole('button', { name: 'Guardar' }).click();

    await expect(
      page.getByText('Este microchip ya está registrado en otro paciente.'),
    ).toBeVisible();
    await expect(field(page, 'Microchip')).toBeFocused();
    await field(page, 'Microchip').fill('');
    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByRole('heading', { name: 'Chispa' })).toBeVisible();
    created.push(patientIdOf(page));

    // /pacientes: la búsqueda por el apellido del dueño encuentra sus pacientes, con el dueño en la tabla.
    await page.goto('/pacientes');
    await page.getByLabel('Buscar pacientes').fill('Martínez');
    await expect(page).toHaveURL(/\?buscar=Mart%C3%ADnez$/);
    const rows = page.locator('tbody tr');
    await expect(rows.filter({ hasText: 'Pampa' })).toContainText('Laura Martínez');
    await expect(rows.filter({ hasText: 'Chispa' })).toContainText('Laura Martínez');
    await expect(rows.filter({ hasText: 'Firulais' })).toHaveCount(1);
    await expect(rows.filter({ hasText: 'Rocky' })).toHaveCount(0);

    // Recargar muestra lo mismo.
    await page.reload();
    await expect(page.getByLabel('Buscar pacientes')).toHaveValue('Martínez');
    await expect(rows.filter({ hasText: 'Pampa' })).toHaveCount(1);
  } finally {
    await deletePatients(page, created);
  }
});
