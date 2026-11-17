import {
  IconBuildingHospital,
  IconBuildingStore,
  IconUsersGroup,
  type Icon,
} from '@tabler/icons-react';
import { Link } from '@tanstack/react-router';
import { Card } from '../components/ui';
import { useCan } from '../features/auth/permissions';
import { PERMISSIONS } from '../lib/auth/permissions';

interface Section {
  to: '/configuracion/sucursales' | '/configuracion/usuarios' | '/configuracion/clinica';
  title: string;
  description: string;
  icon: Icon;
  permission: string;
}

/**
 * Las secciones de la administración (pregunta 2 del diseño de la F2): cada una llega en su entrega (Sucursales en la 2.1;
 * Usuarios en la 2.2a; la Clínica en la 2.5; la Auditoría, después).
 */
const SECTIONS: readonly Section[] = [
  {
    to: '/configuracion/sucursales',
    title: 'Sucursales',
    description: 'Las sucursales de la clínica, con su establecimiento de SIFEN y sus depósitos.',
    icon: IconBuildingStore,
    permission: PERMISSIONS.branchesShow,
  },
  {
    to: '/configuracion/usuarios',
    title: 'Usuarios',
    description: 'Quién ingresa al sistema, con sus roles y sus sucursales.',
    icon: IconUsersGroup,
    permission: PERMISSIONS.usersIndex,
  },
  {
    to: '/configuracion/clinica',
    title: 'Clínica',
    description: 'El nombre, la razón social, el contacto, el pie de los documentos y el logo.',
    icon: IconBuildingHospital,
    permission: PERMISSIONS.settingsUpdate,
  },
];

/** /configuracion: la página de entrada, con una tarjeta por sección; solo las que el usuario puede ver. */
export function ConfigurationPage() {
  const can = useCan();
  const sections = SECTIONS.filter((section) => can(section.permission));

  return (
    <>
      <div className="page-header mb-3">
        <h2 className="page-title">Configuración</h2>
      </div>
      <div className="row row-cards configuration-sections">
        {sections.map((section) => (
          <div key={section.to} className="col-sm-6 col-lg-4">
            <Card>
              <Link to={section.to} className="d-flex gap-3 text-reset text-decoration-none">
                <span className="avatar bg-primary-lt" aria-hidden="true">
                  <section.icon size={24} stroke={1.5} />
                </span>
                <span>
                  <span className="d-block h3 mb-1 text-primary">{section.title}</span>
                  <span className="text-secondary">{section.description}</span>
                </span>
              </Link>
            </Card>
          </div>
        ))}
      </div>
    </>
  );
}
