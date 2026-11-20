// Corre con Newman las colecciones de docs/05-pruebas/postman y, después de cada una, borra lo que dejó su corrida con
// `php artisan postman:cleanup <corrida>` (cierre de la F3 del frontend): la regresión no deja datos en la base.
//
// Desde la raíz del repositorio, con el servidor levantado desde backend/ en el puerto 8001 (`php artisan serve --port=8001`):
//
//   node docs/05-pruebas/newman-todas.mjs                 todas las colecciones
//   node docs/05-pruebas/newman-todas.mjs turnos series   solo las que contienen esos textos en el nombre
//
// La contraseña de la demo sale de DEMO_PASSWORD o, si no está, de backend/.env; no se muestra. Cada colección se exporta al
// terminar (--export-collection, en la carpeta temporal del sistema) para leer su variable `run`. Sale con 1 si una colección
// falla o si la limpieza falla.
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readdirSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const ROOT = process.cwd();
const DIR = join(ROOT, 'docs', '05-pruebas', 'postman');
const BACKEND = join(ROOT, 'backend');

function demoPassword() {
  if (process.env.DEMO_PASSWORD) return process.env.DEMO_PASSWORD;
  const line = readFileSync(join(BACKEND, '.env'), 'utf8')
    .split(/\r?\n/)
    .find((l) => l.startsWith('DEMO_PASSWORD='));
  const value = line?.slice('DEMO_PASSWORD='.length).trim().replace(/^"(.*)"$/, '$1');
  if (!value) throw new Error('Falta DEMO_PASSWORD (en el entorno o en backend/.env).');
  return value;
}

const filters = process.argv.slice(2);
const collections = readdirSync(DIR)
  .filter((f) => f.endsWith('.postman_collection.json'))
  .filter((f) => filters.length === 0 || filters.some((text) => f.includes(text)))
  .sort();
const password = demoPassword();
const work = mkdtempSync(join(tmpdir(), 'syncvet-newman-'));
const results = [];

try {
  for (const file of collections) {
    const exported = join(work, file);
    const report = join(work, `${file}.report.json`);
    console.log(`\n=== ${file}`);
    const newman = spawnSync(
      'npx',
      [
        '--no-install', 'newman', 'run', join(DIR, file),
        '--env-var', `demo_password=${password}`,
        '--export-collection', exported,
        '--reporters', 'cli,json', '--reporter-json-export', report,
        '--color', 'off',
      ],
      { cwd: ROOT, stdio: ['ignore', 'pipe', 'inherit'], shell: process.platform === 'win32', encoding: 'utf8' },
    );
    // Solo el resumen del reporte de la consola (la tabla final).
    const table = (newman.stdout ?? '').split(/\r?\n/).filter((l) => /^[│├┌└]/.test(l));
    console.log(table.join('\n'));

    let stats = null;
    let failures = [];
    try {
      const json = JSON.parse(readFileSync(report, 'utf8'));
      stats = json.run.stats;
      failures = json.run.failures.map((f) => `${f.source?.name ?? '?'}: ${f.error?.message ?? ''}`);
    } catch {
      // Sin reporte: la corrida no llegó a empezar.
    }

    let run = null;
    try {
      const variables = JSON.parse(readFileSync(exported, 'utf8')).variable ?? [];
      run = variables.find((v) => v.key === 'run')?.value ?? null;
    } catch {
      // Sin colección exportada.
    }

    let cleanup = 'sin corrida';
    if (run) {
      const artisan = spawnSync('php', ['artisan', 'postman:cleanup', String(run)], {
        cwd: BACKEND,
        env: { ...process.env, XDEBUG_MODE: 'off' },
        encoding: 'utf8',
      });
      cleanup = artisan.status === 0 ? (artisan.stdout.match(/Corrida[^\n]*/)?.[0] ?? 'ok') : `FALLÓ: ${artisan.stderr || artisan.stdout}`;
      console.log(cleanup);
    }

    results.push({
      file,
      run,
      ok: newman.status === 0,
      requests: stats?.requests.total ?? 0,
      assertions: stats?.assertions.total ?? 0,
      failed: stats?.assertions.failed ?? 0,
      failures,
      cleanup,
    });
  }
} finally {
  rmSync(work, { recursive: true, force: true });
}

console.log('\n=== Resumen');
for (const r of results) {
  console.log(
    `${r.ok ? 'OK   ' : 'FALLA'} ${r.file}: ${r.requests} pedidos, ${r.assertions} aserciones, ${r.failed} fallidas; corrida ${r.run ?? '—'}`,
  );
  for (const f of r.failures) console.log(`      ${f}`);
}
const total = results.reduce((acc, r) => ({ requests: acc.requests + r.requests, assertions: acc.assertions + r.assertions, failed: acc.failed + r.failed }), { requests: 0, assertions: 0, failed: 0 });
console.log(`Total: ${results.length} colecciones, ${total.requests} pedidos, ${total.assertions} aserciones, ${total.failed} fallidas.`);
process.exit(results.every((r) => r.ok && !r.cleanup.startsWith('FALLÓ')) ? 0 : 1);
