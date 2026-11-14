import { useLocation } from '@tanstack/react-router';
import { validatePatientListSearch, type PatientListSearch } from './search';

/**
 * La búsqueda del listado de pacientes de donde se vino (`?buscar=&especie=&fallecidos=&pagina=`), en el estado del
 * historial (no en la URL), como la de los clientes (features/clients/navigation.ts): pasa del listado al detalle, al
 * formulario y de vuelta. Sobrevive a una recarga.
 */
declare module '@tanstack/history' {
  interface HistoryState {
    patientList?: PatientListSearch;
  }
}

/** La búsqueda guardada, validada como la de la URL; sin estado, la búsqueda vacía. */
export function usePatientListSearch(): PatientListSearch {
  const saved = useLocation({ select: (location) => location.state.patientList });

  return validatePatientListSearch({ ...saved });
}

/** El estado para un `<Link>` o un `navigate`: lleva la búsqueda del listado. */
export function withPatientList(search: PatientListSearch) {
  return { patientList: search };
}
