import { queryOptions, type QueryClient } from '@tanstack/react-query';
import { api, getBlob } from '../../lib/api/client';
import { authKeys } from '../auth/api';
import type { SystemSettings, SystemSettingsPayload } from './types';

/** Cuánto queda el logo en la caché sin nadie que lo use (como la foto): 5 minutos. */
const LOGO_GC_TIME_MS = 5 * 60_000;

/** Las claves de la configuración (diseño de la F2, "Claves de las consultas"): son de la clínica, no de la sucursal. */
export const settingsKeys = {
  all: ['settings'] as const,
  logo: ['settings', 'logo'] as const,
};

/** GET system-settings (los tres roles; la pantalla, el administrador). */
export function settingsQueryOptions() {
  return queryOptions({
    queryKey: settingsKeys.all,
    queryFn: async ({ signal }) =>
      (await api.get<SystemSettings>('system-settings', { signal })).data,
  });
}

/**
 * GET system-settings/logo: el archivo como Blob (con el token); sin logo, 404 CLINIC_LOGO_NOT_FOUND. La misma clave en la
 * barra y en la configuración: subirlo o quitarlo lo cambia en los dos lugares.
 */
export function logoQueryOptions() {
  return queryOptions({
    queryKey: settingsKeys.logo,
    queryFn: ({ signal }) => getBlob('system-settings/logo', { signal, expectedType: 'image/' }),
    staleTime: Infinity,
    gcTime: LOGO_GC_TIME_MS,
  });
}

/** PATCH system-settings: el formulario completo (200, SystemSettingsResource). */
export function updateSettings(payload: SystemSettingsPayload) {
  return api.patch<SystemSettings>('system-settings', payload);
}

/** POST system-settings/logo, multipart con el campo `logo` (200, SystemSettingsResource con has_logo). */
export function uploadLogo(file: File) {
  const body = new FormData();
  body.append('logo', file);

  return api.post<SystemSettings>('system-settings/logo', body);
}

/** DELETE system-settings/logo (200, SystemSettingsResource sin logo). */
export function deleteLogo() {
  return api.delete<SystemSettings>('system-settings/logo');
}

/**
 * El logo guardado se vacía sin volver a pedirlo (Query.reset, como la foto: resetQueries o removeQueries lo pedirían de
 * nuevo mientras la barra todavía cree que hay logo).
 */
export function forgetLogo(queryClient: QueryClient) {
  queryClient.getQueryCache().find({ queryKey: settingsKeys.logo, exact: true })?.reset();
}

/**
 * Después de guardar los datos: la configuración toma la respuesta y `me` se vuelve a pedir (el nombre de la clínica en la
 * barra; H5).
 */
export function afterSettingsChanged(queryClient: QueryClient, settings: SystemSettings) {
  queryClient.setQueryData(settingsKeys.all, settings);
  void queryClient.invalidateQueries({ queryKey: authKeys.me });
}

/**
 * Después de subir o quitar el logo: la configuración toma la respuesta, el archivo guardado se vacía (con logo nuevo,
 * resetQueries: se espera la descarga y nunca se ve el viejo; sin logo, no se pide nada) y `me` se vuelve a pedir
 * (`clinic.has_logo`: la barra lo muestra o vuelve a la marca).
 */
export function afterLogoChanged(queryClient: QueryClient, settings: SystemSettings) {
  queryClient.setQueryData(settingsKeys.all, settings);

  if (settings.has_logo) {
    void queryClient.resetQueries({ queryKey: settingsKeys.logo, exact: true });
  } else {
    forgetLogo(queryClient);
  }

  void queryClient.invalidateQueries({ queryKey: authKeys.me });
}

/** La clínica quedó sin logo según la API (404 CLINIC_LOGO_NOT_FOUND al quitarlo): todo se vuelve a pedir. */
export function markWithoutLogo(queryClient: QueryClient) {
  queryClient.setQueryData<SystemSettings>(settingsKeys.all, (current) =>
    current === undefined ? current : { ...current, has_logo: false },
  );
  forgetLogo(queryClient);
  void queryClient.invalidateQueries({ queryKey: settingsKeys.all, exact: true });
  void queryClient.invalidateQueries({ queryKey: authKeys.me });
}
