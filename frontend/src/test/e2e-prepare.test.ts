// La preparación de Playwright (e2e/support/prepare.ts), con artisan y la API falsos: sus controles se prueban sin tocar
// ninguna base. Vive en src/test para que /validar-docs cuente el archivo (desvío 14 de la 0.2b).
import { describe, expect, it } from 'vitest';
import { prepareE2eDatabase, RESOLVED_DATABASE_CODE } from '../../e2e/support/prepare';

const PASSWORD = 'E2e-prueba#Aa9';

function fakes(options: { resolved?: string; loginFails?: boolean } = {}) {
  const artisanCalls: { args: string[]; env: Record<string, string> }[] = [];
  const apiCalls: string[] = [];
  const written: string[] = [];

  return {
    artisanCalls,
    apiCalls,
    written,
    deps: {
      database: 'syncvet_e2e',
      password: PASSWORD,
      artisan: (args: string[], env: Record<string, string>) => {
        artisanCalls.push({ args, env });
        // Como tinker: puede imprimir otras líneas antes; la base es la última.
        return args[0] === 'tinker' ? `Aviso de algo\n${options.resolved ?? 'syncvet_e2e'}\n` : '';
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
    },
  };
}

const migrations = (calls: { args: string[] }[]) =>
  calls.filter(
    (call) => call.args[0]?.startsWith('migrate') === true || call.args[0] === 'db:seed',
  );

describe('prepareE2eDatabase', () => {
  it('con todo en orden: controla la base, la recrea, ingresa, crea la sucursal y guarda la contraseña', async () => {
    const f = fakes();

    await prepareE2eDatabase(f.deps);

    expect(f.artisanCalls.map((call) => call.args.join(' '))).toEqual([
      `tinker --execute ${RESOLVED_DATABASE_CODE}`,
      'migrate --force',
      'migrate:fresh --seed --force',
      'db:seed --class=DemoSeeder --force',
    ]);
    expect(f.artisanCalls.every((call) => call.env.DB_DATABASE === 'syncvet_e2e')).toBe(true);
    expect(f.artisanCalls[3]?.env.DEMO_PASSWORD).toBe(PASSWORD);
    expect(f.apiCalls).toEqual(['POST login', 'POST branches', 'PATCH users/1', 'POST logout']);
    expect(f.written).toEqual([PASSWORD]);
  });

  it('paso 1: con otra base pedida no corre nada', async () => {
    const f = fakes();

    await expect(prepareE2eDatabase({ ...f.deps, database: 'syncvet' })).rejects.toThrow(
      /solo corre contra la base syncvet_e2e/,
    );
    expect(f.artisanCalls).toEqual([]);
  });

  it.each(['syncvet', 'syncvet_e2e_copia', ''])(
    'paso 2: si Laravel resuelve %j (configuración cacheada, otra variable) no migra nada',
    async (resolved) => {
      const f = fakes({ resolved });

      await expect(prepareE2eDatabase(f.deps)).rejects.toThrow(
        /solo corre contra la base syncvet_e2e/,
      );
      expect(migrations(f.artisanCalls)).toEqual([]);
      expect(f.apiCalls).toEqual([]);
      expect(f.written).toEqual([]);
    },
  );

  it('paso 4: si el backend no acepta la contraseña de la corrida (usa otra base), se detiene sin escribir por la API', async () => {
    const f = fakes({ loginFails: true });

    await expect(prepareE2eDatabase(f.deps)).rejects.toThrow(
      /no usa la base syncvet_e2e recién preparada/,
    );
    expect(f.apiCalls).toEqual(['POST login']);
    expect(f.written).toEqual([]);
  });
});
