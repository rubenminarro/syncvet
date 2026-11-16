import type { ButtonHTMLAttributes, MouseEvent, ReactNode, Ref } from 'react';

type Variant = 'primary' | 'secondary' | 'danger' | 'link';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  loading?: boolean;
  block?: boolean;
  icon?: ReactNode;
  ref?: Ref<HTMLButtonElement>;
  /**
   * Inactivo (deshabilitado o esperando) sin perder el foco: aria-disabled en lugar de disabled, y el clic no hace nada. Un
   * botón con disabled suelta el foco (cae en body): en un diálogo, el teclado se queda sin lugar (revisión de la 1.2).
   */
  focusableWhenInactive?: boolean;
}

export function Button({
  variant = 'primary',
  loading = false,
  block = false,
  icon,
  type = 'button',
  className,
  disabled,
  focusableWhenInactive = false,
  onClick,
  children,
  ...rest
}: ButtonProps) {
  const inactive = disabled === true || loading;
  const soft = inactive && focusableWhenInactive;
  const classes = [
    'btn',
    `btn-${variant}`,
    block ? 'w-100' : '',
    soft ? 'disabled' : '',
    className ?? '',
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <button
      type={type}
      className={classes}
      disabled={inactive && !focusableWhenInactive}
      aria-disabled={soft ? true : undefined}
      onClick={(event: MouseEvent<HTMLButtonElement>) => {
        if (soft) {
          event.preventDefault();

          return;
        }

        onClick?.(event);
      }}
      {...rest}
    >
      {loading ? (
        <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />
      ) : (
        icon
      )}
      {children}
    </button>
  );
}
