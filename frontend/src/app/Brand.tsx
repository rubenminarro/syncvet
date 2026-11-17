import { IconPaw } from '@tabler/icons-react';
import { useQuery } from '@tanstack/react-query';
import { BlobImage } from '../components/ui';
import { logoQueryOptions } from '../features/settings/api';

/** La marca SyncVet: en /ingresar y en la barra de una clínica sin logo (o mientras llega, o si no se pudo ver). */
function SyncVetMark() {
  return (
    <span className="navbar-brand navbar-brand-autodark d-inline-flex align-items-center gap-2">
      <IconPaw size={28} stroke={1.75} aria-hidden="true" />
      <span className="fw-bold">SyncVet</span>
    </span>
  );
}

interface BrandProps {
  /** La clínica de la sesión (`me.clinic`): sin ella (fuera de la sesión), la marca SyncVet. */
  clinic?: { name: string; has_logo: boolean };
  /** El usuario puede ver el logo (`system-settings.show-logo`, los tres roles). */
  canShowLogo?: boolean;
}

/**
 * La marca de la barra (2.5, pregunta 9 del diseño de la F2): el logo de la clínica, con 32 px de alto y su nombre en `alt`,
 * si `me.clinic.has_logo`; si no, mientras se descarga o con un error (también el 404 CLINIC_LOGO_NOT_FOUND), la marca SyncVet:
 * nunca una imagen rota. La misma clave que la configuración (`['settings', 'logo']`): subir o quitar el logo lo cambia acá
 * sin recargar.
 */
export function Brand({ clinic, canShowLogo = false }: BrandProps) {
  const enabled = clinic !== undefined && clinic.has_logo && canShowLogo;
  const logo = useQuery({ ...logoQueryOptions(), enabled });

  if (!enabled || logo.data === undefined || logo.isError) {
    return <SyncVetMark />;
  }

  return (
    <span className="navbar-brand d-inline-flex align-items-center">
      <BlobImage blob={logo.data} alt={clinic.name} className="brand-logo" />
    </span>
  );
}
