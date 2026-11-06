# Estado por módulo

Fuente de verdad del estado de cada módulo. Criterio de estados en [`roadmap/README.md`](../roadmap/README.md).

COMPLETADO no significa listo para producción.

| Módulo | Fase | Estado | Reglas | Observaciones |
|---|---|---|---|---|
| Clínicas (separación de datos, D1) | 0 | COMPLETADO | `CLAUDE.md` §3 | Separación por clínica (0.2): `clinic_id`, scopes, fallo cerrado, `SetCurrentClinic`, `ClinicService::create` y `tests/Feature/Tenancy`. Alta con `php artisan clinic:create` y `DemoSeeder` para desarrollo (0.4, R-012); sin alta por la API en el MVP. Deuda 5: sin claves foráneas compuestas |
| Autenticación | 0 | COMPLETADO | `CLAUDE.md` §3 y §4; [diseño de la 0.3](../fases/fase-00-puesta-en-marcha.md#03-autenticación) | `login`, `logout` y `me` con tokens de Sanctum de 12 horas, límite de intentos por usuario e IP, auditoría y `tests/Feature/Auth` (0.3, R-008). Prueba manual con Postman hecha en la 0.4 (R-012), con el alcance que indica la [guía](../testing/guia-postman.md). El hash de relleno usa el costo configurado (0.5, R-013). Deuda 6: el límite no es exacto en ráfagas con la caché `database` |
| Usuarios, roles y permisos | 0, 2 | EN DESARROLLO | `CLAUDE.md` §3 | Seeder, Spatie sin *teams* y `ClinicRolesTest` (permisos de roles, Controllers y rutas) desde la 0.1; cada usuario en una clínica, con `inClinic()` y el *route binding* acotado desde la 0.2; email en minúsculas y solo ASCII, y tokens borrados al eliminar el usuario desde la 0.3; regla de contraseñas (`UserPassword`, R-009) desde la 0.4; acceso por rol de cada ruta y método (`tests/Feature/Access`), el permiso de cada ruta igual a su nombre y la foto de los roles (0.5, R-013); el ABM es de la FASE 2 |
| Clientes | 1 | COMPLETADO | 6.1 | Código listo y separado por clínica, con tests de aislamiento (0.2) y de acceso por rol a sus rutas (0.5); mensajes en voseo, traducciones de validación y `PUT`/`PATCH` parciales probados (1.1, R-017); tests de cada regla de §6.1, dígito verificador del RUC, documento único por número base y filtros del listado validados (1.2, R-018 y R-019) |
| Pacientes | 1 | EN DESARROLLO | 6.2 | Código listo y separado por clínica, con tests de aislamiento (0.2) y de acceso por rol a sus rutas (0.5); mensajes en voseo, traducciones de validación y `PUT`/`PATCH` parciales probados (1.1, R-017); filtros del listado validados y sufijo de baja con el id (1.2, R-018); tests de cada regla de §6.2, sin `custom_fields` en la API, microchip a 60 caracteres y `deceased_at` en hora de Asunción (1.3, R-020); faltan la foto (1.5) y el ABM de especies y razas (1.4) |
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
| Auditoría | 0 | EN DESARROLLO | | `Auditable` y `audit_logs` listos, con la clínica de cada registro (0.2); `LOGIN`, `LOGIN_FAILED` y `LOGOUT` (0.3); falta la consulta (FASE 2) |
