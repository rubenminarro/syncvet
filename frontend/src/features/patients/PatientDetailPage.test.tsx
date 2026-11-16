import { fireEvent, screen, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { describe, expect, it } from 'vitest';
import { failure, success } from '../../test/api';
import { detail, patient, PERSON } from '../../test/clients';
import { patientDetail } from '../../test/patients';
import { meFor, ROLE_PERMISSIONS, ROLES, type Role } from '../../test/roles';
import { server } from '../../test/server';
import { meHandler, renderApp, sessionFor } from '../../test/session';
import type { PatientDetail } from './types';

/** El valor de una fila de una lista de datos (dt/dd), por su etiqueta. */
function dataValue(container: string, label: string): string | null {
  const term = Array.from(document.querySelectorAll(`${container} dt`)).find(
    (node) => node.textContent === label,
  );

  return term?.nextElementSibling?.textContent ?? null;
}

async function openDetail(data: PatientDetail = patientDetail(), role: Role = 'recepcionista') {
  server.use(
    meHandler(meFor(role)),
    http.get('/api/patients/:id', () => success(data)),
  );
  const rendered = renderApp(`/pacientes/${String(data.id)}`, { session: sessionFor() });
  await screen.findByText(data.name, { selector: 'h2', exact: false });

  return rendered;
}

describe('/pacientes/$id: detalle', () => {
  it('sus datos: la fecha de nacimiento sin correrse un día, el peso con coma y las fechas en la hora de Asunción', async () => {
    await openDetail(
      patientDetail({
        birth_date: '2020-05-10',
        birth_date_estimated: true,
        weight_kg: '12.50',
        is_neutered: true,
        notes: 'Tranquilo.',
      }),
    );

    // Con la suite en UTC, una fecha sin hora pasada por Date se mostraría el día anterior en Asunción.
    expect(dataValue('.patient-data', 'Fecha de nacimiento')).toBe('10/05/2020 (aprox.)');
    expect(dataValue('.patient-data', 'Peso')).toBe('12,5 kg');
    expect(dataValue('.patient-data', 'Código')).toBe('P-000001');
    expect(dataValue('.patient-data', 'Especie')).toBe('Canino');
    expect(dataValue('.patient-data', 'Raza')).toBe('Mestizo');
    expect(dataValue('.patient-data', 'Sexo')).toBe('Macho');
    expect(dataValue('.patient-data', 'Castrado')).toBe('Sí');
    expect(dataValue('.patient-data', 'Edad')).toBe('4 años');
    expect(dataValue('.patient-data', 'Microchip')).toBe('985112003456789');
    expect(dataValue('.patient-data', 'Color')).toBe('Marrón');
    expect(dataValue('.patient-data', 'Notas')).toBe('Tranquilo.');
    expect(dataValue('.patient-data', 'Alta')).toBe('10/11/2026 10:30');
    expect(dataValue('.patient-data', 'Última modificación')).toBe('12/11/2026 15:05');
  });

  it('sin fecha, sin peso y sin raza: una raya; sin alergias, sin el aviso', async () => {
    await openDetail(
      patientDetail({ birth_date: null, age: null, weight_kg: null, breed: null, microchip: null }),
    );

    expect(dataValue('.patient-data', 'Fecha de nacimiento')).toBe('—');
    expect(dataValue('.patient-data', 'Peso')).toBe('—');
    expect(dataValue('.patient-data', 'Raza')).toBe('—');
    expect(document.querySelector('.patient-allergies')).toBeNull();
  });

  it('las alergias, resaltadas arriba', async () => {
    await openDetail(patientDetail({ allergies: 'Penicilina.' }));

    expect(document.querySelector('.patient-allergies')).toHaveTextContent('AlergiasPenicilina.');
  });

  it('el dueño, con enlace a su detalle, su teléfono y su email', async () => {
    await openDetail();

    expect(screen.getByText('Laura Martínez', { selector: '.patient-owner-link' })).toHaveAttribute(
      'href',
      '/clientes/1',
    );
    expect(dataValue('.patient-owner', 'Teléfono')).toBe('+595981100001');
    expect(dataValue('.patient-owner', 'Email')).toBe('laura.martinez@example.com');
  });

  it('un dueño eliminado: "Cliente eliminado", sin enlace', async () => {
    await openDetail(
      patientDetail({
        client: { id: 1, display_name: 'Laura Martínez', phone: null, email: null, deleted: true },
      }),
    );

    expect(screen.getByText('Cliente eliminado')).toBeInTheDocument();
    expect(document.querySelector('.patient-owner-link')).toBeNull();
  });

  it('un fallecido lleva la marca "Fallecido" en el título', async () => {
    await openDetail(
      patientDetail({ is_deceased: true, deceased_at: '2026-11-01T13:30:00.000000Z' }),
    );

    expect(screen.getByText('Fallecido', { selector: '.patient-deceased' })).toBeInTheDocument();
  });

  it.each(ROLES)('%s ve "Editar" (patients.update: los tres roles)', async (role) => {
    await openDetail(patientDetail(), role);

    expect(screen.getByText('Editar', { selector: 'a' })).toHaveAttribute(
      'href',
      '/pacientes/1/editar',
    );
  });

  it('sin patients.update, sin "Editar"', async () => {
    const permissions = ROLE_PERMISSIONS.veterinario.filter(
      (permission) => permission !== 'patients.update',
    );
    server.use(
      meHandler(meFor('veterinario', { permissions })),
      http.get('/api/patients/:id', () => success(patientDetail())),
    );
    renderApp('/pacientes/1', { session: sessionFor() });
    await screen.findByText('Firulais', { selector: 'h2' });

    expect(screen.queryByText('Editar', { selector: 'a' })).not.toBeInTheDocument();
  });

  it('un 404 de la API muestra la página de no encontrado, dentro del layout', async () => {
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/patients/:id', () =>
        failure(404, 'RESOURCE_NOT_FOUND', 'El recurso no existe.'),
      ),
    );
    renderApp('/pacientes/99', { session: sessionFor() });

    expect(await screen.findByText('No encontramos este paciente')).toBeInTheDocument();
    expect(document.querySelector('.session-user')).not.toBeNull();
  });

  it('un id que no es un entero positivo: no encontrado, sin llamar a la API', async () => {
    const calls: string[] = [];
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/patients/:id', ({ request }) => {
        calls.push(request.url);

        return success(patientDetail());
      }),
    );
    renderApp('/pacientes/01', { session: sessionFor() });

    expect(await screen.findByText('No encontramos este paciente')).toBeInTheDocument();
    expect(calls).toHaveLength(0);
  });

  it('un 403 FORBIDDEN de la API muestra la página de sin permiso con su mensaje', async () => {
    server.use(
      meHandler(meFor('recepcionista')),
      http.get('/api/patients/:id', () =>
        failure(403, 'FORBIDDEN', 'No tenés permiso para ver este paciente.'),
      ),
    );
    renderApp('/pacientes/1', { session: sessionFor() });

    expect(await screen.findByText('No tenés permiso para ver este paciente.')).toBeInTheDocument();
  });

  it('sin patients.show, /pacientes/1 va a /sin-permiso sin pedir el paciente', async () => {
    const calls: string[] = [];
    const permissions = ROLE_PERMISSIONS.recepcionista.filter(
      (permission) => permission !== 'patients.show',
    );
    server.use(
      meHandler(meFor('recepcionista', { permissions })),
      http.get('/api/patients/:id', ({ request }) => {
        calls.push(request.url);

        return success(patientDetail());
      }),
    );
    const { history } = renderApp('/pacientes/1', { session: sessionFor() });

    await waitFor(() => {
      expect(history.location.pathname).toBe('/sin-permiso');
    });
    expect(calls).toHaveLength(0);
  });
});

describe('/clientes/$id: los pacientes llevan a su detalle y "Nuevo paciente"', () => {
  it('el nombre del paciente es un enlace a /pacientes/$id; "Nuevo paciente" lleva al alta desde el cliente', async () => {
    server.use(
      meHandler(meFor('veterinario')),
      http.get('/api/clients/:id', () => success(detail(PERSON, [patient()]))),
      http.get('/api/patients/:id', () => success(patientDetail())),
    );
    const { history } = renderApp('/clientes/1', { session: sessionFor() });
    await screen.findByText('Laura Martínez', { selector: 'h2' });

    expect(screen.getByText('Nuevo paciente', { selector: 'a' })).toHaveAttribute(
      'href',
      '/clientes/1/pacientes/nuevo',
    );
    fireEvent.click(screen.getByText('Firulais', { selector: 'a' }));

    expect(await screen.findByText('Firulais', { selector: 'h2' })).toBeInTheDocument();
    expect(history.location.pathname).toBe('/pacientes/1');
  });

  it('sin patients.show ni patients.store: el nombre sin enlace y sin "Nuevo paciente"', async () => {
    const permissions = ROLE_PERMISSIONS.recepcionista.filter(
      (permission) => permission !== 'patients.show' && permission !== 'patients.store',
    );
    server.use(
      meHandler(meFor('recepcionista', { permissions })),
      http.get('/api/clients/:id', () => success(detail(PERSON, [patient()]))),
    );
    renderApp('/clientes/1', { session: sessionFor() });
    await screen.findByText('Laura Martínez', { selector: 'h2' });

    expect(screen.getByText('Firulais', { selector: 'span' })).toBeInTheDocument();
    expect(screen.queryByText('Nuevo paciente')).not.toBeInTheDocument();
  });
});
