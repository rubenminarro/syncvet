# Despliegue

PENDIENTE: se define en la FASE 10 con la decisión D7 (hosting y respaldos).

Lo que ya se sabe que hace falta:

| Componente | Detalle |
|---|---|
| Worker de colas | `php artisan queue:work` con las colas `sifen`, `whatsapp` y `default` |
| Scheduler | `php artisan schedule:run` cada minuto (recordatorios, avisos de vencimiento) |
| Archivos privados | Fotos, adjuntos, PDF, XML de SIFEN y el certificado `.p12`, fuera de `public` |
| Zona horaria | `APP_TIMEZONE=America/Asuncion` |
| PHP: `zend.exception_ignore_args=On` | Obligatorio en producción (es el valor de `php.ini-production`). Con `Off`, las trazas del log muestran los argumentos de cada llamada. La contraseña del login ya va con `#[\SensitiveParameter]` y los errores de negocio no se reportan, pero esta opción cubre cualquier otro dato que llegue a una traza |
| Proxies de confianza | `TRUSTED_PROXIES` (`config/trustedproxy.php`), vacía por defecto: no se confía en ningún proxy y se ignora `X-Forwarded-For`. Detrás de un balanceador o proxy inverso, poner su IP o rango (`10.0.0.1` o `10.0.0.0/8,192.168.1.10`); `*` solo si la aplicación no es accesible sin pasar por el proxy. Sin esto, detrás de un proxy todos los usuarios comparten su IP: el límite de intentos del login queda solo por usuario (5 fallidos bloquean a esa persona en todas partes) y la auditoría registra la IP del proxy |
| Caché: Redis | `CACHE_STORE=redis`. El límite de intentos del login usa la caché, y con `database` el contador no es atómico: en una ráfaga simultánea pasan de 0 a 4 intentos de más (deuda 6, [`roadmap/deuda-tecnica.md`](../roadmap/deuda-tecnica.md)). Redis incrementa de forma atómica; verificar al desplegar que 20 logins fallidos simultáneos dejen pasar exactamente 5 (si no, envolver el `hit` en `Cache::lock`) |
| Salida a internet: Have I Been Pwned | La regla de contraseñas (R-009) consulta `https://api.pwnedpasswords.com` al crear un usuario (`clinic:create`, el `DemoSeeder` y, desde la FASE 2, el ABM de usuarios). Solo viajan los 5 primeros caracteres del SHA-1. Sin salida a internet la verificación no se hace: Laravel acepta la contraseña y deja el error en el log |
| Log | `storage/logs` no debe tener contraseñas ni tokens. Los errores de negocio (`AppException`) no se escriben; `ClinicContextMissingException` sí, porque es un bug |
