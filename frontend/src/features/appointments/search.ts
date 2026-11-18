import { z } from 'zod';
import { isAgendaDay } from './dates';

/**
 * La búsqueda de /agenda en la URL (diseño de la F3, "URL en castellano"):
 * `?vista=dia&fecha=2026-11-17&agenda=7&veterinario=4&cancelados=1&todos=1`. Sin un valor, no va: el día de hoy en Asunción,
 * todas las agendas que se ven, sin los cancelados y, para el veterinario, su vista por defecto (pregunta 3). `fecha` se lee
 * como texto (`TEXT_SEARCH_PARAMS`): `2026-11-17` no es un número, pero el router lo leería como JSON si pareciera uno.
 * `vista`: en la 3.2, solo `dia` (`semana`, desde la 3.3); otra se descarta.
 */
export interface AgendaSearch {
  vista?: 'dia' | undefined;
  fecha?: string | undefined;
  agenda?: number | undefined;
  veterinario?: number | undefined;
  cancelados?: 1 | undefined;
  todos?: 1 | undefined;
}

const vistaSchema = z.literal('dia');
const fechaSchema = z.string().refine(isAgendaDay);
const idSchema = z
  .union([z.number(), z.string().regex(/^\d+$/)])
  .transform(Number)
  .pipe(z.number().int().min(1).max(Number.MAX_SAFE_INTEGER));
const flagSchema = z
  .union([z.literal(1), z.literal('1'), z.literal(true)])
  .transform(() => 1 as const);

/**
 * `validateSearch` de /agenda: un valor inválido (una fecha mal formada o que no existe, una vista desconocida, un id que no
 * es un entero positivo) se descarta. Las seis claves van siempre, con `undefined` si el valor se descarta (el router mezcla
 * lo devuelto sobre la búsqueda cruda).
 */
export function validateAgendaSearch(raw: Record<string, unknown>): AgendaSearch {
  const vista = vistaSchema.safeParse(raw.vista);
  const fecha = fechaSchema.safeParse(raw.fecha);
  const agenda = idSchema.safeParse(raw.agenda);
  const veterinario = idSchema.safeParse(raw.veterinario);
  const cancelados = flagSchema.safeParse(raw.cancelados);
  const todos = flagSchema.safeParse(raw.todos);

  return {
    vista: vista.success ? vista.data : undefined,
    fecha: fecha.success ? fecha.data : undefined,
    agenda: agenda.success ? agenda.data : undefined,
    veterinario: veterinario.success ? veterinario.data : undefined,
    cancelados: cancelados.success ? cancelados.data : undefined,
    todos: todos.success ? todos.data : undefined,
  };
}
