# Despliegue

PENDIENTE: se define en la FASE 10 con la decisión D7 (hosting y respaldos).

Lo que ya se sabe que hace falta:

| Componente | Detalle |
|---|---|
| Worker de colas | `php artisan queue:work` con las colas `sifen`, `whatsapp` y `default` |
| Scheduler | `php artisan schedule:run` cada minuto (recordatorios, avisos de vencimiento) |
| Archivos privados | Fotos, adjuntos, PDF, XML de SIFEN y el certificado `.p12`, fuera de `public` |
| Zona horaria | `APP_TIMEZONE=America/Asuncion` |
