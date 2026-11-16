import { IconSearch } from '@tabler/icons-react';
import { useId, type InputHTMLAttributes } from 'react';

interface SearchFieldProps extends Omit<
  InputHTMLAttributes<HTMLInputElement>,
  'type' | 'value' | 'onChange'
> {
  label: string;
  value: string;
  onChange: (value: string) => void;
  /** Un pedido en curso con lo escrito: el indicador de carga chico, a la derecha. */
  busy?: boolean;
}

/** El campo de búsqueda de un listado (el input-icon de Tabler): la lupa a la izquierda y la carga a la derecha. */
export function SearchField({
  label,
  value,
  onChange,
  busy = false,
  id,
  className,
  ...rest
}: SearchFieldProps) {
  const generatedId = useId();
  const inputId = id ?? generatedId;

  return (
    <div className={className}>
      <label className="visually-hidden" htmlFor={inputId}>
        {label}
      </label>
      <div className="input-icon">
        <span className="input-icon-addon">
          <IconSearch size={18} aria-hidden="true" />
        </span>
        <input
          id={inputId}
          type="search"
          className="form-control"
          autoComplete="off"
          value={value}
          onChange={(event) => {
            onChange(event.target.value);
          }}
          {...rest}
        />
        {busy ? (
          <span className="input-icon-addon search-busy" aria-hidden="true">
            <span className="spinner-border spinner-border-sm text-secondary" />
          </span>
        ) : null}
      </div>
      {/* Siempre en el DOM: un lector de pantalla anuncia el cambio de texto de una región que ya existía, no la que aparece. */}
      <span role="status" className="visually-hidden search-status">
        {busy ? 'Buscando…' : ''}
      </span>
    </div>
  );
}
