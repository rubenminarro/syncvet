import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { DataTable } from './DataTable';

const rows = [
  { id: 1, name: 'Firulais' },
  { id: 2, name: 'Michi' },
];

describe('DataTable', () => {
  it('lee per_page, current_page y last_page de meta.pagination', async () => {
    const onPageChange = vi.fn();
    render(
      <DataTable
        columns={[{ header: 'Nombre', cell: (row) => row.name }]}
        rows={rows}
        rowKey={(row) => row.id}
        pagination={{ total: 32, per_page: 15, current_page: 2, last_page: 3 }}
        onPageChange={onPageChange}
      />,
    );

    expect(screen.getByText('Firulais')).toBeInTheDocument();
    expect(screen.getByText('Mostrando 16 a 30 de 32')).toBeInTheDocument();
    expect(screen.getByText('Página 2 de 3')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Siguiente' }));
    await userEvent.click(screen.getByRole('button', { name: 'Anterior' }));

    expect(onPageChange.mock.calls).toEqual([[3], [1]]);
  });

  it('en la última página no deja avanzar; sin filas muestra el aviso', () => {
    render(
      <DataTable
        columns={[{ header: 'Nombre', cell: (row: { id: number; name: string }) => row.name }]}
        rows={[]}
        rowKey={(row) => row.id}
        pagination={{ total: 0, per_page: 15, current_page: 1, last_page: 1 }}
      />,
    );

    expect(screen.getByText('No hay datos para mostrar.')).toBeInTheDocument();
    expect(screen.getByText('Mostrando 0 a 0 de 0')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Siguiente' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Anterior' })).toBeDisabled();
  });
});
