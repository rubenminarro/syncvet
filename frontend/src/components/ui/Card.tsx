import type { ReactNode } from 'react';

interface CardProps {
  title?: string;
  actions?: ReactNode;
  children: ReactNode;
  /** Sin el relleno del cuerpo (para una tabla que ocupa toda la tarjeta). */
  flush?: boolean;
}

export function Card({ title, actions, children, flush = false }: CardProps) {
  return (
    <div className="card">
      {title ? (
        <div className="card-header">
          <h3 className="card-title">{title}</h3>
          {actions ? <div className="card-actions">{actions}</div> : null}
        </div>
      ) : null}
      {flush ? children : <div className="card-body">{children}</div>}
    </div>
  );
}
