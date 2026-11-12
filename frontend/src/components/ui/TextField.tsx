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
  const feedback = error ? (
    <div id={errorId} className="invalid-feedback">
      {error}
    </div>
  ) : null;

  return (
    <div className="mb-3">
      <label className={`form-label${rest.required === true ? ' required' : ''}`} htmlFor={inputId}>
        {label}
      </label>
      {addon ? (
        <div className={`input-group input-group-flat has-validation${error ? ' is-invalid' : ''}`}>
          {input}
          <span className={`input-group-text${error ? ' border-danger' : ''}`}>{addon}</span>
          {feedback}
        </div>
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
