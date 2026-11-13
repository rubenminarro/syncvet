import { useId, type SelectHTMLAttributes } from 'react';

export interface SelectOption {
  value: string;
  label: string;
}

interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label: string;
  options: readonly SelectOption[];
  /** La etiqueta solo para los lectores de pantalla (un filtro de listado, al lado de la búsqueda). */
  hideLabel?: boolean;
}

/**
 * Un selector con su etiqueta (el form-select de Tabler). En la 1.1, el filtro de tipo del listado de clientes; la 1.2 le
 * suma el error de un formulario.
 */
export function SelectField({
  label,
  options,
  hideLabel = false,
  id,
  className,
  ...rest
}: SelectFieldProps) {
  const generatedId = useId();
  const selectId = id ?? generatedId;

  return (
    <div className={className}>
      <label className={hideLabel ? 'visually-hidden' : 'form-label'} htmlFor={selectId}>
        {label}
      </label>
      <select id={selectId} className="form-select" {...rest}>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </div>
  );
}
