# Registro de decisiones

Una sección por entrega o decisión, numerada `N.M` (N = fase). Solo crece: las secciones viejas no se corrigen.

# 0.1 Paquete base: esquema del MVP y arquitectura (2026-11-04)

Antes de la FASE 0. Esquema completo del MVP y arquitectura base, armados fuera del repositorio y verificados con el código de
Laravel 13 (sin PHPUnit).

| Tema | Decisión |
|---|---|
| Arquitectura | Controller (`HasMiddleware`) → Form Request → Service → Model → Resource → `ApiResponse`; excepciones de dominio con `error_code`; estados con Enum; dinero con BCMath; códigos internos con `DocumentSequenceService`; auditoría con `Auditable` (decisión del usuario del 2026-11-03: seguir las reglas de backend que ya usa en otro proyecto) |
| Idioma | Código, tablas, columnas y `error_code` en inglés; mensajes al usuario en español |
| Esquema | 27 migraciones: 8 de base (usuarios, cache, colas, tokens, permisos, auditoría, configuración, secuencias) y 19 de SyncVet |
| Estados | `string(20)` en la base, nunca `ENUM` de MySQL |
| Mercado | Paraguay: IVA 0/5/10, timbrados, SIFEN, zona horaria `America/Asuncion` (decisión del usuario del 2026-11-03) |
| Roles | `administrador` (todo por `Gate::before`), `recepcionista`, `veterinario`; 119 permisos |
| Módulo de ejemplo | Clientes, pacientes y especies con Controller, Requests, Service y Resources |
| Verificación | Migraciones arriba y abajo en SQLite con claves foráneas; 221 sentencias MySQL sin nombres de más de 64 caracteres; prueba de los Services de clientes y pacientes; permisos de controllers y roles contra el seeder |
| Pendiente | D1 a D7 ([`README.md`](README.md)); tests de PHPUnit (FASE 0 y 1) |

# 0.2 D1: una base compartida con `clinic_id` (2026-11-04)

Decisión del usuario del 2026-11-04, sobre la recomendación del plan. Se implementa en la FASE 0, entrega 0.2
([`fase-00-puesta-en-marcha.md`](../fases/fase-00-puesta-en-marcha.md)).

| Tema | Decisión |
|---|---|
| Modelo | Una sola base para todas las clínicas. Tabla `clinics` y columna `clinic_id` en las tablas de negocio |
| Alternativas descartadas | Una base por clínica (migrar y respaldar cada base por separado); una instalación por cliente |
| Qué tablas llevan `clinic_id` | Todas las de negocio, también las hijas (ítems, horarios, movimientos), para que el filtro sea uniforme y los reportes no dependan de joins. Excepciones: pivotes (`branch_user`), tablas de Laravel (cache, jobs, sessions, tokens, notifications) y los catálogos `species` y `breeds`, donde `clinic_id` es nulo para los globales |
| Usuarios | Cada usuario pertenece a una clínica (`users.clinic_id`); el email sigue único en todo el sistema porque es el login. Roles comunes a todas las clínicas, sin la opción *teams* de Spatie |
| Aislamiento | Trait `BelongsToClinic` (completa `clinic_id` al crear) y global scope `ClinicScope`, con la clínica del usuario fijada por un middleware después de `auth:sanctum` |
| Fallo cerrado | Sin clínica en contexto, una consulta sobre un modelo con el scope lanza una excepción; nunca devuelve datos de todas las clínicas. Jobs y comandos fijan la clínica explícitamente |
| Únicos | Pasan a compuestos con `clinic_id` (documento y email del cliente, códigos, número de factura, timbrados, `document_sequences.key`, `system_settings.key`). El CDC de SIFEN sigue único global |
| Validación | Toda regla `unique` y `exists` de los Form Requests filtra por la clínica actual |
| Alta de una clínica | `ClinicService::create` crea la clínica, su sucursal y depósito por defecto y su configuración. Las filas por defecto salen de las migraciones |
| Tests | `tests/Feature/Tenancy`: cada protección tiene un test que falla al quitarla (scope, creación, validación, rutas con un id de otra clínica → 404) |
| Sin cambiar | Arquitectura, contrato de la API, permisos y reglas de negocio |

# 0.3 Trabajo previo (Bloque 0.1) (2026-11-04)

Registro de lo que quedó del trabajo de documentación anterior al paquete base: una "Fase 0" de solo documentación, por
bloques del 0.1 al 0.6 y con aprobación explícita del usuario. Esa numeración no es la de la FASE 0 de
[`fase-00-puesta-en-marcha.md`](../fases/fase-00-puesta-en-marcha.md). Fuente: notas de sesión de Claude Code del
2026-11-03. Desde esta entrega la fuente de verdad son `docs/` y `CLAUDE.md`, no esas notas.

| Tema | Lo que se sabe |
|---|---|
| Archivo `docs/00-vision/oportunidades-producto.md` | Contenía las decisiones del Bloque 0.1 en su §7. Se perdió: no está en ningún commit (verificado con `git log --all` el 2026-11-04). Contenido: PENDIENTE DE VERIFICACIÓN |
| DP-0.1-04 | Decisión del Bloque 0.1, aprobada por el usuario y marcada como no modificable. Contenido: PENDIENTE DE VERIFICACIÓN |
| DP-0.1-05 | Decisión del Bloque 0.1, aprobada por el usuario y marcada como no modificable. Contenido: PENDIENTE DE VERIFICACIÓN |
| DP-0.1-01 a DP-0.1-03 y otras | Si existieron y en qué estado quedaron: PENDIENTE DE VERIFICACIÓN |
| Criterio de evidencia | En ese trabajo se distinguía lo verificado, lo publicitado (web o anuncio), lo referido y lo inferido, sin presentar interpretaciones normativas como hechos |
| Material que sí está en el repositorio | [`docs/prompts/prompt-fase0-syncvet.md`](../prompts/prompt-fase0-syncvet.md), [`docs/references/competitors.md`](../references/competitors.md) y tres anuncios en `docs/references/ads/` |
| Pendiente | Reconstruir DP-0.1-04, DP-0.1-05 y el §7 desde otra copia (por ejemplo, la conversación de revisión con ChatGPT) y registrarlos en una sección nueva |
