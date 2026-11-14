import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useSearch } from '@tanstack/react-router';
import { ForbiddenPage } from '../../app/ForbiddenPage';
import {
  Button,
  Card,
  CheckboxField,
  DataTable,
  ErrorPage,
  Loading,
  SearchField,
  SelectField,
  type Column,
} from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSearchText } from '../../lib/hooks/use-search-text';
import { SEARCH_MAX_LENGTH } from '../../lib/search-text';
import { useCan } from '../auth/permissions';
import { patientListQueryOptions, speciesQueryOptions } from './api';
import { withPatientList } from './navigation';
import { toPatientListFilters, type PatientListSearch } from './search';
import type { Patient } from './types';

/**
 * /pacientes: la búsqueda, la especie, "Incluir los fallecidos" y la página van en la URL (la convención de listados de la
 * 1.1); recargar o volver atrás muestra lo mismo.
 */
export function PatientListPage() {
  const search = useSearch({ from: '/_sesion/pacientes/' });
  const navigate = useNavigate({ from: '/pacientes/' });
  const filters = toPatientListFilters(search);
  const query = useQuery(patientListQueryOptions(filters));
  // El filtro de especie: si GET species falla, el filtro queda solo con "Todas las especies" (el listado sigue).
  const species = useQuery(speciesQueryOptions());
  const can = useCan();

  // El campo es local; la URL cambia 300 ms después de la última tecla, sin llenar el historial y de vuelta en la página 1.
  const [text, setText] = useSearchText(search.buscar, (next) => {
    void navigate({
      search: (previous: PatientListSearch) => ({
        ...previous,
        buscar: next === '' ? undefined : next,
        pagina: undefined,
      }),
      replace: true,
    });
  });

  const speciesFilter = [
    { value: '', label: 'Todas las especies' },
    ...(species.data ?? []).map((item) => ({ value: String(item.id), label: item.name })),
  ];

  const showLink = can(PERMISSIONS.patientsShow);
  const showClientLink = can(PERMISSIONS.clientsShow);
  const columns: Column<Patient>[] = [
    { header: 'Código', cell: (patient) => patient.code },
    {
      header: 'Nombre',
      cell: (patient) => (
        <>
          {showLink ? (
            <Link
              to="/pacientes/$id"
              params={{ id: String(patient.id) }}
              state={withPatientList(search)}
              className="patient-link"
            >
              {patient.name}
            </Link>
          ) : (
            patient.name
          )}
          {patient.is_deceased ? (
            <span className="badge bg-secondary-lt ms-2 patient-deceased">Fallecido</span>
          ) : null}
        </>
      ),
    },
    { header: 'Especie', cell: (patient) => patient.species?.name ?? '' },
    { header: 'Raza', cell: (patient) => patient.breed?.name ?? '' },
    { header: 'Sexo', cell: (patient) => patient.sex.label },
    { header: 'Edad', cell: (patient) => patient.age ?? '' },
    {
      header: 'Dueño',
      cell: (patient) => {
        if (patient.client === null) {
          return '';
        }

        return showClientLink ? (
          <Link
            to="/clientes/$id"
            params={{ id: String(patient.client.id) }}
            className="patient-owner-link"
          >
            {patient.client.display_name}
          </Link>
        ) : (
          patient.client.display_name
        );
      },
    },
  ];

  const filtered = filters.search !== '' || filters.species_id !== null || filters.include_deceased;
  let content;

  if (query.data !== undefined && !query.isError) {
    content = (
      <DataTable
        columns={columns}
        rows={query.data.items}
        rowKey={(patient) => patient.id}
        pagination={query.data.pagination}
        onPageChange={(page) => {
          void navigate({
            search: (previous: PatientListSearch) => ({
              ...previous,
              pagina: page === 1 ? undefined : page,
            }),
          });
        }}
        empty={
          filtered ? 'No hay pacientes que coincidan con la búsqueda.' : 'Todavía no hay pacientes.'
        }
        firstPageLink={
          <Link
            to="/pacientes"
            search={(previous: PatientListSearch) => ({ ...previous, pagina: undefined })}
            className="first-page-link"
          >
            Ir a la página 1
          </Link>
        }
      />
    );
  } else if (query.isError) {
    content =
      isApiError(query.error) && errorTreatment(query.error.errorCode) === 'forbidden' ? (
        <ForbiddenPage message={query.error.message} />
      ) : (
        <ErrorPage
          title="No se pudieron cargar los pacientes"
          message={isApiError(query.error) ? userMessage(query.error) : MESSAGES.programming}
          action={
            <Button
              onClick={() => {
                void query.refetch();
              }}
            >
              Probá de nuevo
            </Button>
          }
        />
      );
  } else {
    content = <Loading label="Cargando los pacientes…" />;
  }

  return (
    <>
      <div className="page-header mb-3">
        <h2 className="page-title">Pacientes</h2>
        <div className="text-secondary">
          Los pacientes nuevos se cargan desde el detalle de su dueño.
        </div>
      </div>
      <Card flush>
        <div className="card-body border-bottom">
          <div className="row g-2 align-items-center">
            <SearchField
              className="col-md-6"
              label="Buscar pacientes"
              placeholder="Buscá por nombre, código, microchip o dueño"
              value={text}
              onChange={setText}
              maxLength={SEARCH_MAX_LENGTH}
              busy={query.isFetching && query.data !== undefined}
            />
            <SelectField
              className="col-md-3"
              label="Especie"
              hideLabel
              options={speciesFilter}
              value={search.especie === undefined ? '' : String(search.especie)}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: PatientListSearch) => ({
                    ...previous,
                    especie: value === '' ? undefined : Number(value),
                    pagina: undefined,
                  }),
                });
              }}
            />
            <div className="col-md-3 patient-deceased-filter">
              <CheckboxField
                label="Incluir los fallecidos"
                checked={search.fallecidos === 1}
                onChange={(event) => {
                  const checked = event.target.checked;
                  void navigate({
                    search: (previous: PatientListSearch) => ({
                      ...previous,
                      fallecidos: checked ? 1 : undefined,
                      pagina: undefined,
                    }),
                  });
                }}
              />
            </div>
          </div>
        </div>
        {content}
      </Card>
    </>
  );
}
