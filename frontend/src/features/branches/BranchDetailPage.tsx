import { IconEdit, IconPlus } from '@tabler/icons-react';
import { useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { Fragment, useState } from 'react';
import { Button, Card, DataTable, ErrorPage, useToast, type Column } from '../../components/ui';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { formatDateTime } from '../../lib/format/dates';
import { useCan } from '../auth/permissions';
import { afterBranchChanged, branchDetailQueryOptions, updateBranch, updateWarehouse } from './api';
import { BranchBadges } from './BranchBadges';
import { StatusDialog, WarehouseDialog } from './BranchDialogs';
import { useBranchListSearch, withBranchList } from './navigation';
import type { BranchDetail, Warehouse } from './types';
import { useBranchGone } from './use-branch-gone';

/** El diálogo abierto del detalle: uno a la vez. */
type Dialog =
  | { type: 'branch-default' }
  | { type: 'branch-active'; active: boolean }
  | { type: 'warehouse-new' }
  | { type: 'warehouse-edit'; warehouse: Warehouse }
  | { type: 'warehouse-default'; warehouse: Warehouse }
  | { type: 'warehouse-active'; warehouse: Warehouse; active: boolean };

/**
 * /configuracion/sucursales/$id (2.1): los datos, las acciones de la sucursal (editar, marcar por defecto, activar o
 * inactivar) y la tarjeta de sus depósitos, con sus diálogos. La por defecto no se inactiva: el botón no está (y si el detalle
 * estaba viejo, el 409 de la API en el diálogo).
 */
export function BranchDetailPage({ id }: { id: number }) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const { data: branch } = useSuspenseQuery(branchDetailQueryOptions(id));
  const can = useCan();
  const listSearch = useBranchListSearch();
  const onBranchGone = useBranchGone(id);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const canUpdate = can(PERMISSIONS.branchesUpdate);

  const close = () => {
    setDialog(null);
  };

  // Un depósito que ya no está (404): aviso y la sucursal pedida de nuevo.
  const onWarehouseGone = (message: string) => {
    toast.show(message);
    void afterBranchChanged(queryClient);
    close();
  };

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <div className="page-pretitle">
              <Link to="/configuracion">Configuración</Link>
              {' / '}
              <Link to="/configuracion/sucursales" search={listSearch}>
                Sucursales
              </Link>
            </div>
            <h2 className="page-title d-flex gap-2 align-items-center">
              {branch.name} <BranchBadges item={branch} feminine />
            </h2>
          </div>
          {canUpdate ? (
            <div className="col-auto d-flex gap-2 flex-wrap branch-actions">
              <Link
                to="/configuracion/sucursales/$id/editar"
                params={{ id: String(branch.id) }}
                state={withBranchList(listSearch)}
                className="btn btn-primary branch-edit"
              >
                <IconEdit size={18} className="me-2" aria-hidden="true" />
                Editar
              </Link>
              {branch.is_active && !branch.is_default ? (
                <Button
                  variant="secondary"
                  className="branch-make-default"
                  onClick={() => {
                    setDialog({ type: 'branch-default' });
                  }}
                >
                  Marcar como por defecto
                </Button>
              ) : null}
              {branch.is_active ? (
                branch.is_default ? null : (
                  <Button
                    variant="secondary"
                    className="branch-deactivate"
                    onClick={() => {
                      setDialog({ type: 'branch-active', active: false });
                    }}
                  >
                    Inactivar
                  </Button>
                )
              ) : (
                <Button
                  variant="secondary"
                  className="branch-activate"
                  onClick={() => {
                    setDialog({ type: 'branch-active', active: true });
                  }}
                >
                  Activar
                </Button>
              )}
            </div>
          ) : null}
        </div>
      </div>
      <div className="row row-cards">
        <div className="col-lg-5">
          <BranchData branch={branch} />
        </div>
        <div className="col-lg-7">
          <BranchWarehouses branch={branch} onOpen={setDialog} />
        </div>
      </div>
      {dialog?.type === 'branch-default' ? (
        <StatusDialog
          title="Sucursal por defecto"
          confirmLabel="Marcar como por defecto"
          run={() => updateBranch(branch.id, { is_default: true })}
          onDone={(response) => {
            void afterBranchChanged(queryClient, response.data);
          }}
          onGone={onBranchGone}
          onClose={close}
        >
          ¿Querés que <strong>{branch.name}</strong> sea la sucursal por defecto? La anterior deja
          de serlo.
        </StatusDialog>
      ) : null}
      {dialog?.type === 'branch-active' ? (
        <StatusDialog
          title={dialog.active ? 'Activar la sucursal' : 'Inactivar la sucursal'}
          confirmLabel={dialog.active ? `Activar ${branch.name}` : `Inactivar ${branch.name}`}
          run={() => updateBranch(branch.id, { is_active: dialog.active })}
          onDone={(response) => {
            void afterBranchChanged(queryClient, response.data);
          }}
          onGone={onBranchGone}
          onClose={close}
        >
          {dialog.active ? (
            <>
              ¿Querés activar la sucursal <strong>{branch.name}</strong>?
            </>
          ) : (
            <>
              ¿Querés inactivar la sucursal <strong>{branch.name}</strong>? Los usuarios que la
              tienen como sucursal actual van a tener que elegir otra.
            </>
          )}
        </StatusDialog>
      ) : null}
      {dialog?.type === 'warehouse-new' ? (
        <WarehouseDialog branch={branch} onGone={onWarehouseGone} onClose={close} />
      ) : null}
      {dialog?.type === 'warehouse-edit' ? (
        <WarehouseDialog
          branch={branch}
          warehouse={dialog.warehouse}
          onGone={onWarehouseGone}
          onClose={close}
        />
      ) : null}
      {dialog?.type === 'warehouse-default' ? (
        <StatusDialog
          title="Depósito por defecto"
          confirmLabel="Marcar como por defecto"
          run={() => updateWarehouse(dialog.warehouse.id, { is_default: true })}
          onDone={() => {
            void afterBranchChanged(queryClient);
          }}
          onGone={onWarehouseGone}
          onClose={close}
        >
          ¿Querés que <strong>{dialog.warehouse.name}</strong> sea el depósito por defecto de la
          sucursal? El anterior deja de serlo.
        </StatusDialog>
      ) : null}
      {dialog?.type === 'warehouse-active' ? (
        <StatusDialog
          title={dialog.active ? 'Activar el depósito' : 'Inactivar el depósito'}
          confirmLabel={
            dialog.active
              ? `Activar ${dialog.warehouse.name}`
              : `Inactivar ${dialog.warehouse.name}`
          }
          run={() => updateWarehouse(dialog.warehouse.id, { is_active: dialog.active })}
          onDone={() => {
            void afterBranchChanged(queryClient);
          }}
          onGone={onWarehouseGone}
          onClose={close}
        >
          ¿Querés {dialog.active ? 'activar' : 'inactivar'} el depósito{' '}
          <strong>{dialog.warehouse.name}</strong>?
        </StatusDialog>
      ) : null}
    </>
  );
}

/** Un dato vacío se muestra con una raya: la fila sigue a la vista. */
function orDash(value: string | null | undefined): string {
  return value === null || value === undefined || value === '' ? '—' : value;
}

function BranchData({ branch }: { branch: BranchDetail }) {
  const rows: [string, string][] = [
    ['Código', branch.code],
    ['Nombre', branch.name],
    ['Establecimiento', branch.establishment],
    ['Teléfono', orDash(branch.phone)],
    ['Dirección', orDash(branch.address)],
    ['Por defecto', branch.is_default ? 'Sí' : 'No'],
    ['Estado', branch.is_active ? 'Activa' : 'Inactivada'],
    ['Alta', orDash(formatDateTime(branch.created_at))],
    ['Última modificación', orDash(formatDateTime(branch.updated_at))],
  ];

  return (
    <Card title="Datos de la sucursal">
      <dl className="row mb-0 branch-data">
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

/**
 * Los depósitos de la sucursal (del detalle: todos, el por defecto primero). Una sucursal inactiva no suma depósitos: sin
 * "Nuevo depósito" (la API respondería 422).
 */
function BranchWarehouses({
  branch,
  onOpen,
}: {
  branch: BranchDetail;
  onOpen: (dialog: Dialog) => void;
}) {
  const can = useCan();
  const canUpdate = can(PERMISSIONS.warehousesUpdate);

  const columns: Column<Warehouse>[] = [
    { header: 'Código', cell: (warehouse) => warehouse.code },
    { header: 'Nombre', cell: (warehouse) => warehouse.name },
    { header: 'Estado', cell: (warehouse) => <BranchBadges item={warehouse} feminine={false} /> },
  ];

  if (canUpdate) {
    columns.push({
      header: 'Acciones',
      cell: (warehouse) => (
        <div className="d-flex gap-1 flex-wrap warehouse-actions">
          <Button
            variant="link"
            className="p-0 warehouse-edit"
            aria-label={`Editar ${warehouse.name}`}
            onClick={() => {
              onOpen({ type: 'warehouse-edit', warehouse });
            }}
          >
            Editar
          </Button>
          {warehouse.is_active && !warehouse.is_default ? (
            <Button
              variant="link"
              className="p-0 warehouse-make-default"
              aria-label={`Marcar ${warehouse.name} como por defecto`}
              onClick={() => {
                onOpen({ type: 'warehouse-default', warehouse });
              }}
            >
              Marcar como por defecto
            </Button>
          ) : null}
          {warehouse.is_active ? (
            warehouse.is_default ? null : (
              <Button
                variant="link"
                className="p-0 warehouse-deactivate"
                aria-label={`Inactivar ${warehouse.name}`}
                onClick={() => {
                  onOpen({ type: 'warehouse-active', warehouse, active: false });
                }}
              >
                Inactivar
              </Button>
            )
          ) : (
            <Button
              variant="link"
              className="p-0 warehouse-activate"
              aria-label={`Activar ${warehouse.name}`}
              onClick={() => {
                onOpen({ type: 'warehouse-active', warehouse, active: true });
              }}
            >
              Activar
            </Button>
          )}
        </div>
      ),
    });
  }

  const actions =
    branch.is_active && can(PERMISSIONS.warehousesStore) ? (
      <Button
        variant="secondary"
        className="warehouse-new"
        icon={<IconPlus size={18} className="me-2" aria-hidden="true" />}
        onClick={() => {
          onOpen({ type: 'warehouse-new' });
        }}
      >
        Nuevo depósito
      </Button>
    ) : undefined;

  return (
    <Card title="Depósitos" actions={actions} flush>
      <DataTable
        columns={columns}
        rows={branch.warehouses}
        rowKey={(warehouse) => warehouse.id}
        empty="La sucursal todavía no tiene depósitos."
      />
    </Card>
  );
}

/** La página de no encontrado del detalle y la edición (un id inválido o el 404 de la API), dentro del layout. */
export function BranchNotFound() {
  return (
    <ErrorPage
      code="404"
      title="No encontramos esta sucursal"
      message="La sucursal no existe. Buscala en el listado."
      action={
        <Link to="/configuracion/sucursales" className="btn btn-primary">
          Volver a las sucursales
        </Link>
      }
    />
  );
}
