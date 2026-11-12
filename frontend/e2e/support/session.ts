import { expect, type Page } from '@playwright/test';
import { PASSWORD_ENV } from './env';

/** Los usuarios de la Clínica A del DemoSeeder (docs/05-pruebas/guia-postman.md). */
export const USERS = {
  admin: { email: 'admin-a@example.com', name: 'Ana Gómez' },
  recepcion: { email: 'recepcion-a@example.com', name: 'Rosa Benítez' },
  vet: { email: 'vet-a@example.com', name: 'Víctor Acosta' },
} as const;

export type User = (typeof USERS)[keyof typeof USERS];

/** La contraseña de esta corrida (la generó la preparación). */
export function demoPassword(): string {
  const password = process.env[PASSWORD_ENV];

  if (password === undefined || password === '') {
    throw new Error(
      'Falta la contraseña de la corrida: la preparación (global-setup.ts) no corrió.',
    );
  }

  return password;
}

/** Completa el formulario de /ingresar (la página ya tiene que estar abierta) y espera salir de él. */
export async function submitLogin(page: Page, user: User, password = demoPassword()) {
  await page.getByLabel('Email', { exact: true }).fill(user.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Ingresar' }).click();
  await expect(page.locator('.session-user')).toHaveText(user.name);
}

/** Ingresa desde /ingresar y espera el inicio. */
export async function login(page: Page, user: User, password = demoPassword()) {
  await page.goto('/ingresar');
  await submitLogin(page, user, password);
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible();
}

export async function logout(page: Page) {
  await page.getByRole('button', { name: 'Salir' }).click();
  await expect(page).toHaveURL(/\/ingresar$/);
}
