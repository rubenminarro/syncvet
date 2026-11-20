// Corre con Newman las colecciones de docs/05-pruebas/postman y, después de cada una, borra lo que dejó su corrida con
// `php artisan postman:cleanup <corrida>` (cierre de la F3 del frontend): la regresión no deja datos en la base.
//
// Desde la raíz del repositorio, con el servidor levantado desde backend/ en el puerto 8001 (`php artisan serve --port=8001`):
//
//   node docs/05-pruebas/newman-todas.mjs                 todas las colecciones
//   node docs/05-pruebas/newman-todas.mjs turnos series   solo las que contienen esos textos en el nombre
//
// La contraseña de la demo sale de DEMO_PASSWORD o, si no está, de backend/.env; no se muestra ni va en la línea de comandos. Cada colección copia su número
// de corrida a la variable global `postman_run`, que se lee de las globales exportadas al terminar (--export-globals, en la
// carpeta temporal del sistema). La limpieza revoca también los tokens de los usuarios de la demo creados desde que empezó la
// colección (--tokens-since): las colecciones ingresan y no salen. Sale con 1 si una colección o su limpieza fallan.
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
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

const readJson = (path) => {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch {
    return null;
  }
};

const filters = process.argv.slice(2);
const collections = readdirSync(DIR)
  .filter((f) => f.endsWith('.postman_collection.json'))
  .filter((f) => filters.length === 0 || filters.some((text) => f.includes(text)))
  .sort();
const password = demoPassword();
const work = mkdtempSync(join(tmpdir(), 'syncvet-newman-'));
// La contraseña va en un entorno de Postman en la carpeta temporal (se borra al terminar), nunca en la línea de comandos: con
// el shell de Windows, un símbolo de la contraseña (& | ^ %) rompería el comando, y la línea se ve en la lista de procesos.
const environment = join(work, 'entorno.postman_environment.json');
writeFileSync(
  environment,
  JSON.stringify({ name: 'newman-todas', values: [{ key: 'demo_password', value: password, enabled: true }] }),
  { mode: 0o600 },
);
const results = [];

try {
  for (const file of collections) {
    const globals = join(work, `${file}.globals.json`);
    const report = join(work, `${file}.report.json`);
    const since = new Date().toISOString();
    console.log(`\n=== ${file}`);
    const newman = spawnSync(
      'npx',
      [
        '--no-install', 'newman', 'run', join(DIR, file),
        '--environment', environment,
        '--export-globals', globals,
        '--reporters', 'cli,json', '--reporter-json-export', report,
        '--color', 'off',
      ],
      { cwd: ROOT, stdio: ['ignore', 'pipe', 'inherit'], shell: process.platform === 'win32', encoding: 'utf8' },
    );
    // Solo la tabla final del reporte de la consola.
    console.log((newman.stdout ?? '').split(/\r?\n/).filter((l) => /^[│├┌└]/.test(l)).join('\n'));

    const json = readJson(report);
    const stats = json?.run.stats ?? null;
    const failures = (json?.run.failures ?? []).map((f) => `${f.source?.name ?? '?'}: ${f.error?.message ?? ''}`);
    const run = (readJson(globals)?.values ?? []).find((v) => v.key === 'postman_run')?.value ?? null;

    let cleanup = 'FALLÓ: sin número de corrida (postman_run)';
    if (run) {
      const artisan = spawnSync('php', ['artisan', 'postman:cleanup', String(run), `--tokens-since=${since}`], {
        cwd: BACKEND,
        env: { ...process.env, XDEBUG_MODE: 'off' },
        encoding: 'utf8',
      });
      cleanup =
        artisan.status === 0
          ? (artisan.stdout.match(/(Corrida|Tokens)[^\n]*/g) ?? ['ok']).map((l) => l.trim()).join(' ')
          : `FALLÓ: ${artisan.stderr || artisan.stdout}`;
    }
    console.log(cleanup);

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
  console.log(`      Limpieza: ${r.cleanup}`);
}
const total = results.reduce(
  (acc, r) => ({ requests: acc.requests + r.requests, assertions: acc.assertions + r.assertions, failed: acc.failed + r.failed }),
  { requests: 0, assertions: 0, failed: 0 },
);
console.log(`Total: ${results.length} colecciones, ${total.requests} pedidos, ${total.assertions} aserciones, ${total.failed} fallidas.`);
process.exit(results.every((r) => r.ok && !r.cleanup.startsWith('FALLÓ')) ? 0 : 1);
