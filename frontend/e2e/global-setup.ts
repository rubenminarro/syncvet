import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { resolve } from 'node:path';
import { assertE2eDatabase, E2E_DATABASE } from './support/database';
import { BACKEND_URL, PASSWORD_ENV, SECOND_BRANCH } from './support/env';

const BACKEND_DIR = resolve(import.meta.dirname, '..', '..', 'backend');

/** La base pedida: E2E_DB_DATABASE si se pasa (para que la guarda se pueda probar a mano), si no syncvet_e2e. */
const DATABASE = process.env.E2E_DB_DATABASE ?? E2E_DATABASE;

interface Envelope<T> {
  success: boolean;
  message: string;
  data: T;
}

interface MeData {
  id: number;
  current_branch: { id: number } | null;
}

/** Una contraseña nueva por corrida (cumple la regla del proyecto): solo un backend con la base recién preparada la acepta. */
function runPassword(): string {
  return `E2e-${randomBytes(8).toString('hex')}#Aa9`;
}

function artisan(args: string[], env: Record<string, string>): string {
  return execFileSync('php', ['artisan', ...args, '--no-interaction'], {
    cwd: BACKEND_DIR,
    env: { ...process.env, ...env },
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
}

async function call<T>(method: string, path: string, body?: unknown, token?: string): Promise<T> {
  const response = await fetch(`${BACKEND_URL}/api/${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token === undefined ? {} : { Authorization: `Bearer ${token}` }),
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  const json = (await response.json()) as Envelope<T>;

  if (!response.ok || !json.success) {
    throw new Error(`${method} /api/${path} respondió ${String(response.status)}: ${json.message}`);
  }

  return json.data;
}

/**
 * Prepara syncvet_e2e antes de las pruebas (corre después de levantar el backend y Vite, los webServer de la
 * configuración). Se niega a seguir si la base no es syncvet_e2e, si Laravel resuelve otra (una configuración cacheada) o
 * si el backend de :8010 no usa la base recién preparada.
 */
export default async function globalSetup(): Promise<void> {
  // 1. El nombre pedido.
  assertE2eDatabase(DATABASE);
  const env = { DB_DATABASE: DATABASE };

  // 2. La base que Laravel resuelve de verdad con ese entorno (con la configuración cacheada, sería otra).
  const resolved = artisan(
    [
      'tinker',
      '--execute',
      "echo config('database.connections.'.config('database.default').'.database');",
    ],
    env,
  )
    .trim()
    .split(/\r?\n/)
    .pop();
  assertE2eDatabase(resolved);

  // 3. La base desde cero, con los roles y los datos de demostración. migrate crea la base si falta.
  const password = runPassword();
  artisan(['migrate', '--force'], env);
  artisan(['migrate:fresh', '--seed', '--force'], env);
  artisan(['db:seed', '--class=DemoSeeder', '--force'], { ...env, DEMO_PASSWORD: password });

  // 4. El backend de :8010 usa la base recién preparada: solo ahí vale la contraseña de esta corrida.
  let login: { token: string; user: MeData };
  try {
    login = await call('POST', 'login', { email: 'admin-a@example.com', password });
  } catch (error) {
    throw new Error(
      `El backend de ${BACKEND_URL} no usa la base ${E2E_DATABASE} recién preparada (no aceptó la contraseña de esta corrida). Levantalo con DB_DATABASE=${E2E_DATABASE}.`,
      { cause: error },
    );
  }

  // 5. Una segunda sucursal para admin-a (el DemoSeeder da una sola por usuario), por la API.
  const branch = await call<{ id: number }>(
    'POST',
    'branches',
    {
      code: SECOND_BRANCH.code,
      name: SECOND_BRANCH.name,
      establishment: '002',
      warehouse_code: `DEP-${SECOND_BRANCH.code}`,
    },
    login.token,
  );
  const current = login.user.current_branch?.id;
  await call(
    'PATCH',
    `users/${String(login.user.id)}`,
    { branch_ids: current === undefined ? [branch.id] : [current, branch.id] },
    login.token,
  );
  await call('POST', 'logout', undefined, login.token);

  process.env[PASSWORD_ENV] = password;
}
