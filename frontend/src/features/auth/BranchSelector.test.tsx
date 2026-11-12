import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { delay, http } from 'msw';
import { describe, expect, it } from 'vitest';
import { branchKey } from '../../lib/api/query-keys';
import type { SessionMessage } from '../../lib/auth/channel';
import { failure, success } from '../../test/api';
import { meFor } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { Me } from './types';

const CENTRAL = { id: 10, code: 'CEN', name: 'Casa central' };
const NORTE = { id: 11, code: 'NOR', name: 'Sucursal Norte' };
const ME_TWO: Me = meFor('administrador', { branches: [CENTRAL, NORTE], current_branch: CENTRAL });
const NOT_AVAILABLE = 'La sucursal no existe, está inactiva o no está asignada a tu usuario.';

function selector(): HTMLSelectElement {
  return screen.getByLabelText<HTMLSelectElement>('Sucursal actual');
}

describe('selector de sucursal', () => {
  it('lista me.branches con la actual elegida', async () => {
    server.use(meHandler(ME_TWO));
    renderApp('/', { session: sessionFor() });

    await screen.findByText('Inicio', { selector: 'h2' });
    expect(Array.from(selector().options).map((option) => option.text)).toEqual([
      'Casa central',
      'Sucursal Norte',
    ]);
    expect(selector().value).toBe('10');
  });

  it('con una sola sucursal, que ya es la actual, solo muestra el nombre', async () => {
    server.use(meHandler(meFor('recepcionista', { branches: [CENTRAL], current_branch: CENTRAL })));
    renderApp('/', { session: sessionFor() });

    await screen.findByText('Inicio', { selector: 'h2' });
    expect(screen.queryByLabelText('Sucursal actual')).not.toBeInTheDocument();
    expect(screen.getByText('Casa central', { selector: '.session-branch' })).toBeInTheDocument();
  });

  /** El servidor: GET me devuelve `serverMe.current`; el PATCH la cambia (salvo `patchSets: false`). */
  function branchServer(options: { patchSets?: boolean; delayMs?: number } = {}) {
    const state = { current: ME_TWO, bodies: [] as unknown[], meCalls: 0 };
    server.use(
      http.get('/api/me', () => {
        state.meCalls++;
        return success(state.current);
      }),
      http.patch('/api/me/current-branch', async ({ request }) => {
        const body = (await request.json()) as { branch_id: number };
        state.bodies.push(body);
        if (options.delayMs !== undefined) {
          await delay(options.delayMs);
        }
        const chosen = [CENTRAL, NORTE].find((branch) => branch.id === body.branch_id) ?? null;
        if (options.patchSets !== false) {
          state.current = { ...ME_TWO, current_branch: chosen };
        }
        return success({ ...ME_TWO, current_branch: chosen });
      }),
    );

    return state;
  }

  it('al cambiar: PATCH me/current-branch, me vuelto a pedir, solo las consultas de la sucursal invalidadas y branch-changed', async () => {
    const state = branchServer();
    const { queryClient, hub } = renderApp('/', { session: sessionFor() });
    const received: SessionMessage[] = [];
    hub.connect().subscribe((message) => received.push(message));

    await screen.findByText('Inicio', { selector: 'h2' });
    queryClient.setQueryData(branchKey(10, 'appointments', 'queue'), []);
    queryClient.setQueryData(['species'], []);
    const meCallsBefore = state.meCalls;
    await userEvent.selectOptions(selector(), '11');

    expect(await screen.findByText('Tu sucursal actual es Sucursal Norte.')).toBeInTheDocument();
    expect(state.bodies).toEqual([{ branch_id: 11 }]);
    expect(state.meCalls).toBe(meCallsBefore + 1);
    expect(selector().value).toBe('11');
    expect(screen.getByText('Estás trabajando en Sucursal Norte.')).toBeInTheDocument();
    // B6: solo las que dependen de la sucursal (query-keys.ts); los catálogos de la clínica, no.
    expect(queryClient.getQueryState(branchKey(10, 'appointments', 'queue'))?.isInvalidated).toBe(
      true,
    );
    expect(queryClient.getQueryState(['species'])?.isInvalidated).toBe(false);
    await waitFor(() => {
      expect(received).toContainEqual({ type: 'branch-changed' });
    });
  });

  it('después del PATCH muestra la sucursal que quedó en el servidor, no la de la respuesta (B5: otra pestaña cambió a la vez)', async () => {
    // El PATCH responde Norte, pero otra pestaña ya dejó Casa central: GET me manda.
    branchServer({ patchSets: false });
    renderApp('/', { session: sessionFor() });

    await screen.findByText('Inicio', { selector: 'h2' });
    await userEvent.selectOptions(selector(), '11');

    expect(await screen.findByText('Tu sucursal actual es Casa central.')).toBeInTheDocument();
    expect(selector().value).toBe('10');
    expect(screen.getByText('Estás trabajando en Casa central.')).toBeInTheDocument();
  });

  it('el selector queda deshabilitado mientras el PATCH está en curso (B3)', async () => {
    branchServer({ delayMs: 200 });
    renderApp('/', { session: sessionFor() });

    await screen.findByText('Inicio', { selector: 'h2' });
    await userEvent.selectOptions(selector(), '11');

    await waitFor(() => {
      expect(selector()).toBeDisabled();
    });
    expect(await screen.findByText('Tu sucursal actual es Sucursal Norte.')).toBeInTheDocument();
    expect(selector()).toBeEnabled();
  });

  it('un 422 muestra el mensaje de la API, vuelve a pedir me y deja la sucursal actual', async () => {
    let meCalls = 0;
    server.use(
      http.get('/api/me', () => {
        meCalls++;
        return success(ME_TWO);
      }),
      http.patch('/api/me/current-branch', () =>
        failure(422, 'VALIDATION_ERROR', NOT_AVAILABLE, { errors: { branch_id: [NOT_AVAILABLE] } }),
      ),
    );
    renderApp('/', { session: sessionFor() });

    await screen.findByText('Inicio', { selector: 'h2' });
    await userEvent.selectOptions(selector(), '11');

    expect(await screen.findByText(NOT_AVAILABLE)).toBeInTheDocument();
    await waitFor(() => {
      expect(meCalls).toBe(2);
    });
    expect(selector().value).toBe('10');
  });

  it('sin sucursal actual: el aviso para elegir una y el selector con "Elegí tu sucursal"', async () => {
    server.use(meHandler({ ...ME_TWO, current_branch: null }));
    renderApp('/', { session: sessionFor() });

    expect(
      await screen.findByText(
        'Elegí tu sucursal actual en la barra de arriba para empezar a trabajar.',
      ),
    ).toBeInTheDocument();
    expect(selector().value).toBe('');
    expect(selector().options[0]?.text).toBe('Elegí tu sucursal');
    expect(screen.getByText('Todavía no elegiste tu sucursal actual.')).toBeInTheDocument();
  });

  it('sin sucursales asignadas: el aviso de pedírselas al administrador', async () => {
    server.use(meHandler({ ...ME_TWO, branches: [], current_branch: null }));
    renderApp('/', { session: sessionFor() });

    expect(
      await screen.findByText(
        'No tenés sucursales asignadas: pedíselas al administrador de la clínica.',
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Sin sucursal actual', { selector: '.session-branch' }),
    ).toBeInTheDocument();
  });

  it('con sucursal actual no hay aviso', async () => {
    server.use(meHandler(ME_TWO));
    renderApp('/', { session: sessionFor() });

    await screen.findByText('Inicio', { selector: 'h2' });
    expect(screen.queryByText(/para empezar a trabajar/)).not.toBeInTheDocument();
  });
});
