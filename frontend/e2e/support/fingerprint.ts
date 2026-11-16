import { createHash } from 'node:crypto';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * La huella de las migraciones: el SHA-256 de los nombres y el contenido de los archivos de la carpeta, en orden. Si una
 * migración se agrega, se borra, se renombra o cambia, la huella es otra y la preparación recrea syncvet_e2e desde cero.
 */
export function migrationsFingerprint(directory: string): string {
  const hash = createHash('sha256');

  for (const name of readdirSync(directory).sort()) {
    hash.update(name);
    hash.update('\0');
    hash.update(readFileSync(join(directory, name)));
    hash.update('\0');
  }

  return hash.digest('hex');
}
