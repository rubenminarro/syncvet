import { useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment, useState } from 'react';
import { Button, Card, ErrorPage } from '../../components/ui';
import { formatDateTime } from '../../lib/format/dates';
import { clientDetailQueryOptions } from './api';
import type { ClientDetail, ClientPatient } from './types';

/** /clientes/$id: los datos del cliente y sus pacientes (los del GET clients/{id}, pregunta 3 del diseño). */
export function ClientDetailPage({ id }: { id: number }) {
  const { data: client } = useSuspenseQuery(clientDetailQueryOptions(id));

  return (
    <>
      <div className="page-header mb-3">
        <div className="page-pretitle">
          <Link to="/clientes">Clientes</Link>
        </div>
        <h2 className="page-title">{client.display_name}</h2>
      </div>
      <div className="row row-cards">
        <div className="col-lg-5">
          <ClientData client={client} />
        </div>
        <div className="col-lg-7">
          <ClientPatients patients={client.patients} />
        </div>
      </div>
    </>
  );
}

/** Un dato vacío se muestra con una raya: la fila sigue a la vista. */
function orDash(value: string | null | undefined): string {
  return value === null || value === undefined || value === '' ? '—' : value;
}

function ClientData({ client }: { client: ClientDetail }) {
  const isCompany = client.kind.value === 'company';
  const rows: [string, string][] = [
    ['Tipo', client.kind.label],
    [isCompany ? 'RUC' : 'Documento', client.document_number],
    ...(isCompany
      ? ([['Razón social', orDash(client.business_name)]] as [string, string][])
      : ([
          ['Nombre', orDash(client.first_name)],
          ['Apellido', orDash(client.last_name)],
        ] as [string, string][])),
    ['Teléfono', orDash(client.phone)],
    ['Email', orDash(client.email)],
    ['Dirección', orDash(client.address)],
    ['Ciudad', orDash(client.city)],
    ['Notificaciones', client.notifications_enabled ? 'Activadas' : 'Desactivadas'],
    ['Notas', orDash(client.notes)],
    ['Alta', orDash(formatDateTime(client.created_at))],
    ['Última modificación', orDash(formatDateTime(client.updated_at))],
  ];

  return (
    <Card title="Datos del cliente">
      <dl className="row mb-0 client-data">
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

function ClientPatients({ patients }: { patients: ClientPatient[] }) {
  const [showDeceased, setShowDeceased] = useState(false);
  const deceased = patients.filter((patient) => patient.is_deceased).length;
  const visible = showDeceased ? patients : patients.filter((patient) => !patient.is_deceased);

  let empty: string | null = null;

  if (patients.length === 0) {
    empty = 'Este cliente todavía no tiene pacientes.';
  } else if (visible.length === 0) {
    empty = 'Todos sus pacientes figuran como fallecidos.';
  }

  return (
    <Card
      title="Pacientes"
      actions={
        deceased > 0 ? (
          <Button
            variant="link"
            className="toggle-deceased"
            aria-pressed={showDeceased}
            onClick={() => {
              setShowDeceased((current) => !current);
            }}
          >
            {showDeceased ? 'Ocultar los fallecidos' : `Mostrar los fallecidos (${deceased})`}
          </Button>
        ) : null
      }
    >
      {empty !== null ? (
        <p className="mb-0 text-secondary">{empty}</p>
      ) : (
        <div className="row row-cards">
          {visible.map((patient) => (
            <div key={patient.id} className="col-sm-6">
              <PatientCard patient={patient} />
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}

function PatientCard({ patient }: { patient: ClientPatient }) {
  const details = [
    patient.species?.name,
    patient.breed?.name,
    patient.sex.label,
    patient.age ?? undefined,
  ].filter((value): value is string => value !== undefined && value !== '');

  return (
    <div className={`card card-sm patient-card${patient.is_deceased ? ' bg-light' : ''}`}>
      <div className="card-body">
        <div className="d-flex align-items-center gap-2">
          <span className="fw-bold patient-name">{patient.name}</span>
          {patient.is_deceased ? (
            <span className="badge bg-secondary-lt patient-deceased">Fallecido</span>
          ) : null}
        </div>
        <div className="text-secondary small">{patient.code}</div>
        <div className="text-secondary patient-details">{details.join(' · ')}</div>
      </div>
    </div>
  );
}

/** El cliente no existe, es de otra clínica o el id no es válido: la página de no encontrado, dentro del layout. */
export function ClientNotFound() {
  return (
    <ErrorPage
      code="404"
      title="No encontramos este cliente"
      message="El cliente no existe o fue eliminado. Buscalo en el listado."
      action={
        <Link to="/clientes" className="btn btn-primary">
          Volver a los clientes
        </Link>
      }
    />
  );
}
