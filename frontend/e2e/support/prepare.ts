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
  /** La huella actual de backend/database/migrations (support/fingerprint.ts). */
  migrationsFingerprint: () => string;
  /** La huella guardada por la última preparación que terminó bien, o `null`. */
  readFingerprint: () => string | null;
  /** Guarda la huella (o la borra, con `null`) en el archivo ignorado por Git. */
  writeFingerprint: (fingerprint: string | null) => void;
  /** `true` recrea la base aunque las migraciones no hayan cambiado (E2E_FRESH=1). */
  fresh?: boolean;
}

interface MeData {
  id: number;
  current_branch: { id: number } | null;
}

/** Lo que Laravel resuelve como base con ese entorno (la última línea de la salida de tinker). */
export const RESOLVED_DATABASE_CODE =
  "echo config('database.connections.'.config('database.default').'.database');";

/** La marca que imprime EMPTY_TABLES_CODE al terminar: sin ella, tinker falló (no siempre devuelve un código de error). */
export const EMPTY_TABLES_DONE = 'e2e-tablas-vacias';

/**
 * Vacía syncvet_e2e sin tocar su esquema (el camino rápido de la preparación): TRUNCATE de cada tabla, menos `migrations`,
 * que tenga filas o el autoincremental avanzado (vacía, pero con registros creados y borrados por los casos), así los ids
 * vuelven a empezar como en una base recién creada. Las vacías desde el principio se saltean: TRUNCATE es lo que tarda.
 * `information_schema_stats_expiry = 0`: sin él, MySQL 8 da un AUTO_INCREMENT guardado de hasta un día atrás.
 */
export const EMPTY_TABLES_CODE = [
  'use Illuminate\\Support\\Facades\\DB;',
  'use Illuminate\\Support\\Facades\\Schema;',
  "DB::statement('SET SESSION information_schema_stats_expiry = 0');",
  '$tables = DB::select("select table_name as name, auto_increment as next_id from information_schema.tables where table_schema = ? and table_type = \'BASE TABLE\'", [DB::getDatabaseName()]);',
  'Schema::disableForeignKeyConstraints();',
  "foreach ($tables as $table) { if ($table->name !== 'migrations' && ((int) $table->next_id > 1 || DB::table($table->name)->exists())) { DB::table($table->name)->truncate(); } }",
  'Schema::enableForeignKeyConstraints();',
  `echo '${EMPTY_TABLES_DONE}';`,
].join(' ');

/** La última línea de la salida de tinker (puede imprimir avisos antes). */
function lastLine(output: string): string | undefined {
  return output.trim().split(/\r?\n/).pop();
}

/**
 * Prepara syncvet_e2e. Se niega a seguir, sin tocar nada, si la base pedida no es syncvet_e2e (1) o si Laravel resuelve
 * otra con ese entorno, por ejemplo con la configuración cacheada (2). La base (3): si las migraciones son las de la última
 * preparación que terminó bien (la misma huella), se reutiliza y solo se vacían sus tablas; si no, se recrea desde cero. En
 * los dos casos, con los roles y los datos de demostración de siempre. Después se niega a seguir si el backend de las
 * pruebas no la usa: solo ahí vale la contraseña de esta corrida (4).
 */
export async function prepareE2eDatabase(deps: PrepareDeps): Promise<void> {
  const { database, password, artisan, call } = deps;

  // 1. El nombre pedido.
  assertE2eDatabase(database);
  const env = { DB_DATABASE: database };

  // 2. La base que Laravel resuelve de verdad con ese entorno.
  assertE2eDatabase(lastLine(artisan(['tinker', '--execute', RESOLVED_DATABASE_CODE], env)));

  // 3. La base, con los roles y los datos de demostración. La huella se borra antes de tocarla y se guarda solo al terminar
  // bien: una preparación cortada a la mitad hace que la próxima la recree. migrate crea la base si falta.
  const fingerprint = deps.migrationsFingerprint();
  const reuse = deps.fresh !== true && deps.readFingerprint() === fingerprint;
  deps.writeFingerprint(null);
  artisan(['migrate', '--force'], env);

  if (reuse) {
    if (lastLine(artisan(['tinker', '--execute', EMPTY_TABLES_CODE], env)) !== EMPTY_TABLES_DONE) {
      throw new Error(`No se pudieron vaciar las tablas de ${E2E_DATABASE}.`);
    }
    artisan(['db:seed', '--force'], env);
  } else {
    artisan(['migrate:fresh', '--seed', '--force'], env);
  }
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

  // 7. La huella de las migraciones con que quedó la base: la próxima corrida la reutiliza si no cambiaron.
  deps.writeFingerprint(fingerprint);
}
