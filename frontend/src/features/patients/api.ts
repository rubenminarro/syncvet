import { keepPreviousData, queryOptions, type QueryClient } from '@tanstack/react-query';
import { api, getBlob, getPage } from '../../lib/api/client';
import { clientKeys } from '../clients/api';
import type { Patient, PatientDetail, PatientListFilters, PatientPayload, Species } from './types';

/** Pacientes por página: fijo, sin selector ni lugar en la URL (pregunta 13 del diseño de la F1). */
export const PATIENTS_PER_PAGE = 20;

/** El catálogo cambia poco: GET species se vuelve a pedir recién a los 5 minutos (diseño de la 1.3a). */
export const SPECIES_STALE_TIME_MS = 5 * 60 * 1000;

/**
 * Las claves de los pacientes y del catálogo de especies (frontend/CLAUDE.md §5, query-keys.ts): son de la clínica, no de la
 * sucursal, así que empiezan con el módulo y el cambio de sucursal no las toca.
 */
export const patientKeys = {
  all: ['patients'] as const,
  lists: ['patients', 'list'] as const,
  list: (filters: PatientListFilters) => ['patients', 'list', filters] as const,
  detail: (id: number) => ['patients', 'detail', id] as const,
  photo: (id: number) => ['patients', 'photo', id] as const,
};

export const speciesKeys = {
  all: ['species'] as const,
  list: (filters: { include_inactive: boolean }) => ['species', filters] as const,
};

/** GET patients: 20 por página, por nombre; mientras llega otra página o búsqueda, la tabla sigue con la anterior. */
export function patientListQueryOptions(filters: PatientListFilters) {
  return queryOptions({
    queryKey: patientKeys.list(filters),
    queryFn: ({ signal }) =>
      getPage<Patient>('patients', {
        signal,
        query: {
          search: filters.search,
          species_id: filters.species_id,
          include_deceased: filters.include_deceased ? 1 : null,
          page: filters.page,
          per_page: PATIENTS_PER_PAGE,
        },
      }),
    placeholderData: keepPreviousData,
  });
}

/** GET patients/{id}: los datos, con el dueño (ShowPatientResource). */
export function patientDetailQueryOptions(id: number) {
  return queryOptions({
    queryKey: patientKeys.detail(id),
    queryFn: async ({ signal }) =>
      (await api.get<PatientDetail>(`patients/${id}`, { signal })).data,
  });
}

/** GET species: las especies activas con sus razas activas (los selectores del paciente y el filtro del listado). */
export function speciesQueryOptions() {
  return queryOptions({
    queryKey: speciesKeys.list({ include_inactive: false }),
    queryFn: async ({ signal }) => (await api.get<Species[]>('species', { signal })).data,
    staleTime: SPECIES_STALE_TIME_MS,
  });
}

/** POST clients/{client}/patients (201, ShowPatientResource): el dueño sale de la URL. */
export function createPatient(clientId: number, payload: PatientPayload) {
  return api.post<PatientDetail>(`clients/${clientId}/patients`, payload);
}

/** PATCH patients/{id} con el formulario completo (200, ShowPatientResource). */
export function updatePatient(id: number, payload: PatientPayload) {
  return api.patch<PatientDetail>(`patients/${id}`, payload);
}

/**
 * PATCH patients/{id} solo con `deceased_at` (1.3b): registrar o corregir el fallecimiento (la hora de pared de Asunción con
 * su desplazamiento, `toApiDateTime`) o anularlo (null). Los demás campos no se mandan: la API los deja como están.
 */
export function setPatientDeceased(id: number, deceasedAt: string | null) {
  return api.patch<PatientDetail>(`patients/${id}`, { deceased_at: deceasedAt });
}

/** POST patients/{id}/change-owner (200, ShowPatientResource con el dueño nuevo). */
export function changePatientOwner(id: number, clientId: number) {
  return api.post<PatientDetail>(`patients/${id}/change-owner`, { client_id: clientId });
}

/** DELETE patients/{id} (200, sin datos). */
export function deletePatient(id: number) {
  return api.delete<null>(`patients/${id}`);
}

/**
 * Los detalles de estos clientes se vacían (resetQueries, como los listados en la baja de un cliente de la 1.2): no están a
 * la vista, así que no se vuelven a pedir hasta mostrarse, y al entrar se espera la lista nueva de sus pacientes en lugar de
 * mostrar un momento la vieja (revisión de la 1.3b).
 */
export function resetClientDetails(queryClient: QueryClient, ids: (number | null)[]) {
  for (const id of new Set(ids)) {
    if (id !== null) {
      void queryClient.resetQueries({ queryKey: clientKeys.detail(id), exact: true });
    }
  }
}

/**
 * Después de la baja (o de un 404 en una acción: el paciente ya no está), antes de salir del detalle: los listados de
 * pacientes y el detalle del dueño se vacían (no se ve un momento el paciente borrado) y las demás consultas de clientes
 * quedan viejas. Devuelve lo que se hace después de salir: sacar el detalle de la caché.
 */
export function afterPatientGone(
  queryClient: QueryClient,
  patient: Pick<PatientDetail, 'id' | 'client'>,
): () => void {
  void queryClient.resetQueries({ queryKey: patientKeys.lists });
  void queryClient.invalidateQueries({ queryKey: patientKeys.all, refetchType: 'none' });
  void queryClient.invalidateQueries({ queryKey: clientKeys.all, refetchType: 'none' });
  resetClientDetails(queryClient, [patient.client?.id ?? null]);

  return () => {
    queryClient.removeQueries({ queryKey: patientKeys.detail(patient.id), exact: true });
  };
}

/**
 * Después del alta o la edición (diseño de la F1, "Invalidaciones"): las consultas de pacientes quedan viejas (los listados
 * buscan por los datos cambiados) y también las de clientes (el detalle del cliente lista sus pacientes y la búsqueda de
 * clientes encuentra por mascota). Se invalidan sin volver a pedir enseguida (refetchType 'none'): se piden al mostrarse. El
 * detalle del paciente toma la respuesta, sin otro pedido.
 */
export function afterPatientSaved(queryClient: QueryClient, patient: PatientDetail) {
  void queryClient.invalidateQueries({ queryKey: patientKeys.all, refetchType: 'none' });
  void queryClient.invalidateQueries({ queryKey: clientKeys.all, refetchType: 'none' });
  queryClient.setQueryData<PatientDetail>(patientKeys.detail(patient.id), patient);
}

/**
 * La foto del paciente es de esta pestaña (1.4): la API manda `no-store`, así que la caché de Query es la única. No se vuelve
 * a pedir sola (staleTime Infinity: subirla o quitarla la cambia) y se descarta a los 5 minutos sin usarse.
 */
export const PHOTO_GC_TIME_MS = 5 * 60 * 1000;

/** GET patients/{id}/photo: el archivo como Blob (getBlob); sin foto, 404 PATIENT_PHOTO_NOT_FOUND. */
export function patientPhotoQueryOptions(id: number) {
  return queryOptions({
    queryKey: patientKeys.photo(id),
    queryFn: ({ signal }) => getBlob(`patients/${id}/photo`, { signal, expectedType: 'image/' }),
    staleTime: Infinity,
    gcTime: PHOTO_GC_TIME_MS,
  });
}

/** POST patients/{id}/photo, multipart con el campo `photo` (200, ShowPatientResource con has_photo). */
export function uploadPatientPhoto(id: number, file: File) {
  const body = new FormData();
  body.append('photo', file);

  return api.post<PatientDetail>(`patients/${id}/photo`, body);
}

/** DELETE patients/{id}/photo (200, ShowPatientResource sin foto). */
export function deletePatientPhoto(id: number) {
  return api.delete<PatientDetail>(`patients/${id}/photo`);
}

/**
 * La foto guardada se vacía sin volver a pedirla (Query.reset, no resetQueries ni removeQueries: los dos la vuelven a pedir
 * mientras la consulta sigue habilitada, antes de que el detalle diga has_photo false).
 */
export function forgetPatientPhoto(queryClient: QueryClient, id: number) {
  queryClient
    .getQueryCache()
    .find({ queryKey: patientKeys.photo(id), exact: true })
    ?.reset();
}

/**
 * El paciente quedó sin foto según la API (404 PATIENT_PHOTO_NOT_FOUND al verla o al quitarla): el detalle pasa a has_photo
 * false y la foto guardada se vacía.
 */
export function markWithoutPhoto(queryClient: QueryClient, id: number) {
  queryClient.setQueryData<PatientDetail>(patientKeys.detail(id), (current) =>
    current === undefined ? current : { ...current, has_photo: false },
  );
  forgetPatientPhoto(queryClient, id);
}

/**
 * Después de subir o quitar la foto: el detalle toma la respuesta (has_photo) y la foto guardada se vacía: con la foto nueva,
 * resetQueries (se espera la descarga en lugar de mostrar un instante la vieja); sin foto, no se pide nada. Los listados no
 * muestran la foto: no se tocan.
 */
export function afterPhotoChanged(queryClient: QueryClient, patient: PatientDetail) {
  queryClient.setQueryData<PatientDetail>(patientKeys.detail(patient.id), patient);

  if (patient.has_photo) {
    void queryClient.resetQueries({ queryKey: patientKeys.photo(patient.id), exact: true });
  } else {
    forgetPatientPhoto(queryClient, patient.id);
  }
}
