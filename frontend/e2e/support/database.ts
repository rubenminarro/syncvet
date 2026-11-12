/**
 * La base de las pruebas de punta a punta. La preparación la borra entera (migrate:fresh): por eso se niega a correr con
 * cualquier otro nombre, aunque se parezca (mayúsculas, espacios, un sufijo).
 */
export const E2E_DATABASE = 'syncvet_e2e';

export function assertE2eDatabase(name: string | undefined): void {
  if (name !== E2E_DATABASE) {
    throw new Error(
      `La preparación de Playwright solo corre contra la base ${E2E_DATABASE} y recibió ${JSON.stringify(name ?? null)}: no se toca nada.`,
    );
  }
}
