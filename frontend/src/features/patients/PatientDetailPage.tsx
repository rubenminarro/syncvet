import { IconAlertTriangle, IconEdit } from '@tabler/icons-react';
import { useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment } from 'react';
import { Card, ErrorPage } from '../../components/ui';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDateOnly, formatDateTime } from '../../lib/format/dates';
import { useCan } from '../auth/permissions';
import { patientDetailQueryOptions } from './api';
import { formatWeight } from './form';
import { usePatientListSearch, withPatientList } from './navigation';
import type { PatientDetail } from './types';

/** Un dato vacío se muestra con una raya: la fila sigue a la vista. */
function orDash(value: string | null | undefined): string {
  return value === null || value === undefined || value === '' ? '—' : value;
}

/** /pacientes/$id: los datos del paciente y su dueño (el estado y la foto llegan en la 1.3b y la 1.4). */
export function PatientDetailPage({ id }: { id: number }) {
  const { data: patient } = useSuspenseQuery(patientDetailQueryOptions(id));
  const can = useCan();
  const listSearch = usePatientListSearch();

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/pacientes" search={listSearch}>
                Pacientes
              </Link>
            </div>
            <h2 className="page-title">
              {patient.name}
              {patient.is_deceased ? (
                <span className="badge bg-secondary-lt ms-2 patient-deceased">Fallecido</span>
              ) : null}
            </h2>
          </div>
          <div className="col-auto d-flex gap-2 patient-actions">
            {can(PERMISSIONS.patientsUpdate) ? (
              <Link
                to="/pacientes/$id/editar"
                params={{ id: String(patient.id) }}
                state={withPatientList(listSearch)}
                className="btn btn-primary patient-edit"
              >
                <IconEdit size={18} className="me-2" aria-hidden="true" />
                Editar
              </Link>
            ) : null}
          </div>
        </div>
      </div>
      {patient.allergies !== null && patient.allergies !== '' ? (
        <div className="alert alert-warning patient-allergies" role="note">
          <div className="d-flex gap-2">
            <IconAlertTriangle size={20} aria-hidden="true" />
            <div>
              <h4 className="alert-title">Alergias</h4>
              <div className="text-break">{patient.allergies}</div>
            </div>
          </div>
        </div>
      ) : null}
      <div className="row row-cards">
        <div className="col-lg-7">
          <PatientData patient={patient} />
        </div>
        <div className="col-lg-5">
          <PatientOwner patient={patient} />
        </div>
      </div>
    </>
  );
}

function PatientData({ patient }: { patient: PatientDetail }) {
  const birthDate = formatDateOnly(patient.birth_date);
  const rows: [string, string][] = [
    ['Código', patient.code],
    ['Especie', orDash(patient.species?.name)],
    ['Raza', orDash(patient.breed?.name)],
    ['Sexo', patient.sex.label],
    ['Castrado', patient.is_neutered ? 'Sí' : 'No'],
    [
      'Fecha de nacimiento',
      birthDate === '' ? '—' : `${birthDate}${patient.birth_date_estimated ? ' (aprox.)' : ''}`,
    ],
    ['Edad', orDash(patient.age)],
    ['Peso', orDash(formatWeight(patient.weight_kg))],
    ['Microchip', orDash(patient.microchip)],
    ['Color', orDash(patient.color)],
    ['Alergias', orDash(patient.allergies)],
    ['Notas', orDash(patient.notes)],
    ['Alta', orDash(formatDateTime(patient.created_at))],
    ['Última modificación', orDash(formatDateTime(patient.updated_at))],
  ];

  return (
    <Card title="Datos del paciente">
      <dl className="row mb-0 patient-data">
        {rows.map(([label, value]) => (
          <Fragment key={label}>
            <dt className="col-5">{label}</dt>
            <dd className="col-7 text-break">{value}</dd>
          </Fragment>
        ))}
      </dl>
    </Card>
  );
}

function PatientOwner({ patient }: { patient: PatientDetail }) {
  const can = useCan();
  const owner = patient.client;

  return (
    <Card title="Dueño">
      {owner === null ? (
        <p className="mb-0 text-secondary">—</p>
      ) : (
        <dl className="row mb-0 patient-owner">
          <dt className="col-5">Nombre</dt>
          <dd className="col-7 text-break">
            {can(PERMISSIONS.clientsShow) && !owner.deleted ? (
              <Link
                to="/clientes/$id"
                params={{ id: String(owner.id) }}
                className="patient-owner-link"
              >
                {owner.display_name}
              </Link>
            ) : (
              owner.display_name
            )}
            {owner.deleted ? (
              <span className="badge bg-secondary-lt ms-2 patient-owner-deleted">
                Cliente eliminado
              </span>
            ) : null}
          </dd>
          <dt className="col-5">Teléfono</dt>
          <dd className="col-7 text-break">{orDash(owner.phone)}</dd>
          <dt className="col-5">Email</dt>
          <dd className="col-7 text-break">{orDash(owner.email)}</dd>
        </dl>
      )}
    </Card>
  );
}

/** El paciente no existe, es de otra clínica o el id no es válido: la página de no encontrado, dentro del layout. */
export function PatientNotFound() {
  return (
    <ErrorPage
      code="404"
      title="No encontramos este paciente"
      message="El paciente no existe o fue eliminado. Buscalo en el listado."
      action={
        <Link to="/pacientes" className="btn btn-primary">
          Volver a los pacientes
        </Link>
      }
    />
  );
}
