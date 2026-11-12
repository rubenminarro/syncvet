import { Card } from '../components/ui';

/** Provisoria (decisión del usuario en la 0.2b): la configuración de la clínica llega en la F2. Solo el administrador. */
export function ConfigurationPage() {
  return (
    <>
      <div className="page-header mb-3">
        <h2 className="page-title">Configuración</h2>
      </div>
      <Card title="En construcción">
        <p className="mb-0 text-secondary">
          Acá vas a poder configurar los datos de la clínica, las sucursales y los usuarios. Llega
          en una próxima entrega.
        </p>
      </Card>
    </>
  );
}
