import { useId, type InputHTMLAttributes, type Ref } from 'react';

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  error?: string | undefined;
  hint?: string;
  ref?: Ref<HTMLInputElement>;
}

/** Campo de texto con su etiqueta y su error (el de Zod o el de la API, vía setError). */
export function TextField({ label, error, hint, id, className, ref, ...rest }: TextFieldProps) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-error`;

  return (
    <div className="mb-3">
      <label className={`form-label${rest.required === true ? ' required' : ''}`} htmlFor={inputId}>
        {label}
      </label>
      <input
        ref={ref}
        id={inputId}
        className={`form-control${error ? ' is-invalid' : ''} ${className ?? ''}`.trim()}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        {...rest}
      />
      {error ? (
        <div id={errorId} className="invalid-feedback">
          {error}
        </div>
      ) : hint ? (
        <small className="form-hint">{hint}</small>
      ) : null}
    </div>
  );
}
