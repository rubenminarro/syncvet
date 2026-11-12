import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { E2E_DATABASE } from './support/database';
import { BACKEND_URL, CREDENTIALS_FILE, PASSWORD_ENV } from './support/env';
import { prepareE2eDatabase } from './support/prepare';

const BACKEND_DIR = resolve(import.meta.dirname, '..', '..', 'backend');

interface Envelope<T> {
  success: boolean;
  message: string;
  data: T;
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

function writeCredentials(password: string): void {
  writeFileSync(
    CREDENTIALS_FILE,
    [
      '# syncvet_e2e: la contraseña de los usuarios del DemoSeeder en la última corrida de npm run test:e2e.',
      '# La genera e2e/global-setup.ts en cada corrida; no se versiona (frontend/.gitignore).',
      `DEMO_PASSWORD=${password}`,
      '',
    ].join('\n'),
  );
}

/**
 * Prepara syncvet_e2e antes de las pruebas (corre después de levantar el backend y Vite, los webServer de la
 * configuración): los pasos y sus controles, en e2e/support/prepare.ts.
 */
export default async function globalSetup(): Promise<void> {
  const password = runPassword();

  await prepareE2eDatabase({
    // E2E_DB_DATABASE solo para probar la guarda a mano: con cualquier otro nombre, se niega.
    database: process.env.E2E_DB_DATABASE ?? E2E_DATABASE,
    password,
    artisan,
    call,
    writeCredentials,
  });

  process.env[PASSWORD_ENV] = password;
}
