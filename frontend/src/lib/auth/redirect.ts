/**
 * A dónde ir después de ingresar: la ruta de `volver` si es interna de la aplicación; si no (vacía, otro origen, `//otro`,
 * `/\otro`, `javascript:`, la misma /ingresar), el inicio. Así `/ingresar?volver=https://otro.sitio` no saca al usuario.
 */
export function safeRedirect(volver: unknown, origin: string = window.location.origin): string {
  if (typeof volver !== 'string' || !volver.startsWith('/') || /^\/[/\\]/.test(volver)) {
    return '/';
  }

  let url: URL;

  try {
    url = new URL(volver, origin);
  } catch {
    return '/';
  }

  if (url.origin !== new URL(origin).origin || url.pathname === '/ingresar') {
    return '/';
  }

  return `${url.pathname}${url.search}${url.hash}`;
}
