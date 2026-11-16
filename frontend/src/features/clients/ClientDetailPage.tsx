import { IconEdit, IconPlus, IconTrash } from '@tabler/icons-react';
import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link, useNavigate } from '@tanstack/react-router';
import { Fragment, useState } from 'react';
import { Button, Card, ConfirmDialog, ErrorPage, useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDateTime } from '../../lib/format/dates';
import { useCan } from '../auth/permissions';
import { afterClientGone, clientDetailQueryOptions, deleteClient } from './api';
import { useClientListSearch, withClientList } from './navigation';
import type { ClientDetail, ClientPatient } from './types';

/** /clientes/$id: los datos del cliente y sus pacientes (los del GET clients/{id}, pregunta 3 del diseño). */
export function ClientDetailPage({ id }: { id: number }) {
  const { data: client } = useSuspenseQuery(clientDetailQueryOptions(id));
  const can = useCan();
  const listSearch = useClientListSearch();

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/clientes" search={listSearch}>
                Clientes
              </Link>
            </div>
            <h2 className="page-title">{client.display_name}</h2>
          </div>
          <div className="col-auto d-flex gap-2 client-actions">
            {can(PERMISSIONS.clientsUpdate) ? (
              <Link
                to="/clientes/$id/editar"
                params={{ id: String(client.id) }}
                state={withClientList(listSearch)}
                className="btn btn-primary client-edit"
              >
                <IconEdit size={18} className="me-2" aria-hidden="true" />
                Editar
              </Link>
            ) : null}
            {can(PERMISSIONS.clientsDestroy) ? <DeleteClient client={client} /> : null}
          </div>
        </div>
      </div>
      <div className="row row-cards">
        <div className="col-lg-5">
          <ClientData client={client} />
        </div>
        <div className="col-lg-7">
          <ClientPatients clientId={client.id} patients={client.patients} />
        </div>
      </div>
    </>
  );
}

/** Un cliente con pacientes, turnos, consultas o facturas no se elimina (backend/CLAUDE.md §6.1). */
const CLIENT_IN_USE = 'CLIENT_IN_USE';

/**
 * La baja (solo el administrador, `clients.destroy`), con confirmación. CLIENT_IN_USE se muestra dentro del diálogo, con
 * solo "Cerrar" (los clientes no se inactivan). Con el 200, al listado de donde se vino.
 */
function DeleteClient({ client }: { client: ClientDetail }) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const listSearch = useClientListSearch();
  const [open, setOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [inUse, setInUse] = useState(false);

  // Los listados se vacían antes de volver (no se ve el cliente borrado) y el detalle sale de la caché después.
  const leave = () => {
    const forgetDetail = afterClientGone(queryClient, client.id);

    return navigate({ to: '/clientes', search: listSearch, replace: true }).then(forgetDetail);
  };

  const mutation = useMutation({
    mutationFn: () => deleteClient(client.id),
    onMutate: () => {
      setError(null);
    },
    onSuccess: (response) => {
      toast.show(response.message, 'success');
      void leave();
    },
    onError: (failure) => {
      if (!isApiError(failure)) {
        setError(MESSAGES.programming);

        return;
      }

      if (failure.errorCode === CLIENT_IN_USE) {
        setInUse(true);
        setError(failure.message);

        return;
      }

      if (errorTreatment(failure.errorCode) === 'not-found') {
        // Ya no está (otra persona lo borró): aviso y al listado.
        toast.show(failure.message);
        void leave();

        return;
      }

      setError(userMessage(failure));
    },
  });

  return (
    <>
      <Button
        variant="danger"
        className="client-delete"
        icon={<IconTrash size={18} className="me-2" aria-hidden="true" />}
        onClick={() => {
          setOpen(true);
        }}
      >
        Eliminar
      </Button>
      <ConfirmDialog
        open={open}
        title="Eliminar el cliente"
        confirmLabel={`Eliminar a ${client.display_name}`}
        busy={mutation.isPending}
        error={error}
        canConfirm={!inUse}
        onConfirm={() => {
          mutation.mutate();
        }}
        onClose={() => {
          setOpen(false);
          setError(null);
          setInUse(false);
        }}
      >
        ¿Querés eliminar a <strong>{client.display_name}</strong>? No se puede deshacer.
      </ConfirmDialog>
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

function ClientPatients({ clientId, patients }: { clientId: number; patients: ClientPatient[] }) {
  const can = useCan();
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
        <div className="d-flex gap-2 align-items-center">
          {deceased > 0 ? (
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
          ) : null}
          {can(PERMISSIONS.patientsStore) ? (
            <Link
              to="/clientes/$id/pacientes/nuevo"
              params={{ id: String(clientId) }}
              className="btn btn-sm btn-primary patient-new"
            >
              <IconPlus size={16} className="me-1" aria-hidden="true" />
              Nuevo paciente
            </Link>
          ) : null}
        </div>
      }
    >
      {empty !== null ? (
        <p className="mb-0 text-secondary">{empty}</p>
      ) : (
        <div className="row row-cards">
          {visible.map((patient) => (
            <div key={patient.id} className="col-sm-6">
              <PatientCard patient={patient} link={can(PERMISSIONS.patientsShow)} />
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}

function PatientCard({ patient, link }: { patient: ClientPatient; link: boolean }) {
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
          {link ? (
            <Link
              to="/pacientes/$id"
              params={{ id: String(patient.id) }}
              className="fw-bold patient-name"
            >
              {patient.name}
            </Link>
          ) : (
            <span className="fw-bold patient-name">{patient.name}</span>
          )}
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
