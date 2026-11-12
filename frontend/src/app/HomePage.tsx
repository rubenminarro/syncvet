import { IconSettings } from '@tabler/icons-react';
import { useQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { useState } from 'react';
import { Card } from '../components/ui';
import { meQueryOptions } from '../features/auth/api';
import { RequirePermission } from '../features/auth/RequirePermission';
import { PERMISSIONS } from '../lib/auth/permissions';
import { formatDate } from '../lib/format/dates';

export function HomePage() {
  const [today] = useState(() => formatDate(Date.now()));
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });
  const branch = me?.current_branch;

  return (
    <>
      <div className="page-header mb-3">
        <h2 className="page-title">Inicio</h2>
        <div className="text-secondary">Hoy es {today}</div>
      </div>
      <Card title="Bienvenido a SyncVet">
        <p className="text-secondary home-branch">
          {branch
            ? `Estás trabajando en ${branch.name}.`
            : 'Todavía no elegiste tu sucursal actual.'}
        </p>
        <p className="mb-0 text-secondary">
          Los accesos a cada módulo aparecen acá y en el menú a medida que estén listos, según tus
          permisos.
        </p>
        <RequirePermission permission={PERMISSIONS.settingsUpdate}>
          <Link to="/configuracion" className="btn btn-outline-primary mt-3 home-settings">
            <IconSettings size={18} className="me-2" aria-hidden="true" />
            Configuración
          </Link>
        </RequirePermission>
      </Card>
    </>
  );
}
