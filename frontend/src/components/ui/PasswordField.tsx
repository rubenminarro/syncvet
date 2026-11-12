import { IconEye, IconEyeOff } from '@tabler/icons-react';
import { useState, type ComponentProps } from 'react';
import { TextField } from './TextField';

type PasswordFieldProps = Omit<ComponentProps<typeof TextField>, 'type' | 'addon'>;

/** Campo de contraseña con el botón para mostrarla u ocultarla (sugerencia del usuario, R-060). */
export function PasswordField(props: PasswordFieldProps) {
  const [visible, setVisible] = useState(false);
  const label = visible ? 'Ocultar la contraseña' : 'Mostrar la contraseña';

  return (
    <TextField
      {...props}
      type={visible ? 'text' : 'password'}
      addon={
        <button
          type="button"
          className="btn btn-link link-secondary p-0 border-0"
          aria-label={label}
          aria-pressed={visible}
          title={label}
          onClick={() => {
            setVisible((current) => !current);
          }}
        >
          {visible ? (
            <IconEyeOff size={18} stroke={1.5} aria-hidden="true" />
          ) : (
            <IconEye size={18} stroke={1.5} aria-hidden="true" />
          )}
        </button>
      }
    />
  );
}
