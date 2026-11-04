# Estado por módulo

Fuente de verdad del estado de cada módulo. Criterio de estados en [`roadmap/README.md`](../roadmap/README.md).

COMPLETADO no significa listo para producción.

| Módulo | Fase | Estado | Reglas | Observaciones |
|---|---|---|---|---|
| Autenticación | 0 | PENDIENTE | `CLAUDE.md` §4 | Sanctum configurado (guard `api`, tokens de 720 minutos); login, logout y `me` en la entrega 0.3 |
| Usuarios, roles y permisos | 0, 2 | EN DESARROLLO | `CLAUDE.md` §3 | Seeder, Spatie sin *teams* y `ClinicRolesTest` (permisos de roles, Controllers y rutas) desde la 0.1; el ABM es de la FASE 2 |
| Clientes | 1 | EN DESARROLLO | 6.1 | Código listo, sin tests |
| Pacientes | 1 | EN DESARROLLO | 6.2 | Código listo, sin tests |
| Especies y razas | 1 | EN DESARROLLO | | Listado y seeder; falta el ABM |
| Sucursales y depósitos | 2 | PENDIENTE | | Tablas listas |
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
| Auditoría | 0 | EN DESARROLLO | | `Auditable` y `audit_logs` listos; falta la consulta (FASE 2) |
