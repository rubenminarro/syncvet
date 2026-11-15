import { cleanup, fireEvent, screen, waitFor } from '@testing-library/react';
import { File as NodeFile } from 'node:buffer';
import { http, HttpResponse } from 'msw';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { failure, success } from '../../test/api';
import { patientDetail } from '../../test/patients';
import { meFor, ROLE_PERMISSIONS, ROLES, type Role } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import { PHOTO_MAX_BYTES } from './photo';
import type { PatientDetail } from './types';

/**
 * La foto del paciente en su detalle (1.4): se descarga como Blob y se muestra con una URL de objeto que se libera; subirla
 * con vista previa, reemplazarla y quitarla, con los errores de la API y el control del navegador.
 */

const PHOTO_URL = '/api/patients/1/photo';

/**
 * El FormData y el File de jsdom no se pueden leer desde el fetch de Node (el pedido se corta): el test usa los de Node, como
 * los del navegador. El FormData de Node sale de una respuesta (no es un global en el entorno jsdom).
 */
beforeAll(async () => {
  const form = await new Response('a=1', {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  }).formData();
  vi.stubGlobal('FormData', form.constructor);
});

function nodeFile(parts: (string | Uint8Array<ArrayBuffer>)[], name: string, type: string): File {
  return new NodeFile(parts, name, { type }) as unknown as File;
}

/** jsdom no tiene URL.createObjectURL: cada URL creada lleva un número y se anota cuándo se libera. */
let created: { url: string; blob: Blob }[] = [];
let revoked: string[] = [];

beforeEach(() => {
  created = [];
  revoked = [];
  Object.defineProperty(URL, 'createObjectURL', {
    configurable: true,
    value: (blob: Blob) => {
      const url = `blob:syncvet/${String(created.length + 1)}`;
      created.push({ url, blob });

      return url;
    },
  });
  Object.defineProperty(URL, 'revokeObjectURL', {
    configurable: true,
    value: (url: string) => {
      revoked.push(url);
    },
  });
});

afterEach(() => {
  vi.useRealTimers();
});

afterAll(() => {
  vi.unstubAllGlobals();
});

function imageResponse(text: string) {
  return new HttpResponse(new TextEncoder().encode(text), {
    headers: { 'Content-Type': 'image/png' },
  });
}

/** Un handler de GET photo que cuenta los pedidos y responde con el texto de la foto de turno. */
function photoHandler(photos: string[]) {
  const calls: string[] = [];
  server.use(
    http.get(PHOTO_URL, () => {
      const text = photos[calls.length] ?? photos.at(-1) ?? 'foto';
      calls.push(text);

      return imageResponse(text);
    }),
  );

  return calls;
}

async function openDetail(
  data: PatientDetail,
  { role = 'recepcionista', permissions }: { role?: Role; permissions?: string[] } = {},
) {
  server.use(
    meHandler(meFor(role, permissions === undefined ? {} : { permissions })),
    http.get('/api/patients/:id', () => success(data)),
  );
  const rendered = renderApp('/pacientes/1', { session: sessionFor() });
  await screen.findByText(data.name, { selector: 'h2', exact: false });

  return rendered;
}

function image(): HTMLImageElement | null {
  return document.querySelector<HTMLImageElement>('.patient-photo-image');
}

function fileInput(): HTMLInputElement {
  const input = document.querySelector<HTMLInputElement>('.patient-photo-form input[type="file"]');

  if (input === null) {
    throw new Error('No está el campo de la foto.');
  }

  return input;
}

function choose(file: File) {
  fireEvent.change(fileInput(), { target: { files: [file] } });
}

const PNG_SIGNATURE = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];

function png(name = 'nueva.png', size = 10): File {
  // Con la firma de un PNG: el navegador mira los primeros bytes, como la API (revisión de la 1.4).
  const bytes = new Uint8Array(Math.max(size, PNG_SIGNATURE.length));
  bytes.set(PNG_SIGNATURE);

  return nodeFile([bytes], name, 'image/png');
}

async function blobText(blob: Blob | undefined): Promise<string> {
  return blob === undefined ? '' : await blob.text();
}

describe('/pacientes/$id: la foto (1.4)', () => {
  it('con foto: se descarga con el token y se muestra con una URL de objeto, con el nombre en alt', async () => {
    let authorization: string | null = null;
    server.use(
      http.get(PHOTO_URL, ({ request }) => {
        authorization = request.headers.get('Authorization');

        return imageResponse('foto-1');
      }),
    );
    await openDetail(patientDetail({ has_photo: true }));

    await waitFor(() => {
      expect(image()).not.toBeNull();
    });
    expect(image()).toHaveAttribute('src', 'blob:syncvet/1');
    expect(image()).toHaveAttribute('alt', 'Foto de Firulais');
    expect(await blobText(created[0]?.blob)).toBe('foto-1');
    expect(authorization).toMatch(/^Bearer /);
  });

  it('la URL de objeto se libera al salir de la página', async () => {
    photoHandler(['foto-1']);
    await openDetail(patientDetail({ has_photo: true }));
    await waitFor(() => {
      expect(image()).not.toBeNull();
    });
    expect(revoked).toEqual([]);

    cleanup();

    expect(revoked).toEqual(['blob:syncvet/1']);
  });

  it('sin foto: el lugar vacío, sin pedir la foto', async () => {
    const calls = photoHandler(['foto-1']);
    await openDetail(patientDetail({ has_photo: false }));

    expect(document.querySelector('.patient-photo-empty')).toHaveTextContent('Sin foto');
    expect(calls).toHaveLength(0);
    expect(screen.getByText('Elegir una foto', { selector: 'label' })).toBeInTheDocument();
    expect(document.querySelector('.patient-photo-delete')).toBeNull();
  });

  it('con 404 PATIENT_PHOTO_NOT_FOUND al verla: el lugar vacío, sin error', async () => {
    server.use(
      http.get(PHOTO_URL, () =>
        failure(404, 'PATIENT_PHOTO_NOT_FOUND', 'El paciente no tiene foto.'),
      ),
    );
    await openDetail(patientDetail({ has_photo: true }));

    await waitFor(() => {
      expect(document.querySelector('.patient-photo-empty')).toHaveTextContent('Sin foto');
    });
    expect(screen.queryByText('El paciente no tiene foto.')).not.toBeInTheDocument();
    expect(screen.queryByText('No encontramos este paciente')).not.toBeInTheDocument();
    // El detalle pasa a sin foto (revisión de la 1.4): ni "Cambiar la foto" ni "Quitar la foto" sobre una que no está.
    expect(await screen.findByText('Elegir una foto', { selector: 'label' })).toBeInTheDocument();
    expect(document.querySelector('.patient-photo-delete')).toBeNull();
  });

  it('un 200 que no es una imagen (el index.html de un servidor mal configurado): el error, no una foto rota', async () => {
    server.use(
      http.get(
        PHOTO_URL,
        () =>
          new HttpResponse('<!doctype html><title>SyncVet</title>', {
            headers: { 'Content-Type': 'text/html' },
          }),
      ),
    );
    await openDetail(patientDetail({ has_photo: true }));

    expect(
      await screen.findByText(
        'No se pudo completar el pedido. Si se repite, avisá al administrador.',
        {
          selector: '.patient-photo-error',
        },
      ),
    ).toBeInTheDocument();
    expect(image()).toBeNull();
    expect(created).toHaveLength(0);
  });

  it('otro paciente es otra pantalla (key, revisión de la 1.4): el archivo elegido no pasa al siguiente', async () => {
    let posts = 0;
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/patients/:id', ({ params }) =>
        success(
          patientDetail({
            id: Number(params.id),
            name: params.id === '1' ? 'Firulais' : 'Michi',
          }),
        ),
      ),
      http.post('/api/patients/:id/photo', () => {
        posts += 1;

        return success(patientDetail({ has_photo: true }));
      }),
    );
    const { history } = renderApp('/pacientes/1', { session: sessionFor() });
    await screen.findByText('Firulais', { selector: 'h2', exact: false });
    choose(png('de-firulais.png'));
    await screen.findByAltText('Vista previa de la foto nueva');

    history.push('/pacientes/2');

    await screen.findByText('Michi', { selector: 'h2', exact: false });
    expect(screen.queryByAltText('Vista previa de la foto nueva')).not.toBeInTheDocument();
    expect(screen.queryByText('Subir la foto', { selector: 'button' })).not.toBeInTheDocument();
    expect(revoked).toEqual(['blob:syncvet/1']);
    expect(posts).toBe(0);
  });

  it('un error del servidor al verla: el mensaje en el lugar de la foto, el detalle sigue', async () => {
    server.use(http.get(PHOTO_URL, () => failure(500, 'INTERNAL_SERVER_ERROR', 'Error interno.')));
    await openDetail(patientDetail({ has_photo: true }));

    expect(
      await screen.findByText('No se pudo completar. Probá de nuevo en unos minutos.', {
        selector: '.patient-photo-error',
      }),
    ).toBeInTheDocument();
  });

  it('subir: la vista previa del archivo elegido, "Subir la foto" manda el multipart y se muestra la foto de la API', async () => {
    const calls = photoHandler(['de-la-api']);
    let contentType: string | null = null;
    let body = '';
    server.use(
      http.post(PHOTO_URL, async ({ request }) => {
        contentType = request.headers.get('Content-Type');
        body = await request.text();

        return success(patientDetail({ has_photo: true }), {
          message: 'Foto del paciente guardada correctamente.',
        });
      }),
    );
    const { queryClient } = await openDetail(patientDetail({ has_photo: false }));

    choose(png('nueva.png'));

    const preview = await screen.findByAltText('Vista previa de la foto nueva');
    expect(preview).toHaveAttribute('src', 'blob:syncvet/1');
    expect(calls).toHaveLength(0);

    fireEvent.click(screen.getByText('Subir la foto', { selector: 'button' }));

    expect(
      await screen.findByText('Foto del paciente guardada correctamente.'),
    ).toBeInTheDocument();
    await waitFor(() => {
      expect(image()).toHaveAttribute('src', 'blob:syncvet/2');
    });
    expect(contentType).toMatch(/^multipart\/form-data; boundary=/);
    expect(body).toContain('Content-Disposition: form-data; name="photo"; filename="nueva.png"');
    expect(body).toContain('Content-Type: image/png');
    expect(calls).toEqual(['de-la-api']);
    // La vista previa se liberó; la foto se ve como la devolvió la API (vuelta a codificar), no el archivo local.
    expect(revoked).toContain('blob:syncvet/1');
    expect(await blobText(created[1]?.blob)).toBe('de-la-api');
    expect(fileInput().value).toBe('');
    // El archivo no queda en la caché de mutaciones (revisión de la 1.4): gcTime 0 y reset() al terminar.
    expect(queryClient.getMutationCache().getAll()).toHaveLength(0);
    expect(screen.getByText('Cambiar la foto', { selector: 'label' })).toBeInTheDocument();
  });

  it('reemplazar: mientras llega la foto nueva no se ve la vieja, y la vieja se libera', async () => {
    let releaseSecond: () => void = () => undefined;
    const second = new Promise<void>((resolve) => {
      releaseSecond = resolve;
    });
    let photoCalls = 0;
    server.use(
      http.get(PHOTO_URL, async () => {
        photoCalls += 1;

        if (photoCalls === 2) {
          await second;

          return imageResponse('nueva');
        }

        return imageResponse('vieja');
      }),
      http.post(PHOTO_URL, () => success(patientDetail({ has_photo: true }))),
    );
    await openDetail(patientDetail({ has_photo: true }));
    await waitFor(() => {
      expect(image()).toHaveAttribute('src', 'blob:syncvet/1');
    });

    choose(png());
    await screen.findByAltText('Vista previa de la foto nueva');
    fireEvent.click(screen.getByText('Subir la foto', { selector: 'button' }));

    expect(await screen.findByText('Cargando la foto…')).toBeInTheDocument();
    expect(image()).toBeNull();
    expect(revoked).toContain('blob:syncvet/1');

    releaseSecond();

    await waitFor(() => {
      expect(image()).toHaveAttribute('src', 'blob:syncvet/3');
    });
    expect(await blobText(created[2]?.blob)).toBe('nueva');
    expect(photoCalls).toBe(2);
  });

  it('"Cancelar" descarta el archivo elegido y libera la vista previa, sin pedido', async () => {
    let posts = 0;
    server.use(
      http.post(PHOTO_URL, () => {
        posts += 1;

        return success(patientDetail({ has_photo: true }));
      }),
    );
    await openDetail(patientDetail());
    choose(png());
    await screen.findByAltText('Vista previa de la foto nueva');

    fireEvent.click(screen.getByText('Cancelar', { selector: 'button' }));

    expect(screen.queryByAltText('Vista previa de la foto nueva')).not.toBeInTheDocument();
    expect(revoked).toEqual(['blob:syncvet/1']);
    expect(fileInput().value).toBe('');
    expect(posts).toBe(0);
  });

  it.each([
    ['más de 5 MB', png('grande.png', PHOTO_MAX_BYTES + 1), 'La foto no debe pesar más de 5 MB.'],
    [
      'un tipo que no es JPG, PNG ni WEBP',
      nodeFile(['hola'], 'notas.txt', 'text/plain'),
      'La foto debe ser una imagen JPG, PNG o WEBP.',
    ],
  ])(
    '%s: el mensaje de la API en el campo, sin vista previa ni pedido',
    async (_, file, message) => {
      let posts = 0;
      server.use(
        http.post(PHOTO_URL, () => {
          posts += 1;

          return success(patientDetail({ has_photo: true }));
        }),
      );
      await openDetail(patientDetail());

      choose(file);

      expect(
        await screen.findByText(message, { selector: '.invalid-feedback' }),
      ).toBeInTheDocument();
      expect(fileInput()).toHaveAttribute('aria-invalid', 'true');
      expect(screen.queryByAltText('Vista previa de la foto nueva')).not.toBeInTheDocument();
      expect(screen.queryByText('Subir la foto', { selector: 'button' })).not.toBeInTheDocument();
      expect(created).toHaveLength(0);
      expect(posts).toBe(0);
    },
  );

  it('un texto renombrado a .png lo frena el navegador por el contenido, sin pedido', async () => {
    let posts = 0;
    server.use(
      http.post(PHOTO_URL, () => {
        posts += 1;

        return success(patientDetail({ has_photo: true }));
      }),
    );
    await openDetail(patientDetail());

    choose(nodeFile(['Esto es un texto.'], 'texto.png', 'image/png'));

    expect(
      await screen.findByText('La foto debe ser una imagen JPG, PNG o WEBP.', {
        selector: '.invalid-feedback',
      }),
    ).toBeInTheDocument();
    expect(screen.queryByAltText('Vista previa de la foto nueva')).not.toBeInTheDocument();
    expect(posts).toBe(0);
  });

  it('un JPG con la extensión .gif pasa el navegador (la API mira el contenido y lo acepta)', async () => {
    await openDetail(patientDetail());

    choose(nodeFile([new Uint8Array([0xff, 0xd8, 0xff, 0xe0, 0, 0])], 'foto.gif', 'image/gif'));

    expect(await screen.findByAltText('Vista previa de la foto nueva')).toBeInTheDocument();
    expect(document.querySelector('.patient-photo-form .invalid-feedback')).toBeNull();
  });

  it('5 MB justos pasan el control del navegador', async () => {
    await openDetail(patientDetail());

    choose(png('justa.png', PHOTO_MAX_BYTES));

    expect(await screen.findByAltText('Vista previa de la foto nueva')).toBeInTheDocument();
  });

  it.each([
    [422, 'VALIDATION_ERROR', 'La foto debe ser una imagen JPG, PNG o WEBP.'],
    [422, 'VALIDATION_ERROR', 'La foto no debe superar los 25 megapíxeles.'],
    [413, 'PAYLOAD_TOO_LARGE', 'La foto no debe pesar más de 5 MB.'],
  ])(
    'un %i %s con errors.photo: el mensaje en el campo, con el foco',
    async (status, code, message) => {
      server.use(
        http.post(PHOTO_URL, () =>
          failure(status, code, message, { errors: { photo: [message] } }),
        ),
      );
      await openDetail(patientDetail());
      choose(png('falsa.png'));
      await screen.findByAltText('Vista previa de la foto nueva');

      fireEvent.click(screen.getByText('Subir la foto', { selector: 'button' }));

      expect(
        await screen.findByText(message, { selector: '.invalid-feedback' }),
      ).toBeInTheDocument();
      expect(document.activeElement).toBe(fileInput());
      expect(screen.queryByAltText('Vista previa de la foto nueva')).not.toBeInTheDocument();
    },
  );

  it('un 413 sin errors (el límite de PHP): el mensaje en una alerta', async () => {
    server.use(
      http.post(PHOTO_URL, () =>
        failure(413, 'PAYLOAD_TOO_LARGE', 'El contenido enviado es demasiado grande.'),
      ),
    );
    await openDetail(patientDetail());
    choose(png());
    await screen.findByAltText('Vista previa de la foto nueva');

    fireEvent.click(screen.getByText('Subir la foto', { selector: 'button' }));

    expect(
      await screen.findByText('El contenido enviado es demasiado grande.', {
        selector: '.alert *',
      }),
    ).toBeInTheDocument();
  });

  it('un doble clic o dos envíos del formulario mandan un solo pedido', async () => {
    let posts = 0;
    let release: () => void = () => undefined;
    const answer = new Promise<void>((resolve) => {
      release = resolve;
    });
    photoHandler(['de-la-api']);
    server.use(
      http.post(PHOTO_URL, async () => {
        posts += 1;
        await answer;

        return success(patientDetail({ has_photo: true }));
      }),
    );
    await openDetail(patientDetail());
    choose(png());
    await screen.findByAltText('Vista previa de la foto nueva');
    const button = screen.getByText('Subir la foto', { selector: 'button' });
    const form = document.querySelector('.patient-photo-form');

    fireEvent.click(button);
    fireEvent.click(button);
    if (form !== null) {
      fireEvent.submit(form);
    }

    await waitFor(() => {
      expect(posts).toBe(1);
    });
    release();
    await waitFor(() => {
      expect(image()).not.toBeNull();
    });
    expect(posts).toBe(1);
  });

  it('quitar: con confirmación, DELETE y el lugar vacío, sin volver a pedir la foto', async () => {
    const calls = photoHandler(['foto-1']);
    let deletes = 0;
    server.use(
      http.delete(PHOTO_URL, () => {
        deletes += 1;

        return success(patientDetail({ has_photo: false }), {
          message: 'Foto del paciente eliminada correctamente.',
        });
      }),
    );
    await openDetail(patientDetail({ has_photo: true }));
    await waitFor(() => {
      expect(image()).not.toBeNull();
    });

    fireEvent.click(screen.getByText('Quitar la foto', { selector: '.patient-photo-delete' }));
    const dialog = await screen.findByRole('dialog');
    expect(dialog).toHaveTextContent('¿Querés quitar la foto de Firulais?');
    fireEvent.click(
      screen.getByText('Quitar la foto', { selector: '[role="dialog"] .btn-danger' }),
    );

    expect(
      await screen.findByText('Foto del paciente eliminada correctamente.'),
    ).toBeInTheDocument();
    expect(document.querySelector('.patient-photo-empty')).toHaveTextContent('Sin foto');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(revoked).toEqual(['blob:syncvet/1']);
    expect(deletes).toBe(1);
    expect(calls).toHaveLength(1);
  });

  it('quitar con 404 PATIENT_PHOTO_NOT_FOUND (ya la quitó otra persona): aviso y el paciente pedido de nuevo', async () => {
    photoHandler(['foto-1']);
    let detailCalls = 0;
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/patients/:id', () => {
        detailCalls += 1;

        return success(patientDetail({ has_photo: detailCalls === 1 }));
      }),
      http.delete(PHOTO_URL, () =>
        failure(404, 'PATIENT_PHOTO_NOT_FOUND', 'El paciente no tiene foto.'),
      ),
    );
    renderApp('/pacientes/1', { session: sessionFor() });
    await waitFor(() => {
      expect(image()).not.toBeNull();
    });

    fireEvent.click(screen.getByText('Quitar la foto', { selector: '.patient-photo-delete' }));
    await screen.findByRole('dialog');
    fireEvent.click(
      screen.getByText('Quitar la foto', { selector: '[role="dialog"] .btn-danger' }),
    );

    expect(await screen.findByText('El paciente no tiene foto.')).toBeInTheDocument();
    await waitFor(() => {
      expect(document.querySelector('.patient-photo-empty')).toHaveTextContent('Sin foto');
    });
    expect(detailCalls).toBe(2);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('quitar con un error del servidor: el mensaje dentro del diálogo, la foto sigue', async () => {
    photoHandler(['foto-1']);
    server.use(
      http.delete(PHOTO_URL, () => failure(500, 'INTERNAL_SERVER_ERROR', 'Error interno.')),
    );
    await openDetail(patientDetail({ has_photo: true }));
    await waitFor(() => {
      expect(image()).not.toBeNull();
    });

    fireEvent.click(screen.getByText('Quitar la foto', { selector: '.patient-photo-delete' }));
    await screen.findByRole('dialog');
    fireEvent.click(
      screen.getByText('Quitar la foto', { selector: '[role="dialog"] .btn-danger' }),
    );

    expect(await screen.findByRole('dialog')).toHaveTextContent(
      'No se pudo completar. Probá de nuevo en unos minutos.',
    );
    expect(image()).not.toBeNull();
  });

  it('subir a un paciente que ya no está (404 RESOURCE_NOT_FOUND): aviso y al listado', async () => {
    server.use(
      http.post(PHOTO_URL, () => failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.')),
      http.get('/api/patients', () =>
        success([], {
          meta: { pagination: { total: 0, per_page: 20, current_page: 1, last_page: 1 } },
        }),
      ),
      http.get('/api/species', () => success([])),
    );
    const { history } = await openDetail(patientDetail());
    choose(png());
    await screen.findByAltText('Vista previa de la foto nueva');

    fireEvent.click(screen.getByText('Subir la foto', { selector: 'button' }));

    expect(await screen.findByText('El recurso no existe.')).toBeInTheDocument();
    await waitFor(() => {
      expect(history.location.pathname).toBe('/pacientes');
    });
  });

  it.each(ROLES)(
    '%s ve, sube y quita la foto (clinic-roles.json: los tres roles)',
    async (role) => {
      photoHandler(['foto-1']);
      await openDetail(patientDetail({ has_photo: true }), { role });

      await waitFor(() => {
        expect(image()).not.toBeNull();
      });
      expect(screen.getByText('Cambiar la foto', { selector: 'label' })).toBeInTheDocument();
      expect(document.querySelector('.patient-photo-delete')).not.toBeNull();
    },
  );

  it('también con el paciente fallecido', async () => {
    photoHandler(['foto-1']);
    await openDetail(
      patientDetail({
        has_photo: true,
        is_deceased: true,
        deceased_at: '2026-11-01T13:30:00.000000Z',
      }),
    );

    await waitFor(() => {
      expect(image()).not.toBeNull();
    });
    expect(screen.getByText('Cambiar la foto', { selector: 'label' })).toBeInTheDocument();
  });

  it('sin patients.upload-photo ni patients.delete-photo: la foto, sin el campo ni "Quitar la foto"', async () => {
    photoHandler(['foto-1']);
    const permissions = ROLE_PERMISSIONS.veterinario.filter(
      (permission) =>
        permission !== 'patients.upload-photo' && permission !== 'patients.delete-photo',
    );
    await openDetail(patientDetail({ has_photo: true }), { role: 'veterinario', permissions });

    await waitFor(() => {
      expect(image()).not.toBeNull();
    });
    expect(document.querySelector('.patient-photo-form')).toBeNull();
    expect(document.querySelector('.patient-photo-delete')).toBeNull();
  });

  it('sin patients.show-photo: no se pide la foto', async () => {
    const calls = photoHandler(['foto-1']);
    const permissions = ROLE_PERMISSIONS.recepcionista.filter(
      (permission) => permission !== 'patients.show-photo',
    );
    await openDetail(patientDetail({ has_photo: true }), { permissions });

    expect(document.querySelector('.patient-photo-empty')).toHaveTextContent('Tiene foto');
    expect(calls).toHaveLength(0);
  });

  it('sin ninguno de los tres permisos: sin la tarjeta de la foto', async () => {
    const permissions = ROLE_PERMISSIONS.recepcionista.filter(
      (permission) => !permission.endsWith('-photo'),
    );
    await openDetail(patientDetail({ has_photo: true }), { permissions });

    expect(screen.queryByText('Foto', { selector: '.card-title' })).not.toBeInTheDocument();
  });

  it('el listado de pacientes no descarga fotos', async () => {
    const calls = photoHandler(['foto-1']);
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/patients', () =>
        success([{ ...patientDetail({ has_photo: true }) }], {
          meta: { pagination: { total: 1, per_page: 20, current_page: 1, last_page: 1 } },
        }),
      ),
      http.get('/api/species', () => success([])),
    );
    renderApp('/pacientes', { session: sessionFor() });

    await screen.findByText('Firulais', { selector: 'a' });
    expect(calls).toHaveLength(0);
  });
});
