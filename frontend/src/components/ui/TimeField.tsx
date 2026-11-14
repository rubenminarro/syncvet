import type { ComponentProps } from 'react';
import { TextField } from './TextField';

type TimeFieldProps = Omit<ComponentProps<typeof TextField>, 'type'>;

/**
 * Una hora sin fecha con el selector nativo del navegador (`<input type="time">`, desde la 1.3b). Su valor es el texto
 * `HH:mm` (o '' vacío), la hora de pared de Asunción: se junta con la fecha de un `DateField` y se manda con
 * `toApiDateTime`, sin pasar por la zona de la PC.
 */
export function TimeField(props: TimeFieldProps) {
  return <TextField type="time" {...props} />;
}
