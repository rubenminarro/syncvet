import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useSearch } from '@tanstack/react-router';
import { ForbiddenPage } from '../../app/ForbiddenPage';
import {
  Button,
  Card,
  DataTable,
  ErrorPage,
  Loading,
  SearchField,
  SelectField,
  type Column,
} from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { formatDateTime } from '../../lib/format/dates';
import { useSearchText } from '../../lib/hooks/use-search-text';
import { userOptionsQueryOptions } from '../users/api';
import { auditLogListQueryOptions } from './api';
import { AuditDateFilters } from './AuditDateFilters';
import { withAuditList } from './navigation';
import {
  isAuditableType,
  isAuditEvent,
  SEARCH_MAX_LENGTH,
  toAuditListFilters,
  type AuditListSearch,
} from './search';
import { AUDIT_EVENTS, AUDITABLE_TYPES, auditableTypeLabel, type AuditLog } from './types';

/**
 * /configuracion/auditoria (2.6, solo el administrador; solo lectura): las fechas "desde" y "hasta" (con hora opcional, en
 * Asunción), el usuario, el evento, el tipo (`entidad`) y el número del registro y la búsqueda en el nombre o el email del usuario, todo
 * en la URL, como en /clientes. En cada fila, el usuario y el registro son enlaces que filtran por ellos.
 */
export function AuditListPage() {
  const search = useSearch({ from: '/_sesion/configuracion/auditoria/' });
  const navigate = useNavigate({ from: '/configuracion/auditoria/' });
  const filters = toAuditListFilters(search);
  const query = useQuery(auditLogListQueryOptions(filters));
  // Con los inactivos: la auditoría guarda también lo que hicieron (H6 del diseño).
  const users = useQuery(userOptionsQueryOptions({ role: null, include_inactive: true }));

  const [text, setText] = useSearchText(search.buscar, (next) => {
    void navigate({
      search: (previous: AuditListSearch) => ({
        ...previous,
        buscar: next === '' ? undefined : next,
        pagina: undefined,
      }),
      replace: true,
    });
  });

  const userOptions = [
    { value: '', label: 'Todos los usuarios' },
    ...(users.data ?? []).map((user) => ({
      value: String(user.id),
      label: user.is_active ? user.name : `${user.name} (inactivo)`,
    })),
  ];

  // Un usuario de la URL que la lista no trae (uno eliminado, H6): se muestra igual, para no mentir sobre el filtro.
  if (
    filters.user_id !== null &&
    !userOptions.some((option) => option.value === String(filters.user_id))
  ) {
    userOptions.push({
      value: String(filters.user_id),
      label: `Usuario n.º ${String(filters.user_id)}`,
    });
  }

  const eventOptions = [
    { value: '', label: 'Todos los eventos' },
    ...AUDIT_EVENTS.map((event) => ({ value: event.value, label: event.label })),
  ];
  const typeOptions = [
    { value: '', label: 'Todos los registros' },
    ...AUDITABLE_TYPES.map((type) => ({ value: type.value, label: type.label })),
  ];

  const columns: Column<AuditLog>[] = [
    {
      header: 'Fecha y hora',
      cell: (log) => (
        <Link
          to="/configuracion/auditoria/$id"
          params={{ id: String(log.id) }}
          state={withAuditList(search)}
          className="audit-link text-nowrap"
        >
          {formatDateTime(log.created_at) || `Registro n.º ${String(log.id)}`}
        </Link>
      ),
    },
    {
      header: 'Usuario',
      cell: (log) =>
        log.user === null ? (
          '—'
        ) : (
          <Link
            to="/configuracion/auditoria"
            search={(previous: AuditListSearch) => ({
              ...previous,
              usuario: log.user?.id,
              pagina: undefined,
            })}
            title="Ver solo los de este usuario"
            className="audit-user-link"
          >
            {log.user.name}
          </Link>
        ),
    },
    { header: 'Evento', cell: (log) => log.event.label },
    {
      header: 'Registro',
      cell: (log) => {
        if (log.auditable_type === null) {
          return '—';
        }

        const label = `${auditableTypeLabel(log.auditable_type)} n.º ${String(log.auditable_id ?? '—')}`;
        const type = log.auditable_type;

        return isAuditableType(type) && log.auditable_id !== null ? (
          <Link
            to="/configuracion/auditoria"
            search={(previous: AuditListSearch) => ({
              ...previous,
              entidad: type,
              registro: log.auditable_id ?? undefined,
              pagina: undefined,
            })}
            title="Ver solo los de este registro"
            className="audit-record-link"
          >
            {label}
          </Link>
        ) : (
          label
        );
      },
    },
    { header: 'IP', cell: (log) => log.ip_address ?? '—' },
  ];

  const filtered =
    filters.from !== null ||
    filters.to !== null ||
    filters.user_id !== null ||
    filters.event !== null ||
    filters.auditable_type !== null ||
    filters.search !== '';
  let content;

  if (query.data !== undefined && !query.isError) {
    content = (
      <DataTable
        columns={columns}
        rows={query.data.items}
        rowKey={(log) => log.id}
        pagination={query.data.pagination}
        onPageChange={(page) => {
          void navigate({
            search: (previous: AuditListSearch) => ({
              ...previous,
              pagina: page === 1 ? undefined : page,
            }),
          });
        }}
        empty={
          filtered
            ? 'No hay registros que coincidan con los filtros.'
            : 'Todavía no hay registros de auditoría.'
        }
        firstPageLink={
          <Link
            to="/configuracion/auditoria"
            search={(previous: AuditListSearch) => ({ ...previous, pagina: undefined })}
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
          title="No se pudo cargar la auditoría"
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
    content = <Loading label="Cargando la auditoría…" />;
  }

  // Otra URL ("Atrás", un enlace de una fila) es otro formulario: arranca con lo de la URL.
  const dateFormKey = [search.desde, search.hasta, search.registro].join('|');

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/configuracion">Configuración</Link>
            </div>
            <h2 className="page-title">Auditoría</h2>
          </div>
          {filtered ? (
            <div className="col-auto">
              <Link to="/configuracion/auditoria" className="btn audit-clear">
                Quitar los filtros
              </Link>
            </div>
          ) : null}
        </div>
      </div>
      <Card flush>
        <div className="card-body border-bottom">
          <div className="row g-2 align-items-center mb-2">
            <SearchField
              className="col-lg-3"
              label="Buscar en la auditoría"
              placeholder="Nombre o email"
              value={text}
              onChange={setText}
              maxLength={SEARCH_MAX_LENGTH}
              busy={query.isFetching && query.data !== undefined}
            />
            <SelectField
              className="col-md-4 col-lg-3"
              label="Usuario"
              hideLabel
              options={userOptions}
              value={filters.user_id === null ? '' : String(filters.user_id)}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: AuditListSearch) => ({
                    ...previous,
                    usuario: value === '' ? undefined : Number(value),
                    pagina: undefined,
                  }),
                });
              }}
            />
            <SelectField
              className="col-md-4 col-lg-3"
              label="Evento"
              hideLabel
              options={eventOptions}
              value={filters.event ?? ''}
              onChange={(event) => {
                const value = event.target.value;
                void navigate({
                  search: (previous: AuditListSearch) => ({
                    ...previous,
                    evento: isAuditEvent(value) ? value : undefined,
                    pagina: undefined,
                  }),
                });
              }}
            />
            <SelectField
              className="col-md-4 col-lg-3"
              label="Tipo de registro"
              hideLabel
              options={typeOptions}
              value={filters.auditable_type ?? ''}
              onChange={(event) => {
                const value = event.target.value;
                // Sin tipo, el número del registro no vale (la API lo exige): sale con él.
                void navigate({
                  search: (previous: AuditListSearch) => ({
                    ...previous,
                    entidad: isAuditableType(value) ? value : undefined,
                    registro: isAuditableType(value) ? previous.registro : undefined,
                    pagina: undefined,
                  }),
                });
              }}
            />
          </div>
          <AuditDateFilters
            key={dateFormKey}
            desde={search.desde}
            hasta={search.hasta}
            registro={search.registro}
            hasType={search.entidad !== undefined}
            onApply={(values) => {
              void navigate({
                search: (previous: AuditListSearch) => ({
                  ...previous,
                  ...values,
                  pagina: undefined,
                }),
              });
            }}
          />
          <p className="text-secondary small mb-0">
            Las fechas y las horas son las de Asunción. Una fecha sin hora toma el día completo.
          </p>
        </div>
        {content}
      </Card>
    </>
  );
}
