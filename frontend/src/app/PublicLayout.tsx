import { Outlet } from '@tanstack/react-router';
import { Brand } from './Brand';

/** Layout público (ingresar): la tarjeta centrada de Tabler. */
export function PublicLayout() {
  return (
    <div className="page page-center">
      <div className="container container-tight py-4">
        <div className="text-center mb-4">
          <Brand />
        </div>
        <Outlet />
      </div>
    </div>
  );
}
