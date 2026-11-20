// Recorrido de la F3 en Edge sin ventana (cierre de la F3 del frontend), contra syncvet.test a través de un Vite propio en :5180.
//
// Desde la raíz del repositorio, con el Vite levantado desde frontend/ (API_PROXY_TARGET=http://syncvet.test npx vite --port 5180
// --strictPort) y la demo al día (php artisan demo:agenda):
//
//   node docs/05-pruebas/edge-f3.mjs           todos los controles
//   node docs/05-pruebas/edge-f3.mjs 3,8       solo esos
//
// Cada control declara sus roles; se ingresa una vez por rol pedido y los tokens se revocan en un finally
// (frontend/CLAUDE.md §8). Reutiliza submitLogin y USERS de frontend/e2e/support/session.ts. Los controles que cambian datos
// solo toman agendas "Demo …" (el 6 confirma un turno: después, php artisan demo:agenda). La contraseña de la demo sale de
// DEMO_PASSWORD o de backend/.env y no se muestra.
import { createRequire, registerHooks } from 'node:module';

// Los ayudantes de e2e/ importan sin extensión (los resuelve Playwright): se prueba con .ts.
registerHooks({
  resolve(specifier, context, next) {
    try {
      return next(specifier, context);
    } catch (error) {
      if (specifier.startsWith('.')) return next(`${specifier}.ts`, context);
      throw error;
    }
  },
});
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const ROOT = process.cwd();
const FRONTEND = join(ROOT, 'frontend');
const require = createRequire(join(FRONTEND, 'package.json'));
const { chromium, expect } = require('@playwright/test');
const { USERS, submitLogin } = await import(pathToFileURL(join(FRONTEND, 'e2e', 'support', 'session.ts')).href);

const BASE = 'http://localhost:5180';
const PASSWORD =
  process.env.DEMO_PASSWORD ||
  (readFileSync(join(ROOT, 'backend', '.env'), 'utf8').match(/^DEMO_PASSWORD=(.*)$/m)?.[1] ?? '').trim().replace(/^"(.*)"$/, '$1');
if (!PASSWORD) throw new Error('Falta DEMO_PASSWORD (en el entorno o en backend/.env).');
const LOADED = { timeout: 20_000 };
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Asuncion' }).format(new Date());
const nextMonday = (() => {
  const d = new Date(`${today}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + (((8 - d.getUTCDay()) % 7) || 7));
  return d.toISOString().slice(0, 10);
})();

const sessions = new Map();
let logins = 0;

async function as(browser, role) {
  if (sessions.has(role)) return sessions.get(role);
  const context = await browser.newContext({ baseURL: BASE, locale: 'es-PY', timezoneId: 'America/Asuncion', viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  await page.goto('/ingresar');
  await submitLogin(page, USERS[role], PASSWORD);
  logins++;
  sessions.set(role, page);
  return page;
}

const token = (page) => page.evaluate(() => JSON.parse(sessionStorage.getItem('syncvet.session') ?? 'null')?.token ?? null);

async function api(page, path) {
  const response = await page.request.get(`${BASE}/api/${path}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${await token(page)}` } });
  return response.json();
}

async function todayAppointments(page) {
  return (await api(page, `appointments?from=${today}&to=${today}&per_page=500`)).data;
}

const CONTROLS = {
  1: { roles: ['admin'], name: 'Agendas: las 5 "Demo …" en /configuracion/agendas y el detalle de Demo Guardia', run: async ({ admin }) => {
    await admin.goto('/configuracion/agendas');
    for (const name of ['Demo Consultorio 1', 'Demo Consultorio 2', 'Demo Guardia', 'Demo Peluquería', 'Demo Vacunación']) {
      await expect(admin.getByRole('link', { name, exact: true })).toBeVisible(LOADED);
    }
    await admin.getByRole('link', { name: 'Demo Guardia', exact: true }).click();
    await expect(admin.getByRole('heading', { name: 'Demo Guardia' })).toBeVisible(LOADED);
    return 'las 5 agendas y el detalle';
  } },
  2: { roles: ['admin'], name: 'Bloqueos: el de Demo Guardia en su detalle', run: async ({ admin }) => {
    await expect(admin.getByText('Demo: capacitación del personal')).toBeVisible(LOADED);
    return 'el bloqueo "Demo: capacitación del personal" a la vista';
  } },
  3: { roles: ['recepcion'], name: 'Día: /agenda de hoy, un solo pedido de turnos, con las agendas Demo', run: async ({ recepcion }) => {
    const response = recepcion.waitForResponse((r) => r.url().includes('/api/appointments?') && r.request().method() === 'GET');
    await recepcion.goto(`/agenda?fecha=${today}`);
    const body = await (await response).json();
    await expect(recepcion.locator('.agenda-column-name', { hasText: 'Demo Consultorio 1' })).toBeVisible(LOADED);
    const demo = body.data.filter((a) => a.schedule.name.startsWith('Demo ')).length;
    return `${body.meta.pagination.total} turnos de hoy en la sucursal (${demo} de agendas Demo)`;
  } },
  4: { roles: ['recepcion'], name: 'Semana y lista: /agenda?vista=semana y /turnos de hoy', run: async ({ recepcion }) => {
    const week = recepcion.waitForResponse((r) => r.url().includes('/api/appointments?') && r.status() === 200);
    await recepcion.goto(`/agenda?vista=semana&fecha=${today}`);
    const weekTotal = (await (await week).json()).meta.pagination.total;
    await recepcion.goto(`/turnos?desde=${today}&hasta=${today}`);
    await expect(recepcion.locator('table tbody tr').first()).toBeVisible(LOADED);
    return `semana: ${weekTotal} turnos; lista de hoy: ${await recepcion.locator('table tbody tr').count()} filas en la primera página`;
  } },
  5: { roles: ['recepcion'], name: 'Turno: el detalle de un turno de una agenda Demo', run: async ({ recepcion }) => {
    const turn = (await todayAppointments(recepcion)).find((a) => a.schedule.name.startsWith('Demo ') && a.patient);
    await recepcion.goto(`/turnos/${turn.id}`);
    await expect(recepcion.getByText(turn.schedule.name).first()).toBeVisible(LOADED);
    await expect(recepcion.getByText(turn.patient.name).first()).toBeVisible(LOADED);
    return `turno ${turn.id} (${turn.schedule.name}, ${turn.patient.name}, ${turn.status.label})`;
  } },
  6: { roles: ['recepcion'], name: 'Estados: confirmar un turno "a confirmar" de una agenda Demo (lo restaura demo:agenda)', run: async ({ recepcion }) => {
    const turn = (await todayAppointments(recepcion)).find((a) => a.schedule.name.startsWith('Demo ') && a.status.value === 'pending_confirmation');
    await recepcion.goto(`/turnos/${turn.id}`);
    await recepcion.getByRole('button', { name: 'Confirmar', exact: true }).click();
    await expect(recepcion.locator('.appointment-status-label').first()).toHaveText('Confirmado', LOADED);
    return `turno ${turn.id} (${turn.schedule.name}) confirmado`;
  } },
  7: { roles: ['recepcion'], name: 'Series: la vista previa de 4 turnos semanales en Demo Consultorio 1, sin guardar', run: async ({ recepcion }) => {
    const schedules = (await api(recepcion, 'schedules?per_page=100')).data;
    const id = schedules.find((s) => s.name === 'Demo Consultorio 1').id;
    await recepcion.goto(`/turnos/nuevo?agenda=${id}&inicio=${nextMonday}T09:00`);
    await expect(recepcion.getByLabel('Agenda', { exact: true })).toHaveValue(String(id), LOADED);
    await recepcion.getByLabel('Buscar el paciente').fill('Firulais');
    await recepcion.locator('.appointment-who-options button', { hasText: 'Firulais' }).first().click();
    await recepcion.getByLabel('Repetir el turno (una serie)').check();
    await recepcion.getByLabel('Cantidad de turnos', { exact: true }).fill('4');
    await expect(recepcion.locator('.appointment-series-summary')).toContainText('4 turnos, los ', LOADED);
    await expect(recepcion.locator('.appointment-series-preview li')).toHaveCount(4);
    return `vista previa: ${(await recepcion.locator('.appointment-series-summary').innerText()).replace(/\s+/g, ' ').slice(0, 90)}`;
  } },
  8: { roles: ['vet', 'recepcion'], name: 'Cola: la de vet-a y la de la recepción, con los pedidos de bloqueos (deuda 29)', run: async ({ vet, recepcion }) => {
    const out = [];
    for (const [role, page] of [['vet-a', vet], ['recepción', recepcion]]) {
      const blocks = [];
      const listener = (r) => { if (/\/api\/schedules\/\d+\/blocks/.test(r.url())) blocks.push(r.url()); };
      page.on('request', listener);
      const queue = page.waitForResponse((r) => r.url().includes('/api/appointments/queue'));
      await page.goto(`/agenda/cola?fecha=${today}`);
      const body = await (await queue).json();
      await expect(page.locator('.queue-row').first()).toBeVisible(LOADED);
      await page.waitForLoadState('networkidle');
      page.off('request', listener);
      const agendas = new Set(body.data.map((a) => a.schedule.id)).size;
      out.push(`${role}: ${body.data.length} turnos en ${agendas} agendas, ${blocks.length} pedidos de bloqueos (1 de la cola + ${blocks.length})`);
    }
    await expect(vet.locator('.queue-unassigned')).toHaveText('Incluye las agendas sin veterinario.');
    return out.join('; ');
  } },
};

const wanted = (process.argv[2] ?? Object.keys(CONTROLS).join(',')).split(',').map(Number);
const browser = await chromium.launch({ channel: 'msedge', headless: true });
let failed = 0;
try {
  for (const n of wanted) {
    const control = CONTROLS[n];
    try {
      const pages = {};
      for (const role of control.roles) pages[role] = await as(browser, role);
      console.log(`OK    ${n}. ${control.name} [${control.roles.join(', ')}]: ${await control.run(pages)}`);
    } catch (error) {
      failed++;
      console.log(`FALLA ${n}. ${control.name}: ${String(error.message).split('\n')[0]}`);
    }
  }
} finally {
  let revoked = 0;
  for (const [role, page] of sessions) {
    try {
      const t = await token(page);
      const r = await page.request.post(`${BASE}/api/logout`, { headers: { Accept: 'application/json', Authorization: `Bearer ${t}` } });
      if (r.ok()) revoked++;
      else console.log(`logout de ${role}: ${r.status()}`);
    } catch (error) {
      console.log(`logout de ${role}: ${error.message}`);
    }
  }
  console.log(`Ingresos: ${logins}; tokens revocados: ${revoked}`);
  await browser.close();
}
process.exit(failed === 0 ? 0 : 1);
