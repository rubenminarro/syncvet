# Reglas de negocio: auditoría

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.9 Auditoría

* Consulta en `audit-logs` (`GET` listado y `GET audit-logs/{auditLog}` detalle; permisos `audit-logs.index` y `audit-logs.show`):
  solo el administrador (P14); el recepcionista y el veterinario reciben 403. Sin rutas de escritura (405): los registros son
  inmutables (`AUDIT_LOG_IMMUTABLE` en el modelo).
* Clínica: `AuditLog` tiene el `ClinicScope`; un registro de otra clínica o sin clínica (`LOGIN_FAILED` con un email desconocido,
  `clinic_id` nulo) nunca aparece en el listado y en el detalle responde 404.
* Listado (`AuditLogResource`): `id`, `event` (`{value, label}`), `auditable_type` (el tipo corto, `"patient"`), `auditable_id`,
  `user` (`id`, `name`; también eliminado; `null` sin usuario), `ip_address` y `created_at` (UTC, `Z`); sin `old_values` ni
  `new_values`. Por `created_at` e `id` descendentes, paginado con `ListFilters`.
* Filtros (`IndexAuditLogRequest`): `from` y `to` (una fecha sola, de 00:00 a 23:59:59 de Asunción, o una fecha con hora con o sin
  zona, por `ApiDateTime::FILTER_FORMAT`: horas de 00 a 23 y desplazamientos de hasta ±14:00; otro formato, también "tomorrow", →
  422 "Ingresá una fecha (2026-11-09) o una fecha con hora (2026-11-09T13:00:00Z)."; `from` con fracción de segundo se redondea
  hacia arriba y `to` hacia abajo; `to` anterior a `from` → 422 en `to`), `user_id` (un usuario de la clínica, también eliminado;
  si no, 422), `event` (`AuditEvent`), `auditable_type` (lista cerrada `AuditLog::AUDITABLE_TYPES`, que `AuditableTypesTest`
  compara con los modelos con `Auditable`), `auditable_id` (exige el tipo: 422 en `auditable_type`) y `search` literal en el nombre
  o el email del usuario.
* Detalle (`ShowAuditLogResource`): suma `old_values`, `new_values` y `user_agent`. Nunca muestra contraseñas ni tokens
  (`AuditLogger` guarda `password`, `remember_token` y `token` como `********`) ni rutas de archivos: la foto del paciente se audita
  como `"(archivo)"` (`Patient::auditValues()`), como el logo (R-041); en los registros anteriores, el detalle cambia por
  `"(archivo)"` el `photo_path` (sin quitar la clave) y cualquier valor con la forma `clinics/{id}/logo/…`, también anidado.
