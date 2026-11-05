# Registro de decisiones

Una sección por entrega o decisión, numerada `R-NNN` en orden correlativo (R-001, R-002…). La numeración es propia del
registro y no coincide con la de las entregas de cada fase (0.1, 0.2…). Solo crece: las secciones viejas no se corrigen.

# R-001 Paquete base: esquema del MVP y arquitectura (2026-11-04)

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

# R-002 D1: una base compartida con `clinic_id` (2026-11-04)

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

# R-003 Trabajo previo (Bloque 0.1) (2026-11-04)

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

# R-004 Respuestas de la FASE 0 y correcciones de la documentación (2026-11-04)

Decisiones del usuario del 2026-11-04, después del análisis previo a la FASE 0. Las respuestas de negocio están también en la
tabla de preguntas de [`fase-00-puesta-en-marcha.md`](../fases/fase-00-puesta-en-marcha.md).

| Tema | Decisión |
|---|---|
| Login (P2) | Con email, único en todo el sistema |
| Tokens (P3) | Vencen a las 12 horas (una jornada). El logout revoca el token actual |
| Usuarios y clínicas (P4) | Un usuario pertenece a una sola clínica en el MVP. Sin *teams* de Spatie |
| Alta de clínicas (P5) | La hace el administrador de la plataforma con `php artisan clinic:create`, que llama a `ClinicService::create` y crea el primer usuario administrador de la clínica. Sin registro público ni rol de plataforma en el MVP |
| DP-0.1-04 y DP-0.1-05 (R-003) | No se pudieron recuperar. D1 (R-002) prevalece; si aparecen, se revisan en una sección nueva |
| Límite de intentos en el login | Se adelanta a la entrega del `AuthController`: 5 intentos por minuto por email e IP. Cierra la deuda #4 |
| Idioma de la interfaz | Mensajes al usuario en español con voseo, como pedía `prompt-fase0-syncvet.md`. Los mensajes existentes se pasan a voseo en la FASE 1 |
| Guaraníes | Las columnas de dinero siguen en `decimal(14,2)` para admitir otra moneda, pero los importes en PYG se redondean a 0 decimales al calcular |
| D1: filas por defecto | Cambia R-002 ("las filas por defecto salen de las migraciones"): la sucursal y el depósito por defecto se quitan de la migración 000005 y los crea `ClinicService::create` |
| D1: catálogos `species` y `breeds` | Scope propio "globales + los de mi clínica" (`clinic_id` nulo o igual al actual), también con fallo cerrado. Solo se crean y editan los de la propia clínica |
| Entrega 0.1 | El paquete base ya está en la raíz del repositorio. Laravel 13 se crea en una carpeta temporal y se trae solo lo que el paquete no tiene; ante un choque gana el archivo del paquete. De `install:api` y de Spatie se publica solo la configuración, no sus migraciones (ya existen 000003 y 000004) |
| Material histórico | `docs/prompts/prompt-fase0-syncvet.md` queda como material histórico; donde contradiga a `CLAUDE.md` o a D1, mandan estos |
| Registro | Las secciones pasan de `N.M` a `R-NNN` (0.1 → R-001, 0.2 → R-002, 0.3 → R-003) para no chocar con las entregas, que siguen siendo 0.1, 0.2… |
| Prompts | `4-revision.md` → `5-revision.md` y `5-cierre.md` → `7-cierre.md`, para que coincidan con el número del paso |
| Remoto | Repositorio `https://github.com/rubenminarro/syncvet`, con `main`, `docs/fase0-paso1` y `feature/fase-00-paquete-base` subidas |
| Sin cambiar | Arquitectura, contrato de la API, permisos y reglas de negocio vigentes |

# R-005 Aprobación del diseño de la FASE 0 (2026-11-04)

Decisión del usuario del 2026-11-04. Aprueba el diseño de
[`fase-00-puesta-en-marcha.md`](../fases/fase-00-puesta-en-marcha.md) y responde sus preguntas.

| Tema | Decisión |
|---|---|
| Entregas | Se reagrupan de siete a cinco: 0.1 proyecto, Sanctum, Spatie y tests; 0.2 D1; 0.3 autenticación; 0.4 `clinic:create`; 0.5 acceso por rol y MySQL |
| RUC de la clínica | Único en todo el sistema y con validación del dígito verificador (módulo 11) |
| Límite de intentos | Solo cuentan los login fallidos; un login correcto limpia el contador |
| Contraseña del primer administrador | `clinic:create` la pide oculta y con confirmación; sin interacción, la genera y la muestra una sola vez |
| Microchip y matrícula del veterinario | Únicos por clínica: la misma mascota puede atenderse en dos clínicas, y un único global revelaría datos de otra clínica |
| Usuarios inactivos | Se decide en la FASE 2 |
| Sesiones | Varias por usuario: cada login crea su token. `sanctum:prune-expired` diario limpia los vencidos |
| `DemoSeeder` | Solo en `local` y `testing`, idempotente y nunca en producción |
| `database/schema-mysql.sql` | Se quita; la verificación en MySQL de la entrega 0.5 lo reemplaza |
| Usuarios y clínica | `User` sin scope de clínica. Toda consulta de usuarios fuera del login filtra por `clinic_id` de forma explícita; el *route binding* de un usuario de otra clínica responde 404, con su test en la 0.2 (`CLAUDE.md` §3) |
| `ClinicContextMissingException` | Responde el 500 genérico, y queda en el log con la ruta o el job que la causó |
| Orden de bloqueo | agenda → turno; consulta → ítem → `product_stocks` → `product_lots`; timbrado → factura; factura → pago; caja → pago; documento → serie de códigos, siempre última (`CLAUDE.md` §3) |
| Restos de otro proyecto | Se quitan la "FASE 25" de `AuditLogger` y de la migración de `audit_logs`, y los ejemplos "part" y "part_supplier" |
| Sin cambiar | Contrato de la API, permisos y reglas de negocio vigentes |

# R-006 Entrega 0.1 de la FASE 0: proyecto Laravel 13 (2026-11-04)

Cierre de la entrega 0.1 ([`fase-00-puesta-en-marcha.md`](../fases/fase-00-puesta-en-marcha.md#entrega-01)), commits `11b82b3` y
`2b50149`. El usuario la validó el 2026-11-04 (suite, `migrate:fresh --seed` y `GET /up`).

| Tema | Decisión | Quién |
|---|---|---|
| Versiones | Laravel 13.40, Sanctum 4.3 y spatie/laravel-permission 8.3; de los paquetes se publica solo la configuración | Implementación, dentro del diseño (R-004) |
| Esqueleto sin frontend | No se traen `package.json`, Vite, `resources/css`, `resources/js` ni `AGENTS.md`: el frontend vive en otro repositorio | Implementación (commit `11b82b3`) |
| Base de los tests | `tests/bootstrap.php` crea la base SQLite y vuelve absoluta la ruta que da `phpunit.xml` | Implementación; la ruta absoluta, corrección de la revisión autorizada por el usuario |
| Errores HTTP sin renderer propio | Conservan su código en lugar del 500 del catch-all: 403 `FORBIDDEN`, 409 `CONFLICT` (nuevo) y cualquier otro 4xx `HTTP_ERROR` (nuevo), con sus encabezados. La `HttpResponseException` sale tal cual. Lista de `error_code` genéricos en `CLAUDE.md` §4 | Usuario, en la revisión |
| Mensajes nuevos | El 403 reutiliza el mensaje de `FORBIDDEN`, todavía con tú (pasa a voseo en la FASE 1, R-004). Los de `CONFLICT` y `HTTP_ERROR` no usan tú ni vos | Implementación |
| Permiso en cada ruta | `ClinicRolesTest` exige un `permission:` en cada ruta de `/api`, salvo las de `ROUTES_WITHOUT_PERMISSION` con su motivo. La 0.3 agrega `login`, `logout` y `me` | Usuario, en la revisión |
| `composer setup` | Migra y siembra (`--seed`) | Corrección de la revisión, autorizada por el usuario |
| `/validar-docs` | El script compara también `permisos` y `migraciones` | Corrección de la revisión, autorizada por el usuario |
| Sin cambiar | Contrato de respuesta, `error_code` y códigos HTTP existentes, permisos del seeder (119) y roles, reglas de negocio de clientes y pacientes | — |

# R-007 Entrega 0.2 de la FASE 0: separación por clínica (2026-11-05)

Cierre de la entrega 0.2 ([`fase-00-puesta-en-marcha.md`](../fases/fase-00-puesta-en-marcha.md#entrega-02)), commits `fb52b73` y
`cf8f862`. El usuario la validó el 2026-11-05 (suite y `migrate:fresh --seed`). Implementa D1 (R-002, R-004, R-005).

| Tema | Decisión | Quién |
|---|---|---|
| Email del administrador repetido | `CLINIC_ADMIN_EMAIL_TAKEN` (422) en `ClinicException`; la verificación incluye a los usuarios eliminados | Usuario, en la implementación |
| Índices | Los de filtro y listado empiezan por `clinic_id`; los que buscan un registro puntual (*morphs*, `appointments.series_id`, `client_notifications.external_id`) quedan sin él | Implementación |
| Origen en el log | `ExecutionOrigin` informa el job, el comando (por `CommandStarting`, también con `Artisan::call`) o la ruta | Implementación; los comandos, corrección de la revisión pedida por el usuario |
| Escrituras masivas en catálogos | `update`, `delete`, `forceDelete`, `touch` e `increment` sobre `Species` y `Breed` solo alcanzan los registros de la clínica en contexto, nunca los globales (`SharedCatalogBuilder`), y no cambian `clinic_id` | Usuario, en la revisión |
| `clinic_id` inmutable | Cambiarlo al editar lanza `ClinicContextMissingException`, en `BelongsToClinic` y en `SharedCatalog` (`ClinicIdGuard`). Regla en `CLAUDE.md` §3 | Usuario, en la revisión |
| Clínica de la auditoría | Primero la del registro auditado (su `clinic_id`, o la propia `Clinic`), después la del contexto, después la del usuario. Cambia el orden del diseño, que ponía el contexto primero | Usuario, en la revisión |
| Mutaciones equivalentes | A (error al crear un catálogo con otro `clinic_id`), B (agrupado del `OR` del scope de catálogos) y D (`clinic_id` calificado en `ClinicScope`) quedan como están, sin test: no cambian el resultado. El motivo de cada una está en el resultado de la entrega | Usuario, en la revisión |
| Claves foráneas compuestas | No se agregan en la 0.2: queda como deuda #5, a revisar antes del primer cliente real | Usuario, en la revisión |
| Sin cambiar | Contrato de respuesta, `error_code` y códigos HTTP existentes (se agregan `CLINIC_NOT_AVAILABLE` y el 404 de un id de otra clínica), permisos y roles, reglas de negocio de clientes y pacientes | — |

# R-008 Entrega 0.3 de la FASE 0: autenticación (2026-11-05)

Cierre de la entrega 0.3 ([`fase-00-puesta-en-marcha.md`](../fases/fase-00-puesta-en-marcha.md#entrega-03)), commits `2114244`,
`3da07f2` y `727546e`. El usuario la validó el 2026-11-05 (suite) y dejó la prueba manual con Postman para la entrega 0.4.

| Tema | Decisión | Quién |
|---|---|---|
| Clave del límite de intentos | Por usuario e IP; por email en minúsculas e IP si el email no existe. Cambia R-004 ("por email e IP"): con la collation de MySQL, una variante Unicode del email abría un contador nuevo | Usuario, el 2026-11-05, a propuesta de la primera revisión (`3da07f2`) |
| Momento del conteo | El intento se cuenta antes de verificar la contraseña; un login correcto limpia el contador y uno con la clínica suspendida lo descuenta | Usuario, el 2026-11-05, a propuesta de la primera revisión |
| Email del login | Se guarda y se busca en minúsculas, y solo admite ASCII (`ascii` en el login, `CLINIC_ADMIN_EMAIL_INVALID` en `ClinicService`) | Implementación (minúsculas); el ASCII, usuario, el 2026-11-05, a propuesta de la primera revisión |
| Emails sin cuenta | El mismo error, el mismo trabajo (hash de relleno precalculado con el costo de producción) y el mismo límite que los emails con cuenta | Implementación; la constante, usuario, el 2026-11-05, a propuesta de la primera revisión |
| Logout | Funciona con la clínica suspendida (sin `SetCurrentClinic`) | Usuario, el 2026-11-05, a propuesta de la primera revisión |
| Log | Los errores de negocio (`AppException` y subclases) no se reportan, en toda la API; `ClinicContextMissingException` sí. Las contraseñas van con `#[\SensitiveParameter]`; en producción, `zend.exception_ignore_args=On` | Usuario, el 2026-11-05, a propuesta de la primera revisión; el test de `ClinicService`, usuario en la segunda revisión |
| Tokens de un usuario eliminado | Se borran al eliminarlo. Los usuarios se eliminan modelo por modelo, nunca con un borrado masivo (`CLAUDE.md` §3) | Usuario, el 2026-11-05, a propuesta de la primera revisión; la regla, usuario en la segunda revisión |
| Proxies de confianza | `TRUSTED_PROXIES`, vacía por defecto (ninguno) | Usuario, el 2026-11-05, a propuesta de la primera revisión |
| Límite en ráfagas | Con la caché `database` pasan de 0 a 4 intentos de más en una ráfaga simultánea: queda como deuda 6, a resolver con Redis en producción en la FASE 10, verificando que pasen exactamente 5 | Usuario, en la segunda revisión |
| Validación manual | La guía de Postman de la FASE 0 se recorre en la entrega 0.4, con el `DemoSeeder` | Usuario |
| Sin cambiar | Contrato de respuesta, `error_code` y códigos HTTP existentes (se agregan `INVALID_CREDENTIALS`, `AUTH_TOO_MANY_ATTEMPTS` y `CLINIC_ADMIN_EMAIL_INVALID`), permisos (119) y roles, reglas de negocio de clientes y pacientes | — |

# R-009 Regla de contraseña de los usuarios (2026-11-05)

Decisión tomada durante la entrega 0.4 de la FASE 0 (`clinic:create` y `DemoSeeder`), antes de su cierre. No había ninguna regla:
el login solo exige un texto.

| Tema | Decisión | Quién |
|---|---|---|
| Regla | `Password::defaults()` (`AppServiceProvider`): al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos, y `uncompromised()`: no puede aparecer en filtraciones conocidas (Have I Been Pwned). Reemplaza la primera propuesta de la 0.4 (10 caracteres, con letras y números) | Usuario, el 2026-11-05 |
| Dónde se aplica | La contraseña del administrador de `clinic:create` y `DEMO_PASSWORD` del `DemoSeeder`; en la FASE 2, el ABM de usuarios. Reglas y mensajes con voseo en `App\Support\UserPassword`. El login no la aplica: valida credenciales, no contraseñas nuevas | Implementación |
| Contraseña generada | `clinic:create` sin interacción genera 20 caracteres con al menos una mayúscula, una minúscula, un número y un símbolo, sin caracteres ambiguos (0/O, 1/l/I) | Implementación |
| Have I Been Pwned | Se consulta por k-anonimato: solo viajan los 5 primeros caracteres del SHA-1. Si el servicio no responde, Laravel acepta la contraseña y reporta el error al log. En producción hace falta salida HTTPS a `api.pwnedpasswords.com` ([`deployment.md`](../development/deployment.md)) | Usuario (la regla); comportamiento de Laravel |
| Tests | Ningún test sale a la red: `tests/TestCase.php` bloquea los pedidos HTTP sin simular y simula Have I Been Pwned con `Http::fake` | Usuario |

# R-010 Revisión de la entrega 0.4 de la FASE 0 (2026-11-05)

Correcciones de la revisión de `clinic:create` y el `DemoSeeder`, antes del cierre de la entrega. Completa R-009.

| Tema | Decisión | Quién |
|---|---|---|
| Argumentos en las trazas | `AppServiceProvider::register` fija `zend.exception_ignore_args=1` al arrancar: ninguna traza (log ni reporte externo) lleva los argumentos de las funciones, sin depender del `php.ini` (en desarrollo suele estar en Off). `phpunit.xml` arranca en Off, como el `php.ini` de desarrollo, para probar que lo hace la aplicación. `#[\SensitiveParameter]` queda como segunda protección | Usuario |
| Have I Been Pwned | Verificador propio (`App\Support\PwnedPasswordsVerifier`) en lugar del de Laravel: espera como mucho 3 segundos y, si el servicio no responde o responde con error, acepta la contraseña y registra solo un aviso, sin traza, sin la contraseña y sin el hash. El de Laravel esperaba 30 segundos y reportaba la excepción con su traza, que llevaba la contraseña como argumento (los primeros 15 caracteres en el log). Reemplaza el comportamiento descrito en R-009 | Usuario |
| `clinic:create` | Verifica el RUC (dígito y que esté libre) y el email (ASCII y libre) antes de pedir la contraseña (`ClinicService::ensureCanCreate`); `create()` lo repite dentro de su transacción | Usuario |
| `DemoSeeder`: entornos | Fuera de `local` y `testing` **falla** con una excepción, en vez de terminar sin hacer nada como decía el diseño: un `db:seed` en el entorno equivocado no pasa inadvertido. Exactamente esos dos entornos (`DemoSeeder::ENVIRONMENTS`) | Usuario (desvío de la implementación, aceptado) |
| `DemoSeeder`: dos clínicas | Crea **dos** clínicas (A y B), no una como decía el diseño: la guía de Postman necesita una segunda clínica para probar la separación de datos (D1) | Usuario (desvío de la implementación, aceptado) |
| `DemoSeeder`: solo lo suyo | Cada clínica de demostración lleva la marca `demo_data` en `system_settings`. Una clínica con el RUC de una de demostración sin la marca, o un email de usuario que ya es de otra clínica (también eliminado), lo hacen fallar con un mensaje claro: no los adopta ni los modifica. `system_settings` todavía no tiene modelo: la marca se lee y se escribe con el query builder y `clinic_id` explícito | Usuario (la regla); implementación (la marca en `system_settings`, sin migración) |
| RUC de ejemplo | Los ejemplos usan RUC ficticios con dígito verificador válido: el `DemoSeeder` `99999901` y `99999902`; los tests, `99999910` y `99999911`; la factory de clínicas, de `90000000` a `99899999`. Se eligieron fuera de los RUC de sociedades (que empiezan con 80), sin confirmarlo en el padrón de la DNIT. Los RUC reales quedan solo en `tests/Unit/RucCheckDigitTest`, tomados de los rankings de contribuyentes de la DNIT de 2010 y 2011 (el algoritmo coincide con los 815) | Usuario |
| Cliente de demostración | Granja San Jorge S.R.L. pasa a `80024680-2`: el dígito `1` estaba mal calculado | Usuario |
| Tests | Los que sobrevivían a las mutaciones de la revisión ahora fallan al quitar su protección: argumentos en las trazas, verificador y su plazo, `#[\SensitiveParameter]`, `DEMO_PASSWORD` sin valor por defecto, dígito verificador (resto 0 y RUC de más de 10 dígitos, este calculado a mano porque no hay RUC reales tan largos), contraseña generada con un carácter de cada juego, entornos del seeder y emails eliminados de otra clínica | Usuario |
