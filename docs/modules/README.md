# Estado por módulo

Fuente de verdad del estado de cada módulo. Criterio de estados en [`roadmap/README.md`](../roadmap/README.md).

COMPLETADO no significa listo para producción.

| Módulo | Fase | Estado | Reglas | Observaciones |
|---|---|---|---|---|
| Clínicas (separación de datos, D1) | 0 | EN DESARROLLO | `CLAUDE.md` §3 | Completa la separación por clínica (0.2): `clinic_id`, scopes, fallo cerrado, `SetCurrentClinic`, `ClinicService::create` y `tests/Feature/Tenancy`. Falta el alta con `clinic:create` (0.4) |
| Autenticación | 0 | PENDIENTE | `CLAUDE.md` §4 | Sanctum configurado (guard `api`, tokens de 720 minutos); login, logout y `me` en la entrega 0.3 |
| Usuarios, roles y permisos | 0, 2 | EN DESARROLLO | `CLAUDE.md` §3 | Seeder, Spatie sin *teams* y `ClinicRolesTest` (permisos de roles, Controllers y rutas) desde la 0.1; cada usuario en una clínica, con `inClinic()` y el *route binding* acotado desde la 0.2; el ABM es de la FASE 2 |
| Clientes | 1 | EN DESARROLLO | 6.1 | Código listo y separado por clínica, con tests de aislamiento (0.2); faltan los tests funcionales (deuda #1) |
| Pacientes | 1 | EN DESARROLLO | 6.2 | Código listo y separado por clínica, con tests de aislamiento (0.2); faltan los tests funcionales (deuda #1) |
| Especies y razas | 1 | EN DESARROLLO | `CLAUDE.md` §3 | Listado y seeder; catálogo global más el propio de cada clínica (0.2); falta el ABM |
| Sucursales y depósitos | 2 | PENDIENTE | | Tablas listas; `ClinicService::create` crea `SUC-01` y `DEP-01` de cada clínica (0.2) |
| Veterinarios | 2 | PENDIENTE | | Tabla lista |
| Servicios | 2 | PENDIENTE | | Tabla lista |
| Agenda y turnos | 3 | PENDIENTE | | Tablas listas |
| Historia clínica | 4 | PENDIENTE | | Tablas listas |
| Inventario | 5 | PENDIENTE | | Tablas listas |
| Documentos | 6 | PENDIENTE | | Tablas listas |
| Recordatorios y avisos | 7 | PENDIENTE | | Tablas listas |
| Caja y facturación | 8 | PENDIENTE | | Tablas listas |
| SIFEN | 9 | PENDIENTE | | Columnas y `sifen_events` listas |
| Reportes | 10 | PENDIENTE | | |
| Auditoría | 0 | EN DESARROLLO | | `Auditable` y `audit_logs` listos, con la clínica de cada registro (0.2); falta la consulta (FASE 2) |
