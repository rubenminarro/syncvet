import type { ComponentProps } from 'react';
import { TextField } from './TextField';

type DateFieldProps = Omit<ComponentProps<typeof TextField>, 'type'>;

/**
 * Una fecha sin hora con el selector nativo del navegador (`<input type="date">`, desde la 1.3a). Su valor es siempre el
 * texto `yyyy-MM-dd` (o '' vacío): se manda tal cual, sin pasar por `Date` (que lo tomaría como medianoche UTC). `max` limita
 * el selector (la fecha de hoy en Asunción, `todayInAppZone()`); el control de verdad es el de Zod, con el mismo límite.
 */
export function DateField(props: DateFieldProps) {
  return <TextField type="date" {...props} />;
}
