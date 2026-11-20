import { useId, type InputHTMLAttributes, type Ref } from 'react';
import { classNames } from '../../lib/class-names';

interface FileFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'value'> {
  label: string;
  error?: string | undefined;
  hint?: string;
  ref?: Ref<HTMLInputElement>;
}

/**
 * Campo de archivo (1.4) con su etiqueta, su ayuda y su error (el del navegador o el de la API, `errors.photo`). No es
 * controlado: quien lo usa lee `event.target.files` y lo vacía con la ref (`value = ''`).
 */
export function FileField({ label, error, hint, id, className, ref, ...rest }: FileFieldProps) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-error`;
  const hintId = `${inputId}-hint`;

  return (
    <div className="mb-3">
      <label className="form-label" htmlFor={inputId}>
        {label}
      </label>
      <input
        ref={ref}
        id={inputId}
        type="file"
        className={classNames('form-control', error && 'is-invalid', className)}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : hint ? hintId : undefined}
        {...rest}
      />
      {error ? (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      ) : null}
      {!error && hint ? (
        <small id={hintId} className="form-hint">
          {hint}
        </small>
      ) : null}
    </div>
  );
}
