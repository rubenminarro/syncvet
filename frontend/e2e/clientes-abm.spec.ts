import { expect, test, type Page } from '@playwright/test';
import { login, logout, USERS } from './support/session';

// Alta, edición y baja de clientes (1.2) contra la API real. Los documentos son ficticios (R-011) y no están en el
// DemoSeeder; cada caso borra lo que crea. Laura Martínez (99100001) es de la demo y tiene pacientes (Firulais y Mishi).

const field = (page: Page, label: string) => page.getByLabel(label, { exact: true });

/**
 * Un RUC ficticio con su dígito (R-011) y el mismo con el dígito equivocado (el correcto más uno), armado en el código
 * para que FictitiousDocumentsTest no lo tome como un documento de ejemplo.
 */
const RUC = '99999904-4';
const WRONG_RUC = RUC.replace(/\d$/, (digit) => String((Number(digit) + 1) % 10));

/** Cuenta los POST y PATCH a clients: el RUC mal escrito no tiene que llegar a la API. */
function countWrites(page: Page) {
  const writes: string[] = [];
  page.on('request', (request) => {
    if (
      /\/api\/clients(\/\d+)?$/.test(new URL(request.url()).pathname) &&
      request.method() !== 'GET'
    ) {
      writes.push(`${request.method()} ${new URL(request.url()).pathname}`);
    }
  });

  return writes;
}

async function deleteFromDetail(page: Page, name: string) {
  await page.getByRole('button', { name: 'Eliminar', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: 'Eliminar el cliente' });
  await expect(dialog).toContainText(`¿Querés eliminar a ${name}?`);
  await dialog.getByRole('button', { name: `Eliminar a ${name}` }).click();
  // Al listado, con la búsqueda de donde se vino si se llegó desde él (desvío 1 de la 1.2).
  await expect(page).toHaveURL(/\/clientes(\?[^/]*)?$/);
  await expect(page.getByText('Cliente eliminado correctamente.')).toBeVisible();
}

test('alta de una persona, documento repetido, edición y baja del cliente recién creado', async ({
  page,
}) => {
  await login(page, USERS.admin);
  await page.goto('/clientes');
  await page.getByRole('link', { name: 'Nuevo cliente' }).click();
  await expect(page.getByRole('heading', { name: 'Nuevo cliente' })).toBeVisible();

  await field(page, 'Nombre').fill('Ana-María');
  await field(page, 'Apellido').fill("D'Alessandro");
  // El documento de Laura Martínez (de la demo): el 422 de la API, en su campo.
  await field(page, 'Documento').fill('99100001');
  await field(page, 'Email').fill('  Ana.Dalessandro@Example.COM ');
  await field(page, 'Teléfono').fill('+595 981 400 101');
  await field(page, 'Ciudad').fill('Asunción');
  await page.getByRole('button', { name: 'Guardar' }).click();

  await expect(page.getByText('Este número de documento ya está en uso.')).toBeVisible();
  await expect(field(page, 'Documento')).toBeFocused();

  await field(page, 'Documento').fill('99400101');
  await page.getByRole('button', { name: 'Guardar' }).click();

  await expect(page.getByRole('heading', { name: "Ana-María D'Alessandro" })).toBeVisible();
  await expect(page).toHaveURL(/\/clientes\/\d+$/);
  await expect(page.getByText('Cliente creado correctamente.')).toBeVisible();
  // El email llegó en minúsculas y el teléfono sin espacios.
  await expect(page.locator('.client-data')).toContainText('ana.dalessandro@example.com');
  await expect(page.locator('.client-data')).toContainText('+595981400101');

  await page.getByRole('link', { name: 'Editar' }).click();
  await expect(page.getByRole('heading', { name: 'Editar el cliente' })).toBeVisible();
  await expect(field(page, 'Nombre')).toHaveValue('Ana-María');
  await field(page, 'Teléfono').fill('+595981400102');
  await field(page, 'Notas').fill('Prefiere que la llamen a la tarde.');
  await page.getByRole('button', { name: 'Guardar' }).click();

  await expect(page.getByRole('heading', { name: "Ana-María D'Alessandro" })).toBeVisible();
  await expect(page.getByText('Cliente actualizado correctamente.')).toBeVisible();
  await expect(page.locator('.client-data')).toContainText('+595981400102');
  await expect(page.locator('.client-data')).toContainText('Prefiere que la llamen a la tarde.');

  await deleteFromDetail(page, "Ana-María D'Alessandro");
  await page.getByLabel('Buscar clientes').fill('99400101');
  await expect(page.getByText('No hay clientes que coincidan con la búsqueda.')).toBeVisible();
});

test('alta de una empresa: el RUC con el dígito mal no llega a la API; con el dígito bien, se crea', async ({
  page,
}) => {
  const writes = countWrites(page);
  await login(page, USERS.recepcion);
  await page.goto('/clientes/nuevo');

  await page.getByText('Empresa', { exact: true }).click();
  await expect(field(page, 'Nombre')).toHaveCount(0);
  await field(page, 'Razón social').fill('Haras del Sur SA');
  await field(page, 'RUC').fill(WRONG_RUC);
  await field(page, 'Email').fill('haras@example.com');
  await field(page, 'Teléfono').fill('+595971400104');
  await page.getByRole('button', { name: 'Guardar' }).click();

  await expect(
    page.getByText('El RUC no es válido: revisá el número y el dígito verificador.'),
  ).toBeVisible();
  await expect(field(page, 'RUC')).toBeFocused();
  expect(writes).toEqual([]);

  await field(page, 'RUC').fill(RUC);
  await page.getByRole('button', { name: 'Guardar' }).click();

  await expect(page.getByRole('heading', { name: 'Haras del Sur SA' })).toBeVisible();
  await expect(page.locator('.client-data')).toContainText('Empresa');
  await expect(page.locator('.client-data')).toContainText(RUC);
  expect(writes).toEqual(['POST /api/clients']);
  // La recepción no elimina (clients.destroy es del administrador).
  await expect(page.getByRole('button', { name: 'Eliminar', exact: true })).toHaveCount(0);
  const url = page.url();

  // La baja, con el administrador: no queda en la base de la corrida.
  await logout(page);
  await login(page, USERS.admin);
  await page.goto(url);
  await deleteFromDetail(page, 'Haras del Sur SA');
});

// Revisión de la 1.2: el doble clic mandaba dos POST; el segundo chocaba con el índice único y la API respondía 500.
test('doble clic en Guardar: un solo POST, un solo cliente y ningún 500', async ({ page }) => {
  const responses: number[] = [];
  page.on('response', (response) => {
    const request = response.request();

    if (request.method() === 'POST' && new URL(response.url()).pathname === '/api/clients') {
      responses.push(response.status());
    }
  });
  await login(page, USERS.admin);
  await page.goto('/clientes/nuevo');

  await field(page, 'Nombre').fill('Doble');
  await field(page, 'Apellido').fill('Clic');
  await field(page, 'Documento').fill('99400105');
  await field(page, 'Email').fill('doble.clic@example.com');
  await field(page, 'Teléfono').fill('+595981400105');
  await page.getByRole('button', { name: 'Guardar' }).dblclick();

  await expect(page.getByRole('heading', { name: 'Doble Clic' })).toBeVisible();
  expect(responses).toEqual([201]);

  await page.goto('/clientes?buscar=99400105');
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await page.getByRole('link', { name: 'Doble Clic' }).click();
  await deleteFromDetail(page, 'Doble Clic');
  // Otra vez al final: un segundo POST tardío también habría quedado registrado.
  expect(responses).toEqual([201]);
});

test('la baja de un cliente de la demo con pacientes: CLIENT_IN_USE en el diálogo, con solo "Cerrar"', async ({
  page,
}) => {
  await login(page, USERS.admin);
  await page.goto('/clientes?buscar=Firulais');
  await page.getByRole('link', { name: 'Laura Martínez' }).click();
  await expect(page.getByRole('heading', { name: 'Laura Martínez' })).toBeVisible();

  await page.getByRole('button', { name: 'Eliminar', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: 'Eliminar el cliente' });
  await expect(dialog.getByRole('button', { name: 'Cancelar' })).toBeFocused();
  await dialog.getByRole('button', { name: 'Eliminar a Laura Martínez' }).click();

  await expect(dialog).toContainText(
    'El cliente tiene pacientes, turnos, consultas o facturas y no puede eliminarse.',
  );
  await expect(dialog.getByRole('button', { name: 'Eliminar a Laura Martínez' })).toHaveCount(0);
  await expect(dialog.getByRole('button', { name: 'Cerrar' })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(dialog).toHaveCount(0);
  await expect(page).toHaveURL(/\/clientes\/\d+$/);
});
