import { IconLogout, IconUserCircle } from '@tabler/icons-react';
import { useQuery } from '@tanstack/react-query';
import { Link, Outlet, useNavigate, useRouter } from '@tanstack/react-router';
import { useEffect } from 'react';
import { Alert, Button, Loading } from '../components/ui';
import { meQueryOptions } from '../features/auth/api';
import { BranchSelector } from '../features/auth/BranchSelector';
import type { Me } from '../features/auth/types';
import { can } from '../lib/auth/permissions';
import { useSessionManager, useSessionState } from '../lib/auth/use-session';
import { Brand } from './Brand';
import { MENU, visibleMenu } from './menu';

function warningText(minutes: number): string {
  return `Tu sesión vence en ${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}. Guardá los cambios pendientes.`;
}

/** Sin sucursal actual: elegir una (o pedirlas, si no tiene ninguna asignada). */
function branchNotice(me: Me): string | null {
  if (me.current_branch !== null) {
    return null;
  }

  return me.branches.length > 0
    ? 'Elegí tu sucursal actual en la barra de arriba para empezar a trabajar.'
    : 'No tenés sucursales asignadas: pedíselas al administrador de la clínica.';
}

/**
 * Layout con sesión: barra con la clínica, el selector de la sucursal actual, el usuario (lleva a /perfil) y "Salir"; el
 * menú según los permisos; el aviso previo al vencimiento y el de elegir sucursal arriba del contenido. Si la sesión se cierra (salir, un 401, el vencimiento u
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

  const chooseBranch = branchNotice(me);

  return (
    <div className="page">
      <header className="navbar navbar-expand-md d-print-none">
        <div className="container-xl">
          <Link to="/" className="text-reset text-decoration-none">
            <Brand />
          </Link>
          <div className="navbar-nav flex-row ms-auto align-items-center gap-3">
            <BranchSelector me={me} />
            <Link
              to="/perfil"
              className="d-flex align-items-center gap-2 text-reset text-decoration-none"
              title="Tu perfil"
            >
              <IconUserCircle size={24} stroke={1.5} aria-hidden="true" />
              <div className="lh-sm d-none d-sm-block">
                <div className="session-user">{me.name}</div>
                <div className="small text-secondary">{me.email}</div>
              </div>
            </Link>
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
              {visibleMenu(MENU, (permission) => can(me, permission)).map((item) => (
                <li key={item.to} className="nav-item">
                  <Link
                    to={item.to}
                    className="nav-link"
                    activeProps={{ className: 'active' }}
                    activeOptions={{ exact: item.to === '/' }}
                  >
                    <span className="nav-link-icon">
                      <item.icon size={20} stroke={1.5} aria-hidden="true" />
                    </span>
                    <span className="nav-link-title">{item.label}</span>
                  </Link>
                </li>
              ))}
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
            {chooseBranch !== null ? <Alert tone="info" title={chooseBranch} /> : null}
            <Outlet />
          </div>
        </div>
      </div>
    </div>
  );
}
