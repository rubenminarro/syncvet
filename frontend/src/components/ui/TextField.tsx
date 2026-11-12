import { useId, type InputHTMLAttributes, type ReactNode, type Ref } from 'react';

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  error?: string | undefined;
  hint?: string;
  /** Un botón o ícono dentro del campo, a la derecha (el input-group-flat de Tabler): el de mostrar la contraseña. */
  addon?: ReactNode;
  ref?: Ref<HTMLInputElement>;
}

/** Campo de texto con su etiqueta y su error (el de Zod o el de la API, vía setError). */
export function TextField({
  label,
  error,
  hint,
  addon,
  id,
  className,
  ref,
  ...rest
}: TextFieldProps) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-error`;

  const input = (
    <input
      ref={ref}
      id={inputId}
      className={`form-control${error ? ' is-invalid' : ''} ${className ?? ''}`.trim()}
      aria-invalid={error ? true : undefined}
      aria-describedby={error ? errorId : undefined}
      {...rest}
    />
  );
  // Siempre fuera del grupo del botón (addon): el contorno de foco de Tabler (.input-group-flat:focus-within) rodea el
  // grupo entero y, con el mensaje adentro, lo encerraba también (B1 de la revisión de la 0.2b). Afuera, ya no es hermano
  // del input con .is-invalid: se muestra con d-block.
  const feedback = error ? (
    <div id={errorId} className="invalid-feedback d-block">
      {error}
    </div>
  ) : null;

  return (
    <div className="mb-3">
      <label className={`form-label${rest.required === true ? ' required' : ''}`} htmlFor={inputId}>
        {label}
      </label>
      {addon ? (
        <>
          <div className={`input-group input-group-flat${error ? ' is-invalid' : ''}`}>
            {input}
            <span className={`input-group-text${error ? ' border-danger' : ''}`}>{addon}</span>
          </div>
          {feedback}
        </>
      ) : (
        <>
          {input}
          {feedback}
        </>
      )}
      {!error && hint ? <small className="form-hint">{hint}</small> : null}
    </div>
  );
}
