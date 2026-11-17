import { IconMenu2, IconX } from '@tabler/icons-react';
import { Link } from '@tanstack/react-router';
import { useId, useRef, useState } from 'react';
import { useMediaQuery } from '../lib/hooks/use-media-query';
import type { MenuItem } from './menu';

/**
 * Por debajo de este ancho el menú se pliega: es el `lg` de Tabler (992 px). Con los ítems del administrador la barra mide
 * unos 820 px, así que a 768 px tampoco entra (desvío 10 de la 2.4).
 */
export const COLLAPSED_MENU_QUERY = '(max-width: 991.98px)';

/**
 * El menú principal (2.5, desvío 10 de la 2.4): en pantallas anchas, la barra horizontal de siempre; en las chicas, plegado
 * detrás del botón "Menú", sin el JS de Tabler. El botón dice si está abierto (`aria-expanded`) y qué abre
 * (`aria-controls`); se abre con Enter o Espacio (es un `<button>`), Escape lo cierra con el foco de vuelta en el botón, y
 * elegir un ítem lo cierra.
 */
export function MainMenu({ items }: { items: readonly MenuItem[] }) {
  const collapsed = useMediaQuery(COLLAPSED_MENU_QUERY);
  const [open, setOpen] = useState(false);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const listId = useId();
  const expanded = collapsed && open;

  return (
    <nav
      aria-label="Menú principal"
      className={`main-menu${collapsed ? ' main-menu-collapsed' : ''}`}
      onKeyDown={(event) => {
        if (expanded && event.key === 'Escape') {
          event.preventDefault();
          setOpen(false);
          buttonRef.current?.focus();
        }
      }}
    >
      {collapsed ? (
        <button
          ref={buttonRef}
          type="button"
          className="btn btn-ghost-secondary main-menu-toggle"
          aria-expanded={expanded}
          aria-controls={listId}
          onClick={() => {
            setOpen((current) => !current);
          }}
        >
          {expanded ? (
            <IconX size={20} className="me-2" aria-hidden="true" />
          ) : (
            <IconMenu2 size={20} className="me-2" aria-hidden="true" />
          )}
          Menú
        </button>
      ) : null}
      <ul
        id={listId}
        className={`navbar-nav main-menu-list ${collapsed ? 'flex-column' : 'flex-row'}`}
        hidden={collapsed && !open}
      >
        {items.map((item) => (
          <li key={item.to} className="nav-item">
            <Link
              to={item.to}
              className="nav-link"
              activeProps={{ className: 'active' }}
              activeOptions={{ exact: item.to === '/' }}
              onClick={() => {
                setOpen(false);
              }}
            >
              <span className="nav-link-icon">
                <item.icon size={20} stroke={1.5} aria-hidden="true" />
              </span>
              <span className="nav-link-title">{item.label}</span>
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  );
}
