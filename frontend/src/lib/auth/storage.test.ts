import { describe, expect, it } from 'vitest';
import { memoryStorage } from '../../test/session';
import {
  clearSession,
  expiresAtMs,
  readSession,
  saveSession,
  SESSION_KEY,
  tokenId,
} from './storage';

const SESSION = { token: '7|secreto', expires_at: '2026-11-13T00:00:00.000Z' };

describe('storage', () => {
  it('guarda y lee la sesión en syncvet.session, solo con el token y el vencimiento', () => {
    const storage = memoryStorage();

    saveSession(storage, { ...SESSION, extra: 'no' } as typeof SESSION);

    expect(JSON.parse(storage.data.get(SESSION_KEY) ?? '')).toEqual(SESSION);
    expect(readSession(storage)).toEqual(SESSION);
  });

  it.each([
    ['un JSON roto', '{no es json'],
    ['sin token', JSON.stringify({ expires_at: SESSION.expires_at })],
    ['con el token vacío', JSON.stringify({ token: '', expires_at: SESSION.expires_at })],
    ['con expires_at inválido', JSON.stringify({ token: '7|x', expires_at: 'mañana' })],
  ])('con %s no hay sesión y se borra', (_, raw) => {
    const storage = memoryStorage();
    storage.setItem(SESSION_KEY, raw);

    expect(readSession(storage)).toBeNull();
    expect(storage.data.has(SESSION_KEY)).toBe(false);
  });

  it('borra la sesión', () => {
    const storage = memoryStorage();
    saveSession(storage, SESSION);

    clearSession(storage);

    expect(readSession(storage)).toBeNull();
  });

  it('lee expires_at con el formato de la API (6 decimales, UTC)', () => {
    const storage = memoryStorage();
    saveSession(storage, { token: '7|x', expires_at: '2026-11-13T00:00:00.000000Z' });

    const session = readSession(storage);

    expect(session).not.toBeNull();
    expect(expiresAtMs(session ?? SESSION)).toBe(Date.UTC(2026, 10, 13, 0, 0, 0));
  });

  it('el id del token es la parte anterior a |', () => {
    expect(tokenId('7|secreto')).toBe('7');
    expect(tokenId('sin-separador')).toBe('');
  });
});
