import { useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment, type ReactNode } from 'react';
import { Card, ErrorPage } from '../../components/ui';
import { formatDateTimeSeconds } from '../../lib/format/dates';
import { auditLogDetailQueryOptions } from './api';
import { maskAuditValues } from './mask';
import { useAuditListSearch } from './navigation';
import { auditableTypeLabel, type AuditLogDetail, type AuditValues } from './types';

/**
 * /configuracion/auditoria/$id (2.6, solo el administrador; solo lectura): la fecha y la hora con segundos (en Asunción), el
 * usuario, el evento, el registro, la IP, el navegador y los cambios. Los valores pasan siempre por `maskAuditValues` (sin
 * contraseñas, tokens ni rutas de archivos, aunque la API ya los oculte) y se muestran como texto, nunca como HTML.
 */
export function AuditDetailPage({ id }: { id: number }) {
  const { data: log } = useSuspenseQuery(auditLogDetailQueryOptions(id));
  const listSearch = useAuditListSearch();

  return (
    <>
      <div className="page-header mb-3">
        <div className="page-pretitle">
          <Link to="/configuracion/auditoria" search={listSearch}>
            Auditoría
          </Link>
        </div>
        <h2 className="page-title">Registro de auditoría n.º {log.id}</h2>
      </div>
      <div className="row row-cards">
        <div className="col-lg-5">
          <AuditData log={log} />
        </div>
        <div className="col-lg-7">
          <AuditChanges log={log} />
        </div>
      </div>
    </>
  );
}

/** Un dato vacío se muestra con una raya: la fila sigue a la vista. */
function orDash(value: string | null | undefined): string {
  return value === null || value === undefined || value === '' ? '—' : value;
}

function AuditData({ log }: { log: AuditLogDetail }) {
  const record =
    log.auditable_type === null
      ? '—'
      : `${auditableTypeLabel(log.auditable_type)} n.º ${String(log.auditable_id ?? '—')}`;
  const rows: [string, ReactNode][] = [
    ['Fecha y hora', orDash(formatDateTimeSeconds(log.created_at))],
    ['Usuario', log.user === null ? '—' : log.user.name],
    ['Evento', log.event.label],
    ['Registro', record],
    ['IP', orDash(log.ip_address)],
    ['Navegador', orDash(log.user_agent)],
  ];

  return (
    <Card title="Datos del registro">
      <dl className="row mb-0 audit-data">
        {rows.map(([label, value]) => (
          <Fragment key={label}>
            <dt className="col-5 col-md-4">{label}</dt>
            <dd className="col-7 col-md-8 text-break">{value}</dd>
          </Fragment>
        ))}
      </dl>
    </Card>
  );
}

/**
 * Un valor de un cambio, como texto: null es "(vacío)"; una lista o un objeto, su JSON con sangría en un `<pre>`; lo demás,
 * su texto. React escapa todo: un valor con HTML se ve tal cual, no se interpreta.
 */
function AuditValue({ values, field }: { values: AuditValues; field: string }) {
  if (!Object.hasOwn(values, field)) {
    return <span className="text-secondary">—</span>;
  }

  const value = values[field];

  if (value === null || value === undefined || value === '') {
    return <span className="text-secondary">(vacío)</span>;
  }

  if (typeof value === 'object') {
    return <pre className="audit-json mb-0">{JSON.stringify(value, null, 2)}</pre>;
  }

  return (
    <span className="audit-text">{typeof value === 'string' ? value : JSON.stringify(value)}</span>
  );
}

/**
 * La tabla de los cambios: el campo con el nombre de la API (pregunta 11 del diseño), antes y después. Un alta tiene solo
 * "después" y una baja, solo "antes" (la API manda el otro lado en null); un evento sin valores (un ingreso), el aviso.
 */
function AuditChanges({ log }: { log: AuditLogDetail }) {
  const before = maskAuditValues(log.old_values);
  const after = maskAuditValues(log.new_values);
  const fields = Array.from(new Set([...Object.keys(before ?? {}), ...Object.keys(after ?? {})]));

  if (fields.length === 0) {
    return (
      <Card title="Cambios">
        <p className="text-secondary mb-0">Este registro no guarda cambios de datos.</p>
      </Card>
    );
  }

  return (
    <Card title="Cambios" flush>
      <div className="table-responsive">
        <table className="table table-vcenter card-table audit-changes">
          <thead>
            <tr>
              <th>Campo</th>
              {before !== null ? <th>Antes</th> : null}
              {after !== null ? <th>Después</th> : null}
            </tr>
          </thead>
          <tbody>
            {fields.map((field) => (
              <tr key={field}>
                <td className="text-nowrap">
                  <code>{field}</code>
                </td>
                {before !== null ? (
                  <td className="text-break">
                    <AuditValue values={before} field={field} />
                  </td>
                ) : null}
                {after !== null ? (
                  <td className="text-break">
                    <AuditValue values={after} field={field} />
                  </td>
                ) : null}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}

/** La página de no encontrado del detalle (un id inválido o el 404 de la API), dentro del layout. */
export function AuditLogNotFound() {
  return (
    <ErrorPage
      code="404"
      title="No encontramos este registro de auditoría"
      message="El registro no existe. Buscalo en la auditoría."
      action={
        <Link to="/configuracion/auditoria" className="btn btn-primary">
          Volver a la auditoría
        </Link>
      }
    />
  );
}
