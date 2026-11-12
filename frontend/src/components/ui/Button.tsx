import type { ButtonHTMLAttributes, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'danger' | 'link';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  loading?: boolean;
  block?: boolean;
  icon?: ReactNode;
}

export function Button({
  variant = 'primary',
  loading = false,
  block = false,
  icon,
  type = 'button',
  className,
  disabled,
  children,
  ...rest
}: ButtonProps) {
  const classes = ['btn', `btn-${variant}`, block ? 'w-100' : '', className ?? '']
    .filter(Boolean)
    .join(' ');

  return (
    <button type={type} className={classes} disabled={disabled === true || loading} {...rest}>
      {loading ? (
        <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />
      ) : (
        icon
      )}
      {children}
    </button>
  );
}
