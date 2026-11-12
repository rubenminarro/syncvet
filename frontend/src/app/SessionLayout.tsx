import { IconBuildingStore, IconHome, IconLogout, IconUserCircle } from '@tabler/icons-react';
import { useQuery } from '@tanstack/react-query';
import { Link, Outlet, useNavigate, useRouter } from '@tanstack/react-router';
import { useEffect } from 'react';
import { Alert, Button, Loading } from '../components/ui';
import { meQueryOptions } from '../features/auth/api';
import { useSessionManager, useSessionState } from '../lib/auth/use-session';
import { Brand } from './Brand';

function warningText(minutes: number): string {
  return `Tu sesión vence en ${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}. Guardá los cambios pendientes.`;
}

/**
 * Layout con sesión: barra con el usuario, la clínica, la sucursal actual (solo se muestra; el selector es de la 0.2b) y
 * "Salir"; el aviso previo al vencimiento arriba del contenido. Si la sesión se cierra (salir, un 401, el vencimiento u
 * otra pestaña), va a /ingresar.
 */
export function SessionLayout() {
  const manager = useSessionManager();
  const { session, warningMinutes, notice } = useSessionState();
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: session !== null });
  const navigate = useNavigate();
  const router = useRouter();

  useEffect(() => {
    if (session !== null) {
      return;
    }

    // Un cierre que no pidió el usuario vuelve a la misma página después de ingresar; "Salir", no. La ruta se lee al
    // cerrarse la sesión (no es una dependencia): con la navegación ya en curso, cambia y no tiene que volver a navegar.
    const href = router.state.location.href;
    const volver = notice !== null && href !== '/' ? { volver: href } : {};
    void navigate({ to: '/ingresar', search: volver, replace: true });
  }, [session, notice, navigate, router]);

  if (session === null || me === undefined) {
    return <Loading />;
  }

  return (
    <div className="page">
      <header className="navbar navbar-expand-md d-print-none">
        <div className="container-xl">
          <Link to="/" className="text-reset text-decoration-none">
            <Brand />
          </Link>
          <div className="navbar-nav flex-row ms-auto align-items-center gap-3">
            <div className="d-none d-md-flex align-items-center gap-2 text-secondary">
              <IconBuildingStore size={20} stroke={1.5} aria-hidden="true" />
              <div className="lh-sm">
                <div className="session-clinic">{me.clinic.name}</div>
                <div className="small session-branch">
                  {me.current_branch?.name ?? 'Sin sucursal actual'}
                </div>
              </div>
            </div>
            <div className="d-flex align-items-center gap-2">
              <IconUserCircle size={24} stroke={1.5} aria-hidden="true" />
              <div className="lh-sm d-none d-sm-block">
                <div className="session-user">{me.name}</div>
                <div className="small text-secondary">{me.email}</div>
              </div>
            </div>
            <Button
              variant="secondary"
              icon={<IconLogout size={18} className="me-2" aria-hidden="true" />}
              onClick={() => {
                void manager.logout();
              }}
            >
              Salir
            </Button>
          </div>
        </div>
      </header>
      <header className="navbar-expand-md">
        <div className="navbar">
          <div className="container-xl">
            <ul className="navbar-nav flex-row">
              <li className="nav-item">
                <Link
                  to="/"
                  className="nav-link"
                  activeProps={{ className: 'active' }}
                  activeOptions={{ exact: true }}
                >
                  <span className="nav-link-icon">
                    <IconHome size={20} stroke={1.5} aria-hidden="true" />
                  </span>
                  <span className="nav-link-title">Inicio</span>
                </Link>
              </li>
            </ul>
          </div>
        </div>
      </header>
      <div className="page-wrapper">
        <div className="page-body">
          <div className="container-xl">
            {warningMinutes !== null ? (
              <Alert tone="warning" title={warningText(warningMinutes)} />
            ) : null}
            <Outlet />
          </div>
        </div>
      </div>
    </div>
  );
}
