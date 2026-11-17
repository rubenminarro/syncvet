import { fireEvent, screen, waitFor } from '@testing-library/react';
import { File as NodeFile } from 'node:buffer';
import { http, HttpResponse } from 'msw';
import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { failure, success } from '../../test/api';
import { meFor, type Role } from '../../test/roles';
import { server } from '../../test/server';
import { renderApp, sessionFor } from '../../test/session';
import { LOGO_MESSAGES } from './logo';
import type { SystemSettings } from './types';

/**
 * /configuracion/clinica (2.5): los datos de la clínica y sus claves, el logo (subir con vista previa, reemplazar y quitar) y
 * el logo en la barra para los tres roles, contra una API simulada con estado.
 */

const LOGO_URL = '/api/system-settings/logo';

/** El FormData y el File de Node (los de jsdom no se leen desde el fetch de Node), como en la foto del paciente. */
beforeAll(async () => {
  const form = await new Response('a=1', {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  }).formData();
  vi.stubGlobal('FormData', form.constructor);
});

afterAll(() => {
  vi.unstubAllGlobals();
});

/** jsdom no tiene URL.createObjectURL: cada URL lleva un número y se anota cuándo se libera. */
let revoked: string[] = [];

beforeEach(() => {
  let created = 0;
  revoked = [];
  Object.defineProperty(URL, 'createObjectURL', {
    configurable: true,
    value: () => `blob:syncvet/${String(++created)}`,
  });
  Object.defineProperty(URL, 'revokeObjectURL', {
    configurable: true,
    value: (url: string) => {
      revoked.push(url);
    },
  });
});

function settings(overrides: Partial<SystemSettings> = {}): SystemSettings {
  return {
    clinic: {
      name: 'Veterinaria Central',
      legal_name: 'Veterinaria Central S.A.',
      ruc: '99999901',
      ruc_dv: '0',
    },
    settings: {
      contact_email: 'contacto@example.com',
      contact_phone: null,
      website: null,
      document_footer: null,
    },
    has_logo: false,
    ...overrides,
  };
}

interface Backend {
  state: SystemSettings;
  meCalls: number;
  settingsCalls: number;
  logoCalls: string[];
  patches: unknown[];
  /** El cuerpo multipart de cada POST, como texto (el fetch de Node no deja leerlo con formData()). */
  posts: string[];
  deletes: number;
}

/**
 * La API con estado: `me` dice el nombre y `has_logo` de la configuración; subir o quitar el logo lo cambia; cada descarga
 * del logo devuelve el texto de la imagen de turno.
 */
function backend({
  role = 'administrador',
  initial = settings(),
  logos = ['logo-1', 'logo-2'],
}: {
  role?: Role;
  initial?: SystemSettings;
  logos?: string[];
} = {}): Backend {
  const data: Backend = {
    state: initial,
    meCalls: 0,
    settingsCalls: 0,
    logoCalls: [],
    patches: [],
    posts: [],
    deletes: 0,
  };
  let uploaded = 0;
  server.use(
    http.get('/api/me', () => {
      data.meCalls += 1;

      return success(
        meFor(role, {
          clinic: { id: 1, name: data.state.clinic.name, has_logo: data.state.has_logo },
        }),
      );
    }),
    http.get('/api/system-settings', () => {
      data.settingsCalls += 1;

      return success(data.state);
    }),
    http.patch('/api/system-settings', async ({ request }) => {
      const body = (await request.json()) as {
        name: string;
        legal_name: string;
        settings: SystemSettings['settings'];
      };
      data.patches.push(body);
      data.state = {
        ...data.state,
        clinic: { ...data.state.clinic, name: body.name, legal_name: body.legal_name },
        settings: body.settings,
      };

      return success(data.state, { message: 'Configuración actualizada correctamente.' });
    }),
    http.get(LOGO_URL, () => {
      if (!data.state.has_logo) {
        return failure(404, 'CLINIC_LOGO_NOT_FOUND', 'La clínica no tiene logo.');
      }

      const text = logos[Math.min(uploaded, logos.length - 1)] ?? 'logo';
      data.logoCalls.push(text);

      return new HttpResponse(new TextEncoder().encode(text), {
        headers: { 'Content-Type': 'image/png' },
      });
    }),
    http.post(LOGO_URL, async ({ request }) => {
      const contentType = request.headers.get('Content-Type') ?? '';
      data.posts.push(`${contentType} ${await request.text()}`);
      uploaded += 1;
      data.state = { ...data.state, has_logo: true };

      return success(data.state, { message: 'Logo de la clínica actualizado correctamente.' });
    }),
    http.delete(LOGO_URL, () => {
      data.deletes += 1;
      data.state = { ...data.state, has_logo: false };

      return success(data.state, { message: 'Logo de la clínica eliminado correctamente.' });
    }),
  );

  return data;
}

// Por etiqueta o por texto con selector, no por rol: con los íconos SVG del layout, *ByRole tarda segundos en jsdom.
function field(label: string): HTMLInputElement | HTMLTextAreaElement {
  const found = Array.from(document.querySelectorAll('label')).find(
    (element) => element.textContent.replace('*', '').trim() === label,
  );
  const input = found?.htmlFor ? document.getElementById(found.htmlFor) : null;

  if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
    throw new Error(`Sin el campo ${label}`);
  }

  return input;
}

function type(label: string, value: string) {
  fireEvent.change(field(label), { target: { value } });
}

const fieldError = (label: string) =>
  document.getElementById(`${field(label).id}-error`)?.textContent ?? null;

const save = () => {
  fireEvent.click(screen.getByText('Guardar', { selector: 'button' }));
};

async function openPage(data?: Parameters<typeof backend>[0]) {
  const api = backend(data);
  const rendered = renderApp('/configuracion/clinica', { session: sessionFor() });
  await screen.findByText('Datos de la clínica', { selector: 'h2' });

  return { api, ...rendered };
}

/** El logo de la barra (Brand), o null con la marca SyncVet. */
const barLogo = () =>
  document.querySelector<HTMLImageElement>('header .navbar-brand img.brand-logo');
const cardLogo = () => document.querySelector<HTMLImageElement>('.clinic-logo-current');
const syncVetMark = () => screen.queryByText('SyncVet', { selector: 'header .fw-bold' });

const PNG_SIGNATURE = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];

function png(size = 10, name = 'logo.png'): File {
  const bytes = new Uint8Array(Math.max(size, PNG_SIGNATURE.length));
  bytes.set(PNG_SIGNATURE);

  return new NodeFile([bytes], name, { type: 'image/png' }) as unknown as File;
}

function choose(file: File) {
  const input = document.querySelector<HTMLInputElement>('.clinic-logo-form input[type="file"]');

  if (input === null) {
    throw new Error('Sin el campo del logo');
  }

  fireEvent.change(input, { target: { files: [file] } });
}

async function uploadChosen() {
  fireEvent.click(await screen.findByText('Subir el logo', { selector: 'button' }));
}

describe('/configuracion/clinica: los datos (2.5)', () => {
  it('/configuracion tiene la tarjeta "Clínica" que lleva a la pantalla', async () => {
    backend();
    const { history } = renderApp('/configuracion', { session: sessionFor() });

    fireEvent.click(
      await screen.findByText('Clínica', { selector: '.configuration-sections span' }),
    );

    expect(await screen.findByText('Datos de la clínica', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/configuracion/clinica');
  });

  it.each<Role>(['recepcionista', 'veterinario'])(
    'el %s va a /sin-permiso (la pantalla es del administrador)',
    async (role) => {
      const api = backend({ role });
      const { history } = renderApp('/configuracion/clinica', { session: sessionFor() });

      expect(await screen.findByText('No tenés permiso para ver esta página')).toBeInTheDocument();
      expect(history.location.pathname).toBe('/sin-permiso');
      expect(api.settingsCalls).toBe(0);
    },
  );

  it('muestra lo guardado; el RUC solo para leer, fuera del formulario', async () => {
    await openPage();

    expect(field('Nombre').value).toBe('Veterinaria Central');
    expect(field('Correo de contacto').value).toBe('contacto@example.com');
    expect(field('Sitio web').value).toBe('');
    expect(field('RUC').value).toBe('99999901-0');
    expect(field('RUC')).toHaveAttribute('readonly');
    expect(field('RUC')).not.toHaveAttribute('name');
    expect(screen.getByText('El RUC no se cambia desde acá.')).toBeInTheDocument();
  });

  it('guarda el formulario completo: sin ruc, las claves vacías en null, el correo en minúsculas; vuelve a pedir me', async () => {
    const { api } = await openPage();
    const meBefore = api.meCalls;

    type('Nombre', '  Veterinaria Norte ');
    type('Correo de contacto', ' CONTACTO@Example.COM ');
    type('Teléfono de contacto', '+595 21 110-0001');
    type('Sitio web', 'https://example.com');
    save();

    expect(await screen.findByText('Configuración actualizada correctamente.')).toBeInTheDocument();
    expect(api.patches).toEqual([
      {
        name: 'Veterinaria Norte',
        legal_name: 'Veterinaria Central S.A.',
        settings: {
          contact_email: 'contacto@example.com',
          contact_phone: '+595211100001',
          website: 'https://example.com',
          document_footer: null,
        },
      },
    ]);
    // La barra toma el nombre nuevo: me se volvió a pedir (H5).
    await waitFor(() => {
      expect(
        screen.getByText('Veterinaria Norte', { selector: '.session-clinic' }),
      ).toBeInTheDocument();
    });
    expect(api.meCalls).toBeGreaterThan(meBefore);
    expect(field('Nombre').value).toBe('Veterinaria Norte');
  });

  it('borrar una clave manda null', async () => {
    const { api } = await openPage();

    type('Correo de contacto', '');
    save();

    await screen.findByText('Configuración actualizada correctamente.');
    expect(api.patches).toHaveLength(1);
    expect(
      (api.patches[0] as { settings: Record<string, unknown> }).settings.contact_email,
    ).toBeNull();
  });

  it('el sitio con http:// se rechaza sin pedido, con el mensaje de la API', async () => {
    const { api } = await openPage();

    type('Sitio web', 'http://example.com');
    save();

    await waitFor(() => {
      expect(fieldError('Sitio web')).toBe(
        'El sitio web debe ser una dirección que empiece con https://.',
      );
    });
    expect(api.patches).toHaveLength(0);
  });

  it('un 422 de settings.website va a su campo; una clave que no es un campo, a la alerta', async () => {
    const { api } = await openPage();
    server.use(
      http.patch('/api/system-settings', () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', {
          errors: {
            'settings.website': ['El sitio web debe ser una dirección que empiece con https://.'],
            settings: ['La configuración debe ser una lista de claves y valores.'],
          },
        }),
      ),
    );

    type('Sitio web', 'https://exa_mple');
    save();

    await waitFor(() => {
      expect(fieldError('Sitio web')).toBe(
        'El sitio web debe ser una dirección que empiece con https://.',
      );
    });
    expect(field('Sitio web')).toHaveFocus();
    expect(
      screen.getByText('La configuración debe ser una lista de claves y valores.'),
    ).toBeInTheDocument();
    expect(api.patches).toHaveLength(0);
  });

  it('un doble clic en Guardar manda un solo pedido', async () => {
    const { api } = await openPage();

    type('Razón social', 'Otra razón social');
    save();
    save();

    await screen.findByText('Configuración actualizada correctamente.');
    expect(api.patches).toHaveLength(1);
  });
});

describe('/configuracion/clinica: el logo (2.5)', () => {
  it('sin logo: el lugar vacío, la marca SyncVet en la barra y ninguna descarga', async () => {
    const { api } = await openPage();

    expect(document.querySelector('.clinic-logo-empty')).toHaveTextContent('Sin logo');
    expect(syncVetMark()).toBeInTheDocument();
    expect(barLogo()).toBeNull();
    expect(api.logoCalls).toHaveLength(0);
  });

  it('subir: la vista previa, POST con el campo logo, y el logo en la tarjeta y en la barra sin recargar', async () => {
    const { api } = await openPage();

    choose(png());
    expect(await screen.findByAltText('Vista previa del logo nuevo')).toBeInTheDocument();
    await uploadChosen();

    expect(
      await screen.findByText('Logo de la clínica actualizado correctamente.'),
    ).toBeInTheDocument();
    expect(api.posts).toHaveLength(1);
    expect(api.posts[0]).toMatch(/^multipart\/form-data; boundary=/);
    expect(api.posts[0]).toContain('name="logo"; filename="logo.png"');
    await waitFor(() => {
      expect(barLogo()).not.toBeNull();
    });
    expect(barLogo()).toHaveAttribute('alt', 'Veterinaria Central');
    expect(cardLogo()).toHaveAttribute('alt', 'Logo de Veterinaria Central');
    expect(syncVetMark()).toBeNull();
    // Una sola descarga para la barra y la tarjeta: la misma clave.
    expect(api.logoCalls).toHaveLength(1);
  });

  it('un logo de un byte más de 2 MB no se manda', async () => {
    const { api } = await openPage();

    // El límite escrito (2 MB), no la constante: un límite equivocado en logo.ts tiene que fallar acá.
    choose(png(2 * 1024 * 1024 + 1));

    expect(await screen.findByText(LOGO_MESSAGES.tooLarge)).toBeInTheDocument();
    expect(screen.queryByText('Subir el logo', { selector: 'button' })).toBeNull();
    expect(api.posts).toHaveLength(0);
  });

  it('un logo de 2 MB justos se puede subir', async () => {
    await openPage();

    choose(png(2 * 1024 * 1024));

    expect(await screen.findByText('Subir el logo', { selector: 'button' })).toBeInTheDocument();
    expect(screen.queryByText(LOGO_MESSAGES.tooLarge)).toBeNull();
  });

  it('un archivo que no es una imagen (por el contenido) no se manda', async () => {
    const { api } = await openPage();

    choose(new NodeFile(['hola'], 'logo.png', { type: 'image/png' }) as unknown as File);

    expect(await screen.findByText(LOGO_MESSAGES.notAnImage)).toBeInTheDocument();
    expect(api.posts).toHaveLength(0);
  });

  it('413 con errors.logo: el mensaje en el campo, con el foco', async () => {
    await openPage();
    server.use(
      http.post(LOGO_URL, () =>
        failure(413, 'PAYLOAD_TOO_LARGE', 'El logo no debe pesar más de 2 MB.', {
          errors: { logo: ['El logo no debe pesar más de 2 MB.'] },
        }),
      ),
    );

    choose(png());
    await uploadChosen();

    const input = document.querySelector<HTMLInputElement>('.clinic-logo-form input[type="file"]');
    await waitFor(() => {
      expect(input).toHaveAttribute('aria-invalid', 'true');
    });
    expect(
      screen.getByText('El logo no debe pesar más de 2 MB.', { selector: '.invalid-feedback' }),
    ).toBeInTheDocument();
    await waitFor(() => {
      expect(input).toHaveFocus();
    });
  });

  it('413 sin errors (lo cortó el servidor): una alerta con el mensaje', async () => {
    await openPage();
    server.use(
      http.post(LOGO_URL, () =>
        failure(413, 'PAYLOAD_TOO_LARGE', 'El archivo es demasiado grande.'),
      ),
    );

    choose(png());
    await uploadChosen();

    expect(await screen.findByText('El archivo es demasiado grande.')).toBeInTheDocument();
  });

  it('422 de la API (demasiados megapíxeles) va al campo', async () => {
    await openPage();
    server.use(
      http.post(LOGO_URL, () =>
        failure(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', {
          errors: { logo: ['El logo no debe superar los 25 megapíxeles.'] },
        }),
      ),
    );

    choose(png());
    await uploadChosen();

    expect(
      await screen.findByText('El logo no debe superar los 25 megapíxeles.', {
        selector: '.invalid-feedback',
      }),
    ).toBeInTheDocument();
  });

  it('reemplazar: el logo guardado se vacía y se descarga el nuevo (nunca se ve el viejo)', async () => {
    const { api } = await openPage({ initial: settings({ has_logo: true }) });
    await waitFor(() => {
      expect(cardLogo()).not.toBeNull();
    });
    expect(api.logoCalls).toEqual(['logo-1']);
    const oldUrl = cardLogo()?.getAttribute('src');

    // La descarga del logo nuevo queda retenida hasta soltarla: mientras tanto, ni la tarjeta ni la barra muestran el viejo
    // (revisión de la 2.5: con invalidateQueries en lugar de resetQueries, el viejo se seguía viendo y ningún test fallaba).
    let releaseDownload: () => void = () => undefined;
    const held = new Promise<void>((resolve) => {
      releaseDownload = resolve;
    });
    server.use(
      http.get(LOGO_URL, async () => {
        await held;
        api.logoCalls.push('logo-2');

        return new HttpResponse(new TextEncoder().encode('logo-2'), {
          headers: { 'Content-Type': 'image/png' },
        });
      }),
    );

    choose(png());
    await uploadChosen();

    await screen.findByText('Logo de la clínica actualizado correctamente.');
    await waitFor(() => {
      expect(cardLogo()).toBeNull();
    });
    expect(barLogo()).toBeNull();
    expect(revoked).toContain(oldUrl);

    releaseDownload();

    await waitFor(() => {
      expect(api.logoCalls).toEqual(['logo-1', 'logo-2']);
    });
    await waitFor(() => {
      expect(barLogo()).not.toBeNull();
    });
    expect(cardLogo()).not.toBeNull();
    expect(cardLogo()?.getAttribute('src')).not.toBe(oldUrl);
  });

  it('quitar: con confirmación, la barra vuelve a la marca SyncVet sin volver a pedir el logo', async () => {
    const { api } = await openPage({ initial: settings({ has_logo: true }) });
    await waitFor(() => {
      expect(barLogo()).not.toBeNull();
    });

    const meBefore = api.meCalls;

    fireEvent.click(screen.getByText('Quitar el logo', { selector: '.clinic-logo-delete' }));
    expect(await screen.findByText(/¿Querés quitar el logo de/)).toBeInTheDocument();
    fireEvent.click(
      screen.getByText('Quitar el logo', { selector: '[role="dialog"] .btn-danger' }),
    );

    expect(
      await screen.findByText('Logo de la clínica eliminado correctamente.'),
    ).toBeInTheDocument();
    await waitFor(() => {
      expect(barLogo()).toBeNull();
    });
    expect(syncVetMark()).toBeInTheDocument();
    expect(document.querySelector('.clinic-logo-empty')).toHaveTextContent('Sin logo');
    expect(api.deletes).toBe(1);
    expect(api.logoCalls).toEqual(['logo-1']);
    // me se volvió a pedir (clinic.has_logo en false): la barra no vuelve a buscar un logo que no está.
    await waitFor(() => {
      expect(api.meCalls).toBeGreaterThan(meBefore);
    });
    // Las dos URL de objeto (la barra y la tarjeta) se liberaron.
    expect(revoked).toHaveLength(2);
  });

  it('quitar con 404 CLINIC_LOGO_NOT_FOUND (ya lo quitó otra persona): aviso y la configuración pedida de nuevo', async () => {
    const { api } = await openPage({ initial: settings({ has_logo: true }) });
    await waitFor(() => {
      expect(cardLogo()).not.toBeNull();
    });
    server.use(
      http.delete(LOGO_URL, () => {
        api.state = { ...api.state, has_logo: false };

        return failure(404, 'CLINIC_LOGO_NOT_FOUND', 'La clínica no tiene logo.');
      }),
    );
    const settingsBefore = api.settingsCalls;

    fireEvent.click(screen.getByText('Quitar el logo', { selector: '.clinic-logo-delete' }));
    await screen.findByText(/¿Querés quitar el logo de/);
    fireEvent.click(
      screen.getByText('Quitar el logo', { selector: '[role="dialog"] .btn-danger' }),
    );

    expect(await screen.findByText('La clínica no tiene logo.')).toBeInTheDocument();
    await waitFor(() => {
      expect(api.settingsCalls).toBeGreaterThan(settingsBefore);
    });
    await waitFor(() => {
      expect(barLogo()).toBeNull();
    });
    expect(document.querySelector('.clinic-logo-empty')).toHaveTextContent('Sin logo');
  });

  it('con has_logo y el 404 al verlo: el lugar vacío y la marca SyncVet, sin error', async () => {
    backend({ initial: settings({ has_logo: true }) });
    server.use(
      http.get(LOGO_URL, () => failure(404, 'CLINIC_LOGO_NOT_FOUND', 'La clínica no tiene logo.')),
    );
    renderApp('/configuracion/clinica', { session: sessionFor() });
    await screen.findByText('Datos de la clínica', { selector: 'h2' });

    await waitFor(() => {
      expect(document.querySelector('.clinic-logo-empty')).toHaveTextContent('Sin logo');
    });
    expect(syncVetMark()).toBeInTheDocument();
    expect(barLogo()).toBeNull();
    expect(screen.queryByText('La clínica no tiene logo.')).toBeNull();
  });
});

describe('el logo en la barra (2.5)', () => {
  it.each<Role>(['administrador', 'recepcionista', 'veterinario'])(
    'el %s ve el logo de la clínica con su nombre en alt',
    async (role) => {
      const api = backend({ role, initial: settings({ has_logo: true }) });
      renderApp('/', { session: sessionFor() });
      await screen.findByText('Inicio', { selector: 'h2' });

      await waitFor(() => {
        expect(barLogo()).toHaveAttribute('alt', 'Veterinaria Central');
      });
      expect(syncVetMark()).toBeNull();
      expect(api.logoCalls).toHaveLength(1);
    },
  );

  it('con un error del servidor al descargarlo, la marca SyncVet (nunca una imagen rota)', async () => {
    backend({ initial: settings({ has_logo: true }) });
    let calls = 0;
    server.use(
      http.get(LOGO_URL, () => {
        calls += 1;

        return failure(500, 'INTERNAL_SERVER_ERROR', 'Error interno.');
      }),
    );
    renderApp('/', { session: sessionFor() });
    await screen.findByText('Inicio', { selector: 'h2' });

    // Una lectura con un 5xx se reintenta una vez; después, la marca.
    await waitFor(() => {
      expect(calls).toBe(2);
    });
    expect(barLogo()).toBeNull();
    expect(syncVetMark()).toBeInTheDocument();
  });
});
