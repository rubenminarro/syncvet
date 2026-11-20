/**
 * Las clases de un elemento, separadas por un espacio y sin las vacías (`false`, `null`, `undefined` o `''`). Reemplaza el
 * `` `a ${b ?? ''}`.trim() `` de los componentes: `trim()` queda prohibido por el lint, para que lo que va a la API se recorte
 * con `trimLikeApi` (deuda 28).
 */
export function classNames(...parts: (string | false | null | undefined)[]): string {
  return parts.filter((part): part is string => typeof part === 'string' && part !== '').join(' ');
}
