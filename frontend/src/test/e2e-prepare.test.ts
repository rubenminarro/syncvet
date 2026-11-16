// La preparación de Playwright (e2e/support/prepare.ts), con artisan y la API falsos: sus controles se prueban sin tocar
// ninguna base. Vive en src/test para que /validar-docs cuente el archivo (desvío 14 de la 0.2b).
import { mkdtempSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { migrationsFingerprint } from '../../e2e/support/fingerprint';
import {
  EMPTY_TABLES_CODE,
  EMPTY_TABLES_DONE,
  prepareE2eDatabase,
  RESOLVED_DATABASE_CODE,
} from '../../e2e/support/prepare';

const PASSWORD = 'E2e-prueba#Aa9';
const CURRENT = 'huella-actual';

function fakes(
  options: {
    resolved?: string;
    loginFails?: boolean;
    saved?: string | null;
    emptyOutput?: string;
  } = {},
) {
  const artisanCalls: { args: string[]; env: Record<string, string> }[] = [];
  const apiCalls: string[] = [];
  const written: string[] = [];
  const fingerprints: (string | null)[] = [];

  return {
    artisanCalls,
    apiCalls,
    written,
    fingerprints,
    deps: {
      database: 'syncvet_e2e',
      password: PASSWORD,
      artisan: (args: string[], env: Record<string, string>) => {
        artisanCalls.push({ args, env });
        if (args[0] !== 'tinker') {
          return '';
        }
        // Como tinker: puede imprimir otras líneas antes; lo que importa es la última.
        return args[2] === EMPTY_TABLES_CODE
          ? `Aviso de algo\n${options.emptyOutput ?? EMPTY_TABLES_DONE}\n`
          : `Aviso de algo\n${options.resolved ?? 'syncvet_e2e'}\n`;
      },
      call: <T>(method: string, path: string): Promise<T> => {
        apiCalls.push(`${method} ${path}`);
        if (path === 'login' && options.loginFails === true) {
          return Promise.reject(new Error('POST /api/login respondió 422'));
        }
        const data: Record<string, unknown> = {
          login: { token: '1|t', user: { id: 1, current_branch: { id: 1 } } },
          branches: { id: 2 },
        };
        return Promise.resolve((data[path] ?? null) as T);
      },
      writeCredentials: (password: string) => {
        written.push(password);
      },
      migrationsFingerprint: () => CURRENT,
      readFingerprint: () => options.saved ?? null,
      writeFingerprint: (fingerprint: string | null) => {
        fingerprints.push(fingerprint);
      },
    },
  };
}

const commands = (calls: { args: string[] }[]) =>
  calls.map((call) =>
    call.args[2] === EMPTY_TABLES_CODE ? 'tinker (vaciar)' : call.args.join(' '),
  );

const migrations = (calls: { args: string[] }[]) =>
  calls.filter(
    (call) => call.args[0]?.startsWith('migrate') === true || call.args[0] === 'db:seed',
  );

const FRESH = [
  `tinker --execute ${RESOLVED_DATABASE_CODE}`,
  'migrate --force',
  'migrate:fresh --seed --force',
  'db:seed --class=DemoSeeder --force',
];

const REUSED = [
  `tinker --execute ${RESOLVED_DATABASE_CODE}`,
  'migrate --force',
  'tinker (vaciar)',
  'db:seed --force',
  'db:seed --class=DemoSeeder --force',
];

describe('prepareE2eDatabase', () => {
  it('sin huella guardada: controla la base, la recrea, ingresa, crea la sucursal, guarda la contraseña y la huella', async () => {
    const f = fakes();

    await prepareE2eDatabase(f.deps);

    expect(commands(f.artisanCalls)).toEqual(FRESH);
    expect(f.artisanCalls.every((call) => call.env.DB_DATABASE === 'syncvet_e2e')).toBe(true);
    expect(f.artisanCalls.at(-1)?.env.DEMO_PASSWORD).toBe(PASSWORD);
    expect(f.apiCalls).toEqual(['POST login', 'POST branches', 'PATCH users/1', 'POST logout']);
    expect(f.written).toEqual([PASSWORD]);
    // Se borra antes de tocar la base y se guarda al terminar.
    expect(f.fingerprints).toEqual([null, CURRENT]);
  });

  it('con la misma huella: reutiliza la base, vacía las tablas y vuelve a sembrar, sin migrate:fresh', async () => {
    const f = fakes({ saved: CURRENT });

    await prepareE2eDatabase(f.deps);

    expect(commands(f.artisanCalls)).toEqual(REUSED);
    expect(f.artisanCalls.every((call) => call.env.DB_DATABASE === 'syncvet_e2e')).toBe(true);
    expect(f.artisanCalls.at(-1)?.env.DEMO_PASSWORD).toBe(PASSWORD);
    expect(f.apiCalls).toEqual(['POST login', 'POST branches', 'PATCH users/1', 'POST logout']);
    expect(f.fingerprints).toEqual([null, CURRENT]);
  });

  it('con otra huella (cambiaron las migraciones), la recrea', async () => {
    const f = fakes({ saved: 'huella-vieja' });

    await prepareE2eDatabase(f.deps);

    expect(commands(f.artisanCalls)).toEqual(FRESH);
  });

  it('con fresh (E2E_FRESH=1), la recrea aunque la huella sea la misma', async () => {
    const f = fakes({ saved: CURRENT });

    await prepareE2eDatabase({ ...f.deps, fresh: true });

    expect(commands(f.artisanCalls)).toEqual(FRESH);
  });

  it('si vaciar las tablas falla (tinker sin la marca), se detiene sin sembrar y sin huella', async () => {
    const f = fakes({ saved: CURRENT, emptyOutput: 'PHP Error: algo' });

    await expect(prepareE2eDatabase(f.deps)).rejects.toThrow(/No se pudieron vaciar las tablas/);
    expect(commands(f.artisanCalls)).toEqual(REUSED.slice(0, 3));
    expect(f.apiCalls).toEqual([]);
    expect(f.fingerprints).toEqual([null]);
  });

  it('paso 1: con otra base pedida no corre nada', async () => {
    const f = fakes({ saved: CURRENT });

    await expect(prepareE2eDatabase({ ...f.deps, database: 'syncvet' })).rejects.toThrow(
      /solo corre contra la base syncvet_e2e/,
    );
    expect(f.artisanCalls).toEqual([]);
    expect(f.fingerprints).toEqual([]);
  });

  it.each(['syncvet', 'syncvet_e2e_copia', ''])(
    'paso 2: si Laravel resuelve %j (configuración cacheada, otra variable) no migra ni vacía nada',
    async (resolved) => {
      const f = fakes({ resolved, saved: CURRENT });

      await expect(prepareE2eDatabase(f.deps)).rejects.toThrow(
        /solo corre contra la base syncvet_e2e/,
      );
      expect(migrations(f.artisanCalls)).toEqual([]);
      expect(commands(f.artisanCalls)).not.toContain('tinker (vaciar)');
      expect(f.apiCalls).toEqual([]);
      expect(f.written).toEqual([]);
      expect(f.fingerprints).toEqual([]);
    },
  );

  it('paso 4: si el backend no acepta la contraseña de la corrida (usa otra base), se detiene sin escribir por la API ni guardar la huella', async () => {
    const f = fakes({ loginFails: true, saved: CURRENT });

    await expect(prepareE2eDatabase(f.deps)).rejects.toThrow(
      /no usa la base syncvet_e2e recién preparada/,
    );
    expect(f.apiCalls).toEqual(['POST login']);
    expect(f.written).toEqual([]);
    expect(f.fingerprints).toEqual([null]);
  });
});

describe('migrationsFingerprint', () => {
  const dirs: string[] = [];

  afterEach(() => {
    for (const dir of dirs.splice(0)) {
      rmSync(dir, { recursive: true, force: true });
    }
  });

  function migrationsDir(files: Record<string, string>): string {
    const dir = mkdtempSync(join(tmpdir(), 'syncvet-migraciones-'));
    dirs.push(dir);
    for (const [name, content] of Object.entries(files)) {
      writeFileSync(join(dir, name), content);
    }
    return dir;
  }

  it('la misma carpeta da la misma huella; cambiar, agregar o renombrar una migración da otra', () => {
    const dir = migrationsDir({ '0001_a.php': 'uno', '0002_b.php': 'dos' });
    const original = migrationsFingerprint(dir);

    expect(migrationsFingerprint(dir)).toBe(original);
    expect(migrationsFingerprint(migrationsDir({ '0002_b.php': 'dos', '0001_a.php': 'uno' }))).toBe(
      original,
    );

    writeFileSync(join(dir, '0002_b.php'), 'dos, cambiada');
    const changed = migrationsFingerprint(dir);
    expect(changed).not.toBe(original);

    writeFileSync(join(dir, '0003_c.php'), 'tres');
    const added = migrationsFingerprint(dir);
    expect(added).not.toBe(changed);

    renameSync(join(dir, '0003_c.php'), join(dir, '0004_c.php'));
    expect(migrationsFingerprint(dir)).not.toBe(added);
  });

  it('el nombre y el contenido no se confunden: el archivo "ab" con "c" no es el "a" con "bc"', () => {
    expect(migrationsFingerprint(migrationsDir({ ab: 'c' }))).not.toBe(
      migrationsFingerprint(migrationsDir({ a: 'bc' })),
    );
  });
});
