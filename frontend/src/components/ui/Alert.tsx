import type { ReactNode } from 'react';

type Tone = 'danger' | 'warning' | 'info' | 'success';

interface AlertProps {
  tone?: Tone;
  title?: string;
  children?: ReactNode;
}

export function Alert({ tone = 'danger', title, children }: AlertProps) {
  return (
    <div className={`alert alert-${tone}`} role={tone === 'danger' ? 'alert' : 'status'}>
      {title ? <h4 className="alert-title">{title}</h4> : null}
      {children ? <div className="text-secondary">{children}</div> : null}
    </div>
  );
}
