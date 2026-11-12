import { afterEach, describe, expect, it } from 'vitest';
import { createBroadcastSessionChannel, isSessionMessage, type SessionMessage } from './channel';

const SESSION = { token: '7|secreto', expires_at: '2026-11-13T00:00:00.000000Z' };

const LOGOUT: SessionMessage = { type: 'logout', token_id: '7', notice: null, all: true };

const VALID: SessionMessage[] = [
  { type: 'session-request' },
  { type: 'session-share', session: SESSION },
  LOGOUT,
  { type: 'logout', token_id: '7', notice: 'Tu sesión terminó. Ingresá de nuevo.', all: false },
];

const INVALID: [string, unknown][] = [
  ['null', null],
  ['un texto', 'logout'],
  ['un arreglo', [{ type: 'logout' }]],
  ['un tipo desconocido', { type: 'branch-changed' }],
  ['session-request con campos de más', { type: 'session-request', token: '7|x' }],
  ['session-share sin sesión', { type: 'session-share' }],
  ['session-share con token vacío', { type: 'session-share', session: { ...SESSION, token: '' } }],
  [
    'session-share con expires_at inválido',
    { type: 'session-share', session: { ...SESSION, expires_at: 'mañana' } },
  ],
  ['logout sin token_id', { type: 'logout', notice: null, all: true }],
  ['logout con token_id numérico', { type: 'logout', token_id: 7, notice: null, all: true }],
  ['logout con notice objeto', { type: 'logout', token_id: '7', notice: { x: 1 }, all: true }],
  ['logout sin all', { type: 'logout', token_id: '7', notice: null }],
  ['logout con all texto', { type: 'logout', token_id: '7', notice: null, all: 'true' }],
];

describe('isSessionMessage', () => {
  it.each(VALID.map((message) => [message.type, message] as const))('acepta %s', (_, message) => {
    expect(isSessionMessage(message)).toBe(true);
  });

  it.each(INVALID)('descarta %s', (_, value) => {
    expect(isSessionMessage(value)).toBe(false);
  });
});

describe('createBroadcastSessionChannel (BroadcastChannel real)', () => {
  const open: { close: () => void }[] = [];

  afterEach(() => {
    for (const channel of open.splice(0)) {
      channel.close();
    }
  });

  it('entrega los mensajes válidos a las otras pestañas y descarta los demás', async () => {
    const name = `syncvet-sesion-test-${String(Math.random())}`;
    const receiver = createBroadcastSessionChannel(name);
    const sender = createBroadcastSessionChannel(name);
    const raw = new BroadcastChannel(name);
    open.push(receiver, sender, raw);
    const received: unknown[] = [];
    receiver.subscribe((message) => {
      received.push(message);
    });

    for (const [, value] of INVALID) {
      raw.postMessage(value);
    }

    sender.post(LOGOUT);

    await new Promise((resolve) => setTimeout(resolve, 50));

    expect(received).toEqual([LOGOUT]);
  });
});
