import { useState } from 'react';
import { Card } from '../components/ui';
import { formatDate } from '../lib/format/dates';

export function HomePage() {
  const [today] = useState(() => formatDate(Date.now()));

  return (
    <>
      <div className="page-header mb-3">
        <h2 className="page-title">Inicio</h2>
        <div className="text-secondary">Hoy es {today}</div>
      </div>
      <Card title="Bienvenido a SyncVet">
        <p className="mb-0 text-secondary">
          Acá vas a ver la sucursal actual y los accesos a cada módulo. El menú según tus permisos y
          el cambio de sucursal llegan en la próxima entrega.
        </p>
      </Card>
    </>
  );
}
