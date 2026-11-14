import { useId, type Ref, type SelectHTMLAttributes } from 'react';

export interface SelectOption {
  value: string;
  label: string;
}

interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label: string;
  options: readonly SelectOption[];
  /** La etiqueta solo para los lectores de pantalla (un filtro de listado, al lado de la búsqueda). */
  hideLabel?: boolean;
  /** El error del campo en un formulario (el de Zod o el de la API, vía setError). */
  error?: string | undefined;
  hint?: string;
  ref?: Ref<HTMLSelectElement>;
}

/**
 * Un selector con su etiqueta (el form-select de Tabler). En la 1.1, el filtro de tipo del listado de clientes; desde la
 * 1.3a, también un campo de formulario (la especie y la raza del paciente), con su error y su `ref` para `register`.
 */
export function SelectField({
  label,
  options,
  hideLabel = false,
  error,
  hint,
  id,
  className,
  ref,
  ...rest
}: SelectFieldProps) {
  const generatedId = useId();
  const selectId = id ?? generatedId;
  const errorId = `${selectId}-error`;
  let labelClass = 'form-label';

  if (hideLabel) {
    labelClass = 'visually-hidden';
  } else if (rest.required === true) {
    labelClass = 'form-label required';
  }

  return (
    <div className={className}>
      <label className={labelClass} htmlFor={selectId}>
        {label}
      </label>
      <select
        ref={ref}
        id={selectId}
        className={`form-select${error ? ' is-invalid' : ''}`}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        {...rest}
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {error ? (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      ) : null}
      {!error && hint ? <small className="form-hint">{hint}</small> : null}
    </div>
  );
}
