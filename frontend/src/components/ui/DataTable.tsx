import type { ReactNode } from 'react';
import type { Pagination } from '../../lib/api/types';

export interface Column<T> {
  header: string;
  cell: (row: T) => ReactNode;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  pagination?: Pagination;
  onPageChange?: (page: number) => void;
  empty?: string;
  /**
   * El enlace a la página 1 (un `<Link>` con la búsqueda de la URL, sin la página) que acompaña a "Esta página no existe."
   * cuando la página pedida pasa la última (`?pagina=99` de 7, o después de borrar): sin filas no es "no hay datos".
   */
  firstPageLink?: ReactNode;
}

/** Una página más allá de la última: la API devuelve la página vacía, con el total real. */
function isPageOutOfRange(pagination: Pagination | undefined): boolean {
  return pagination !== undefined && pagination.current_page > pagination.last_page;
}

/** Tabla de un listado, con la paginación de `meta.pagination` (snake_case). */
export function DataTable<T>({
  columns,
  rows,
  rowKey,
  pagination,
  onPageChange,
  empty = 'No hay datos para mostrar.',
  firstPageLink,
}: DataTableProps<T>) {
  const outOfRange = rows.length === 0 && isPageOutOfRange(pagination);

  return (
    <>
      <div className="table-responsive">
        <table className="table table-vcenter card-table">
          <thead>
            <tr>
              {columns.map((column) => (
                <th key={column.header}>{column.header}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="text-center text-secondary">
                  {outOfRange ? (
                    <span className="page-out-of-range">
                      Esta página no existe.
                      {firstPageLink === undefined ? null : <> {firstPageLink}</>}
                    </span>
                  ) : (
                    empty
                  )}
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={rowKey(row)}>
                  {columns.map((column) => (
                    <td key={column.header}>{column.cell(row)}</td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      {pagination ? (
        <div className="card-footer">
          <PaginationBar pagination={pagination} onPageChange={onPageChange ?? noop} />
        </div>
      ) : null}
    </>
  );
}

function noop(): void {
  // Sin cambio de página.
}

interface PaginationBarProps {
  pagination: Pagination;
  onPageChange: (page: number) => void;
}

export function PaginationBar({ pagination, onPageChange }: PaginationBarProps) {
  const { total, per_page, current_page, last_page } = pagination;
  const start = (current_page - 1) * per_page + 1;
  // Una página fuera de rango (la 5 de 3, después de borrar) no tiene filas: "Mostrando 0 a 0 de 32".
  const from = start > total ? 0 : start;
  const to = from === 0 ? 0 : Math.min(current_page * per_page, total);

  return (
    <div className="d-flex align-items-center">
      <p className="m-0 text-secondary">
        Mostrando {from} a {to} de {total}
      </p>
      <ul className="pagination m-0 ms-auto">
        <li className={`page-item${current_page <= 1 ? ' disabled' : ''}`}>
          <button
            type="button"
            className="page-link"
            disabled={current_page <= 1}
            onClick={() => {
              onPageChange(current_page - 1);
            }}
          >
            Anterior
          </button>
        </li>
        <li className="page-item active">
          <span className="page-link">
            Página {current_page} de {last_page}
          </span>
        </li>
        <li className={`page-item${current_page >= last_page ? ' disabled' : ''}`}>
          <button
            type="button"
            className="page-link"
            disabled={current_page >= last_page}
            onClick={() => {
              onPageChange(current_page + 1);
            }}
          >
            Siguiente
          </button>
        </li>
      </ul>
    </div>
  );
}
