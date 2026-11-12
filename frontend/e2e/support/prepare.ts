import { assertE2eDatabase, E2E_DATABASE } from './database';
import { BACKEND_URL, SECOND_BRANCH } from './env';

/** Lo que la preparación necesita de afuera: con dependencias inyectadas, sus pasos se prueban con Vitest. */
export interface PrepareDeps {
  /** La base pedida (syncvet_e2e). */
  database: string;
  /** La contraseña de los usuarios del DemoSeeder en esta corrida. */
  password: string;
  /** `php artisan <args>` desde backend/ con esas variables de entorno; devuelve la salida. */
  artisan: (args: string[], env: Record<string, string>) => string;
  /** Un pedido a la API del backend de las pruebas; falla con un Error si no es un 2xx. */
  call: <T>(method: string, path: string, body?: unknown, token?: string) => Promise<T>;
  /** Guarda la contraseña de la corrida en el archivo ignorado por Git (para entrar a mano a syncvet_e2e). */
  writeCredentials: (password: string) => void;
}

interface MeData {
  id: number;
  current_branch: { id: number } | null;
}

/** Lo que Laravel resuelve como base con ese entorno (la última línea de la salida de tinker). */
export const RESOLVED_DATABASE_CODE =
  "echo config('database.connections.'.config('database.default').'.database');";

/**
 * Prepara syncvet_e2e. Se niega a seguir, sin tocar nada, si la base pedida no es syncvet_e2e (1) o si Laravel resuelve
 * otra con ese entorno, por ejemplo con la configuración cacheada (2). Después de recrearla (3), se niega a seguir si el
 * backend de las pruebas no la usa: solo ahí vale la contraseña de esta corrida (4).
 */
export async function prepareE2eDatabase(deps: PrepareDeps): Promise<void> {
  const { database, password, artisan, call } = deps;

  // 1. El nombre pedido.
  assertE2eDatabase(database);
  const env = { DB_DATABASE: database };

  // 2. La base que Laravel resuelve de verdad con ese entorno.
  const resolved = artisan(['tinker', '--execute', RESOLVED_DATABASE_CODE], env)
    .trim()
    .split(/\r?\n/)
    .pop();
  assertE2eDatabase(resolved);

  // 3. La base desde cero, con los roles y los datos de demostración. migrate crea la base si falta.
  artisan(['migrate', '--force'], env);
  artisan(['migrate:fresh', '--seed', '--force'], env);
  artisan(['db:seed', '--class=DemoSeeder', '--force'], { ...env, DEMO_PASSWORD: password });

  // 4. El backend de las pruebas usa la base recién preparada.
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

  // 6. La contraseña, para entrar a mano a syncvet_e2e (desvío 2 de la 0.2b).
  deps.writeCredentials(password);
}
