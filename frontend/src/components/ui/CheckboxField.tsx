import { useId, type InputHTMLAttributes, type Ref } from 'react';

interface CheckboxFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label: string;
  error?: string | undefined;
  hint?: string;
  ref?: Ref<HTMLInputElement>;
}

/**
 * Una casilla con su etiqueta (form-check de Tabler) y su error. La pista va fuera de la etiqueta (asociada con
 * aria-describedby), para que el nombre de la casilla sea solo la etiqueta.
 */
export function CheckboxField({ label, error, hint, id, ref, ...rest }: CheckboxFieldProps) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-error`;
  const hintId = `${inputId}-hint`;
  let describedBy: string | undefined;

  if (error) {
    describedBy = errorId;
  } else if (hint) {
    describedBy = hintId;
  }

  return (
    <div className="mb-3">
      <label className="form-check mb-1" htmlFor={inputId}>
        <input
          ref={ref}
          id={inputId}
          type="checkbox"
          className={`form-check-input${error ? ' is-invalid' : ''}`}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          {...rest}
        />
        <span className="form-check-label">{label}</span>
      </label>
      {error ? (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      ) : hint ? (
        <small id={hintId} className="form-hint">
          {hint}
        </small>
      ) : null}
    </div>
  );
}
