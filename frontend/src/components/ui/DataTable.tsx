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
}

/** Tabla de un listado, con la paginación de `meta.pagination` (snake_case). */
export function DataTable<T>({
  columns,
  rows,
  rowKey,
  pagination,
  onPageChange,
  empty = 'No hay datos para mostrar.',
}: DataTableProps<T>) {
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
                  {empty}
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
  const from = total === 0 ? 0 : (current_page - 1) * per_page + 1;
  const to = Math.min(current_page * per_page, total);

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
