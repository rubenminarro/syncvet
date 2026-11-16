import { useId, type Ref } from 'react';

export interface CheckboxOption<V extends string | number> {
  value: V;
  label: string;
}

interface CheckboxGroupFieldProps<V extends string | number> {
  /** La leyenda del grupo ("Roles", "Sucursales"). */
  label: string;
  options: readonly CheckboxOption<V>[];
  /** Los valores marcados, en el orden de `options` al cambiar. */
  value: readonly V[];
  onChange: (next: V[]) => void;
  onBlur?: () => void;
  /** El error del grupo (el de Zod o el de la API, vía setError). */
  error?: string | undefined;
  hint?: string;
  required?: boolean;
  disabled?: boolean;
  /** La ref de React Hook Form (`field.ref` de un Controller): el foco de un error va a la primera casilla. */
  ref?: Ref<HTMLInputElement>;
  /** Para los tests y los estilos: una clase en el fieldset. */
  className?: string;
}

/**
 * Un grupo de casillas con su leyenda y su error (2.2a: los roles y las sucursales del usuario), controlado: se usa con el
 * `Controller` de React Hook Form, con el valor como lista. Un `fieldset` con `legend` (el nombre del grupo para los
 * lectores de pantalla) y el error asociado a cada casilla con `aria-describedby`.
 */
export function CheckboxGroupField<V extends string | number>({
  label,
  options,
  value,
  onChange,
  onBlur,
  error,
  hint,
  required = false,
  disabled = false,
  ref,
  className,
}: CheckboxGroupFieldProps<V>) {
  const groupId = useId();
  const errorId = `${groupId}-error`;
  const hintId = `${groupId}-hint`;
  let describedBy: string | undefined;

  if (error) {
    describedBy = errorId;
  } else if (hint) {
    describedBy = hintId;
  }

  const toggle = (option: V, checked: boolean) => {
    const next = new Set(value);

    if (checked) {
      next.add(option);
    } else {
      next.delete(option);
    }

    onChange(options.map((item) => item.value).filter((item) => next.has(item)));
  };

  return (
    <fieldset className={`mb-3${className ? ` ${className}` : ''}`}>
      <legend className={`form-label${required ? ' required' : ''}`}>{label}</legend>
      {options.map((option, index) => {
        const inputId = `${groupId}-${String(index)}`;

        return (
          <label key={String(option.value)} className="form-check mb-1" htmlFor={inputId}>
            <input
              ref={index === 0 ? ref : undefined}
              id={inputId}
              type="checkbox"
              className={`form-check-input${error ? ' is-invalid' : ''}`}
              checked={value.includes(option.value)}
              disabled={disabled}
              aria-invalid={error ? true : undefined}
              aria-describedby={describedBy}
              onChange={(event) => {
                toggle(option.value, event.target.checked);
              }}
              onBlur={onBlur}
            />
            <span className="form-check-label">{option.label}</span>
          </label>
        );
      })}
      {error ? (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      ) : hint ? (
        <small id={hintId} className="form-hint">
          {hint}
        </small>
      ) : null}
    </fieldset>
  );
}
