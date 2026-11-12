import { IconHome, IconUserCircle } from '@tabler/icons-react';
import { Link, Outlet } from '@tanstack/react-router';
import { Brand } from './Brand';

/**
 * Layout con sesión: barra superior y menú. En la F0.1 no hay sesión real: el usuario, la sucursal actual y el menú por
 * permisos llegan en la F0.2.
 */
export function SessionLayout() {
  return (
    <div className="page">
      <header className="navbar navbar-expand-md d-print-none">
        <div className="container-xl">
          <Link to="/" className="text-reset text-decoration-none">
            <Brand />
          </Link>
          <div className="navbar-nav flex-row ms-auto">
            <span className="nav-link d-flex align-items-center gap-2 text-secondary">
              <IconUserCircle size={24} stroke={1.5} aria-hidden="true" />
              <span className="d-none d-sm-inline">Sin sesión (llega en la F0.2)</span>
            </span>
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
            <Outlet />
          </div>
        </div>
      </div>
    </div>
  );
}
