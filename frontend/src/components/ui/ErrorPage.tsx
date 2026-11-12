import type { ReactNode } from 'react';

interface ErrorPageProps {
  code?: string;
  title: string;
  message: string;
  action?: ReactNode;
}

/** Página de error de Tabler: no encontrado, sin permiso y fallas al cargar. */
export function ErrorPage({ code, title, message, action }: ErrorPageProps) {
  return (
    <div className="empty">
      {code ? <div className="empty-header">{code}</div> : null}
      <p className="empty-title">{title}</p>
      <p className="empty-subtitle text-secondary">{message}</p>
      {action ? <div className="empty-action">{action}</div> : null}
    </div>
  );
}
