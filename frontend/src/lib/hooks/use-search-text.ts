import { useRouter } from '@tanstack/react-router';
import { useEffect, useEffectEvent, useState } from 'react';
import { normalizeSearchText } from '../search-text';
import { useDebouncedValue } from './use-debounced-value';

/** La espera al escribir antes de buscar (diseño de la F1, "Espera al escribir"). */
export const SEARCH_DEBOUNCE_MS = 300;

/**
 * El campo de búsqueda de un listado (frontend/CLAUDE.md §5, Listados; el modelo es /clientes de la 1.1): el texto es local y
 * `apply` recibe lo escrito, sin los espacios de los extremos, 300 ms después de la última tecla, solo si es otro que el de
 * la URL (`urlValue`). Quien lo usa cambia la URL con `replace: true` y vuelve a la página 1.
 *
 * Si la URL cambia, el campo la toma solo si el cambio vino de afuera ("Atrás", un enlace): no si es lo que el campo mandó y
 * todavía no llegó, ni si coincide con lo escrito sin los espacios de los extremos (así no se pierden el espacio de "Laura "
 * ni la tecla escrita mientras la URL cambiaba).
 *
 * Si sale una navegación a otra página (un clic en un resultado) mientras corre la espera, la espera se cancela: `apply` no
 * se llama y la URL no cambia (deuda 23). Sin esto, el cambio de la búsqueda salía mientras el router cargaba el detalle y lo
 * pisaba, de vuelta en el listado. Al desmontar, `useDebouncedValue` ya cancela su temporizador.
 */
export function useSearchText(
  urlValue: string | undefined,
  apply: (next: string) => void,
): readonly [string, (text: string) => void] {
  const router = useRouter();
  // La página del listado: no cambia mientras el hook vive (otra página es otro componente).
  const [pathname] = useState(() => router.latestLocation.pathname);
  const [text, setText] = useState(urlValue ?? '');
  const [urlText, setUrlText] = useState(urlValue);
  const debounced = useDebouncedValue(text, SEARCH_DEBOUNCE_MS);
  // El último valor con espera que ya tuvo su cambio de URL (o el del principio): si `debounced` es otro, el campo mandó
  // algo a la URL que todavía no llegó.
  const [answered, setAnswered] = useState(debounced);

  if (urlValue !== urlText) {
    const url = urlValue ?? '';
    const fromField =
      (debounced !== answered && normalizeSearchText(debounced) === url) ||
      normalizeSearchText(text) === url;
    setUrlText(urlValue);
    setAnswered(debounced);

    if (!fromField) {
      setText(url);
    }
  }

  const applySearch = useEffectEvent((value: string) => {
    // latestLocation cambia apenas sale la navegación, antes de que el router termine de cargar la página nueva.
    if (router.latestLocation.pathname !== pathname) {
      return;
    }

    const next = normalizeSearchText(value);

    if (next !== (urlValue ?? '')) {
      apply(next);
    }
  });

  useEffect(() => {
    applySearch(debounced);
  }, [debounced]);

  return [text, setText] as const;
}
