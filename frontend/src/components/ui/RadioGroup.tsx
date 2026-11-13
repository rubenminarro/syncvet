import { useId, type InputHTMLAttributes, type Ref } from 'react';
import type { SelectOption } from './SelectField';

interface RadioGroupProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'value'> {
  label: string;
  options: readonly SelectOption[];
  error?: string | undefined;
  /** El valor marcado, si el grupo es controlado; con React Hook Form alcanza con register(). */
  value?: string;
  ref?: Ref<HTMLInputElement>;
}

/**
 * Un grupo de opciones (form-selectgroup de Tabler, en línea): el tipo de cliente. Con register() de React Hook Form, la
 * misma `ref` y el mismo `name` van a cada opción (RHF las reconoce como un grupo de radios).
 */
export function RadioGroup({
  label,
  options,
  error,
  value,
  ref,
  className,
  ...rest
}: RadioGroupProps) {
  const id = useId();
  const errorId = `${id}-error`;

  return (
    <fieldset className={`mb-3 ${className ?? ''}`.trim()}>
      <legend className="form-label">{label}</legend>
      <div
        className={`form-selectgroup${error ? ' is-invalid' : ''}`}
        aria-describedby={error ? errorId : undefined}
      >
        {options.map((option) => (
          <label key={option.value} className="form-selectgroup-item">
            <input
              ref={ref}
              type="radio"
              className="form-selectgroup-input"
              value={option.value}
              {...(value === undefined ? {} : { checked: value === option.value })}
              {...rest}
            />
            <span className="form-selectgroup-label">{option.label}</span>
          </label>
        ))}
      </div>
      {error ? (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      ) : null}
    </fieldset>
  );
}
