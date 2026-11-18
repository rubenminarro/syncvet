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
([`fase-00-puesta-en-marcha.md`](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md)).

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
[`fase-00-puesta-en-marcha.md`](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md). Fuente: notas de sesión de Claude Code del
2026-11-03. Desde esta entrega la fuente de verdad son `docs/` y `CLAUDE.md`, no esas notas.

| Tema | Lo que se sabe |
|---|---|
| Archivo `docs/00-vision/oportunidades-producto.md` | Contenía las decisiones del Bloque 0.1 en su §7. Se perdió: no está en ningún commit (verificado con `git log --all` el 2026-11-04). Contenido: PENDIENTE DE VERIFICACIÓN |
| DP-0.1-04 | Decisión del Bloque 0.1, aprobada por el usuario y marcada como no modificable. Contenido: PENDIENTE DE VERIFICACIÓN |
| DP-0.1-05 | Decisión del Bloque 0.1, aprobada por el usuario y marcada como no modificable. Contenido: PENDIENTE DE VERIFICACIÓN |
| DP-0.1-01 a DP-0.1-03 y otras | Si existieron y en qué estado quedaron: PENDIENTE DE VERIFICACIÓN |
| Criterio de evidencia | En ese trabajo se distinguía lo verificado, lo publicitado (web o anuncio), lo referido y lo inferido, sin presentar interpretaciones normativas como hechos |
| Material que sí está en el repositorio | [`docs/prompts/prompt-fase0-syncvet.md`](../../03-plan-desarrollo/prompts/prompt-fase0-syncvet.md), [`docs/references/competitors.md`](../../00-vision/referencias/competitors.md) y tres anuncios en `docs/references/ads/` |
| Pendiente | Reconstruir DP-0.1-04, DP-0.1-05 y el §7 desde otra copia (por ejemplo, la conversación de revisión con ChatGPT) y registrarlos en una sección nueva |

# R-004 Respuestas de la FASE 0 y correcciones de la documentación (2026-11-04)

Decisiones del usuario del 2026-11-04, después del análisis previo a la FASE 0. Las respuestas de negocio están también en la
tabla de preguntas de [`fase-00-puesta-en-marcha.md`](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md).

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
[`fase-00-puesta-en-marcha.md`](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md) y responde sus preguntas.

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

Cierre de la entrega 0.1 ([`fase-00-puesta-en-marcha.md`](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md#entrega-01)), commits `11b82b3` y
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

Cierre de la entrega 0.2 ([`fase-00-puesta-en-marcha.md`](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md#entrega-02)), commits `fb52b73` y
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

Cierre de la entrega 0.3 ([`fase-00-puesta-en-marcha.md`](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md#entrega-03)), commits `2114244`,
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
| Have I Been Pwned | Se consulta por k-anonimato: solo viajan los 5 primeros caracteres del SHA-1. Si el servicio no responde, Laravel acepta la contraseña y reporta el error al log. En producción hace falta salida HTTPS a `api.pwnedpasswords.com` ([`deployment.md`](../../04-desarrollo/deployment.md)) | Usuario (la regla); comportamiento de Laravel |
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

# R-011 Segunda revisión de la entrega 0.4 de la FASE 0 (2026-11-05)

Correcciones de la segunda revisión, antes del cierre de la entrega. Completa R-010.

| Tema | Decisión | Quién |
|---|---|---|
| Have I Been Pwned en desarrollo | El `php.ini` de Laragon apuntaba `curl.cainfo` a un `cacert.pem` inexistente (`D:\Projects\Laragon-installer\…`): toda consulta fallaba con cURL error 77 y la regla de contraseñas aceptaba cualquiera, también `Password123!`. `curl.cainfo` y `openssl.cafile` pasan a `C:\laragon\etc\ssl\cacert.pem`. Es configuración de la máquina, fuera del repositorio; [`deployment.md`](../../04-desarrollo/deployment.md) pide comprobarlo en producción | Usuario |
| Aviso de Have I Been Pwned | El aviso del log lleva el código de error de cURL (`curl_error`) o el estado HTTP (`status`), además de la clase de la excepción. Nunca el mensaje: termina con la URL y el prefijo del hash | Usuario |
| Aviso en la consola | Si la contraseña escrita se aceptó sin consultar Have I Been Pwned, `clinic:create` (con interacción) y el `DemoSeeder` lo avisan en la consola (`UserPassword::leakCheckSkipped`). La contraseña generada de `clinic:create` no se consulta: no hay aviso | Usuario |
| Documentos de ejemplo | Todos ficticios: los RUC de clínicas y de clientes empresa son `999999xx` con su dígito verificador (Granja San Jorge S.R.L. pasa a `99999903-6`) y las cédulas de los clientes persona, `99xxxxxx` (`99100001`, `99100002`, `99200001`, `99200002`). El test del `DemoSeeder` exige esos prefijos | Usuario |
| Log de los tests | `phpunit.xml` fija `LOG_CHANNEL=null`: los tests no escriben en `storage/logs/laravel.log`. Los que revisan lo registrado escuchan `MessageLogged` o usan `Log::spy()`; el que necesite leer un log, configura su propio canal | Usuario |

# R-012 Entrega 0.4 de la FASE 0: alta de clínicas y datos de demostración (2026-11-05)

Cierre de la entrega 0.4: `php artisan clinic:create` y el `DemoSeeder`, con la regla de contraseñas (R-009) y las correcciones de
sus dos revisiones (R-010 y R-011). Commits `35d5089`, `d135459` y `e7047b2`, en `feature/fase-00-04-clinic-create`. El detalle está
en el [resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md#entrega-04).

| Tema | Decisión | Quién |
|---|---|---|
| Validación | Suite en verde sobre `e7047b2` en SQLite (222 casos, 3208 aserciones, 1 omitido) y en MySQL 8.4.3 (`syncvet_test_mysql`, 3222 aserciones). Validación manual del usuario sobre la base `syncvet`: `DEMO_PASSWORD`, `migrate:fresh --seed`, `DemoSeeder` sin avisos, `clinic:create` interactivo y la prueba con Postman | Usuario, el 2026-11-05 |
| Prueba con Postman de la 0.3 | Hecha, con alcance: login con un usuario de cada clínica, `me`, 404 al pedir un paciente de la otra clínica, 429 al sexto intento fallido, logout y 401 después. Las demás filas de la sección de la FASE 0 de la [guía](../../05-pruebas/guia-postman.md) no se recorrieron a mano: las respaldan los tests de `tests/Feature/Auth` y `tests/Feature/Tenancy` | Usuario |
| Bases de desarrollo anteriores a R-011 | El `DemoSeeder` falla sobre ellas (busca los clientes por su documento, que cambió, y choca con el email único). Se documenta en el resultado y en la guía (recrear la base); no se abre una deuda | Usuario |
| Instalación | [`setup.md`](../../04-desarrollo/setup.md) explica cómo apuntar `curl.cainfo` y `openssl.cafile` del `php.ini` de Laragon al `cacert.pem` que existe, y cómo comprobarlo | Usuario |
| Cédulas de ejemplo en los tests | Los tests de `tests/Feature/Tenancy` siguen con cédulas fuera del rango ficticio. Deuda 7, para la FASE 1 | Implementación (detectada en el cierre) |
| `CLAUDE.md` | §1 con el estado de la FASE 0 y los comandos; §3 con las reglas de contraseñas (R-009 y R-010) y de datos de ejemplo (R-011); §7 con la deuda 7 | Implementación |

**Qué no cambió.** Contrato de respuesta, `error_code`, códigos HTTP y rutas existentes; los 119 permisos y los tres roles.

# R-013 Revisión de la entrega 0.5 de la FASE 0 (2026-11-05)

Correcciones de la revisión de la entrega 0.5 (acceso por rol y verificación en MySQL), antes de su cierre. Cada protección nueva
se quitó de a una y hace fallar su test.

| Tema | Decisión | Quién |
|---|---|---|
| Rutas por método y URI | `InteractsWithApiRoutes` identifica cada ruta por sus métodos y su URI (`GET\|HEAD api/clients`), no por su nombre: una ruta sin permiso con el nombre de otra se pisaba con ella y pasaba toda la suite. `ROUTES_WITHOUT_PERMISSION` también usa método y URI. `ClinicRolesTest` exige que cada ruta de la API tenga un nombre propio | Usuario |
| Rutas sin permiso | Las de `ROUTES_WITHOUT_PERMISSION` responden 401 sin token, salvo las de `PUBLIC_ROUTES` (el login), que no lo piden | Usuario |
| Todos los métodos | La matriz de `RoleAccessTest` pide cada método de cada ruta (`PUT` y `PATCH`, y cualquier ruta con varios); `HEAD` no, porque Laravel lo agrega como el mismo `GET` | Usuario |
| Foto de los roles | `tests/Fixtures/clinic-roles.json` cubre al administrador (los 119 permisos) y se compara con los roles sembrados en la base. Roles y permisos van en orden alfabético y sin repetidos, y el test lo exige | Usuario |
| Documentos de ejemplo | `FictitiousDocumentsTest` revisa toda secuencia de 7 a 9 dígitos (con o sin comillas, puntos, espacios o dígito verificador) en `tests/` (con `tests/Fixtures`), `database/factories`, `database/seeders` y la guía de Postman. Ficticios: 9 dígitos; 8 que empiezan con 99; con dígito verificador, el correcto. Siete dígitos, nunca. Lo demás solo con una lista permitida por archivo y con motivo (los RUC reales de `RucCheckDigitTest`). La versión anterior solo veía `'ruc' => '...'` y `'document_number' => '...'`: no detectaba 7 de las 8 formas que encontró la revisión | Usuario |
| RUC de la `ClinicFactory` | De 9 dígitos (`100000000` a `999999999`) con su dígito verificador: hoy no se emiten RUC de 9 dígitos, y no chocan con los RUC `999999xx` de los tests ni con las cédulas `99xxxxxx`. Reemplaza el rango de 8 dígitos de R-010 (`90000000` a `99899999`), que contradecía R-011. `tests/Feature/Database/ClinicFactoryTest` revisa lo que genera | Usuario |
| `scopeBindings()` | Sin test: hoy ninguna ruta tiene dos parámetros anidados y quitarlo no cambia nada. Deuda 8, hasta la primera ruta así | Usuario |
| Hash de relleno del login | `AuthService::dummyHash()` usa el costo configurado (`BCRYPT_ROUNDS`), el de las contraseñas de los usuarios: con el de producción es el precalculado `DUMMY_HASH`, sin calcular nada; con otro (los tests usan 4), se calcula una vez por proceso. Antes, cada login con un email desconocido o un usuario eliminado verificaba un bcrypt de costo 12 también en los tests | Usuario |
| Velocidad de `RoleAccessTest` | Los roles y usuarios de prueba se crean antes de los pedidos, con los permisos asociados por id en un solo insert, y la caché de Spatie se vacía una vez, no después de cada pedido. La cobertura no cambió: las mutaciones de la entrega se repitieron y siguen fallando | Usuario |
| `sleep()` | No hay ninguno en `app/` ni en `tests/`: los tests de tiempo ya usan `travel` | Revisión |
| Tiempos | Antes: SQLite 33,1 s (236 casos) y MySQL 75,8 s. Después: SQLite 26,5 s y MySQL 51,7 s (242 casos). El primer test de la ejecución paga la migración inicial (unos 3 s en SQLite y de 20 a 34 s en MySQL); no se usa `schema:dump` porque las migraciones dejarían de probarse en MySQL | Implementación |

**Qué no cambió.** Contrato de respuesta, `error_code`, códigos HTTP y rutas existentes; los 119 permisos y los tres roles; la
regla del login (un hash verificado siempre, con el costo de las contraseñas).

# R-014 Entrega 0.5 y cierre de la FASE 0 (2026-11-05)

Cierre de la entrega 0.5 (acceso por rol y verificación en MySQL), con las correcciones de su revisión (R-013), y de la FASE 0
completa. Commits `8fe50cc` y `f87dbdd`, en `feature/fase-00-05-acceso`. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md#entrega-05) y en el
[cierre de la fase](../../03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md#cierre-de-la-fase-0).

| Tema | Decisión | Quién |
|---|---|---|
| Validación | Suite en verde sobre `f87dbdd` en SQLite (242 casos, 3910 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 3926 aserciones); el usuario la corrió en su máquina | Usuario, el 2026-11-05 |
| FASE 0 | COMPLETADO: las cinco entregas cerradas y el criterio de cierre de la fase cumplido, punto por punto en el cierre de la fase. Sin merge a `main` | Usuario |
| Permiso de cada ruta | Regla de `CLAUDE.md` §3: el permiso de cada ruta es su nombre (`recurso.acción`; en las anidadas, los dos últimos tramos), cada ruta tiene un nombre propio, y las excepciones van con su motivo. La exige `ClinicRolesTest` desde la 0.5 | Usuario |
| Suite en MySQL | Regla de `CLAUDE.md` §3: la suite completa corre en SQLite durante el trabajo, y en MySQL (`syncvet_test_mysql`) en la revisión, antes de cada cierre y siempre que se toquen migraciones, búsquedas de texto, concurrencia o autenticación. Reemplaza "probar cada migración nueva también en MySQL" | Usuario |
| Deuda 7 | Resuelta en la 0.5, antes de la FASE 1 prevista: cédulas ficticias en los tests y `FictitiousDocumentsTest` sobre toda secuencia de 7 a 9 dígitos | Usuario |
| Deudas abiertas | 1, 2, 3, 5, 6 y 8 ([`deuda-tecnica.md`](../../03-plan-desarrollo/deuda-tecnica.md)) | Implementación |
| Siguiente | FASE 1, clientes y pacientes: empieza por el análisis y el diseño (pasos 1 y 2), con autorización del usuario | Usuario |
| `CLAUDE.md` | §1 con el estado de la FASE 0 y los tests; §3 con las dos reglas nuevas (además de la de datos de ejemplo de R-013); §7 sin la deuda 7 y con la 8 | Implementación |

**Qué no cambió.** Contrato de respuesta, `error_code`, códigos HTTP y rutas existentes; los 119 permisos y los tres roles; las
reglas de negocio de clientes y pacientes.

# R-015 Respuestas de negocio y alcance de la FASE 1 (2026-11-05)

Respuestas del usuario a las preguntas de negocio de la FASE 1 (las tres del alcance inicial y las cinco que agregó el análisis), y
el alcance que suma a la fase. El diseño que las aplica está en [`fase-01-clientes-y-pacientes.md`](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md),
pendiente de aprobación.

| Tema | Decisión | Quién |
|---|---|---|
| Tutores | Un solo tutor responsable por paciente en el MVP (`patients.client_id`). Se revisa después del piloto | Usuario |
| Campos personalizados | Postergados: la columna `patients.custom_fields` queda, sin API ni pantalla, hasta que una clínica real los pida. Los Requests dejan de aceptarlos | Usuario |
| RUC del cliente | Se valida el dígito verificador cuando el documento tiene la forma `NNNNNNN-D`, con el cálculo de `ClinicService`. Una cédula sin guion no se valida | Usuario |
| Baja de especies y razas | `is_active` las oculta de las altas nuevas (los pacientes que ya las usan las siguen mostrando). Se eliminan con `SoftDeletes` solo si nunca se usaron; en uso, 409, como los demás datos maestros | Usuario |
| Nombres de especies y razas | Únicos sin distinguir mayúsculas: una clínica no crea una especie o raza con el nombre de una global ni de una propia, y tampoco se repiten entre globales | Usuario |
| Quién administra el catálogo | El administrador y el veterinario. Las razas van anidadas (`species/{species}/breeds`) con los permisos de especie. Con ellas llega el test de `scopeBindings()` y se cierra la deuda 8 | Usuario |
| Foto del paciente | JPG, PNG o WEBP de hasta 5 MB, en disco privado, descargada por la API con `nosniff`, sin redimensionar. La suben el recepcionista y el veterinario. Permiso según la regla "permiso = nombre de la ruta" | Usuario |
| Baja de clientes y pacientes | Sigue siendo solo del administrador. `CLIENT_IN_USE` y `PATIENT_IN_USE` se prueban con un rol propio con esos permisos | Usuario |
| Alcance sumado | Traducciones de validación en `lang/es` con voseo y un test que impida claves crudas (`validation.*`); voseo en los mensajes que quedaron en tú o usted; `SpeciesController` con un Resource; `insert()` y `upsert()` de `SharedCatalogBuilder` respetan la clínica; `PUT` y `PATCH` con la misma semántica parcial, aclarada en `CLAUDE.md` | Usuario |
| Documentación | Se corrigen las menciones a "sin merge a `main`" del handoff y del cierre de la FASE 0 (la fase se integró con `63b3eff`) y el `PAC-000001` del paso 5 de la FASE 1 en la guía de Postman | Usuario |

# R-016 Aprobación del diseño de la FASE 1 (2026-11-05)

El usuario aprueba el diseño de la FASE 1 ([`fase-01-clientes-y-pacientes.md`](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md)) con cinco
entregas (1.1 a 1.5) y responde las preguntas 9 a 19 del diseño.

| Tema | Decisión | Quién |
|---|---|---|
| Entregas | Cinco: 1.1 traducciones, voseo y `PUT`/`PATCH`; 1.2 clientes; 1.3 pacientes; 1.4 especies y razas; 1.5 foto del paciente. Reemplazan las cuatro del alcance inicial | Usuario |
| Nombres de especies y razas | Únicos sin distinguir mayúsculas ni acentos. Se comparan en la aplicación con el nombre normalizado (minúsculas y sin acentos), sin depender de la collation, para que SQLite y MySQL den el mismo resultado; test en las dos bases. Las razas, dentro de su especie | Usuario |
| Globales por clínica | Una clínica no puede ocultar para sí una especie o raza global en el MVP | Usuario |
| Foto del paciente | Queda fuera del bloqueo `PATIENT_DECEASED`. La suben y la borran el recepcionista y el veterinario; la ven los tres roles | Usuario |
| Contrato del paciente | Se quita `custom_fields` de la respuesta y `photo_path` se reemplaza por `has_photo` | Usuario |
| Sufijo de baja | Si un test confirma el choque, una migración nueva amplía `clients.document_number` y `patients.microchip` a 50 caracteres | Usuario |
| Archivos grandes | `413 PAYLOAD_TOO_LARGE` entre los `error_code` genéricos; `development/setup.md` documenta `upload_max_filesize` y `post_max_size` | Usuario |
| Paciente fallecido | Se sigue pudiendo cambiar su dueño | Usuario |
| Baja de una especie propia | Elimina también sus razas propias sin uso, en la misma transacción | Usuario |

# R-017 Entrega 1.1 de la FASE 1: traducciones, voseo y `PUT`/`PATCH` (2026-11-05)

Cierre de la entrega 1.1. Commit `0675c5b`, en `feature/fase-01-01-traducciones`. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#entrega-11).

| Tema | Decisión | Quién |
|---|---|---|
| Validación | Suite en verde sobre `0675c5b` en SQLite (260 casos, 5043 aserciones, 2 omitidos que son solo de MySQL), validada por el usuario en su máquina, y en MySQL 8.4.3 (`syncvet_test_mysql`, 5059 aserciones), corrida en el cierre | Usuario, el 2026-11-05 |
| Traducciones | `lang/es/validation.php` con todas las claves del framework y el nombre en español de cada campo; los mensajes propios de los Requests siguen mandando. Sin paquetes nuevos | Diseño (R-016), implementación |
| Voseo | Regla de `CLAUDE.md` §3: `VoseoTest` rechaza las formas de tú y de usted de su lista; todo campo de un Form Request lleva su nombre en `attributes` (`ValidationTranslationsTest`) | Implementación, validada por el usuario |
| `PUT` y `PATCH` | Regla de `CLAUDE.md` §3: `apiResource` registra los dos y los dos son parciales (`HttpVerbsTest`) | Diseño (R-016) |
| Hallazgos de la revisión | Cuatro huecos de los tests (lista cerrada de `VoseoTest` y mensajes en inglés, marcadores, nombres de los campos que solo valida un Controller, falsos positivos en tercera persona) quedan sin corregir en la entrega: el usuario la validó sin pedir cambios. Se registran como deudas 9 a 12, a resolver en la entrega 1.2 | Usuario |
| Siguiente | Entrega 1.2: clientes, con autorización del usuario | Diseño (R-016) |
| `CLAUDE.md` | §1 con el estado de la FASE 1 y la entrega 1.1; §3 con las dos reglas, ya en el commit de la entrega | Implementación |

**Qué no cambió.** Contrato de respuesta, `error_code`, códigos HTTP y rutas existentes; los 119 permisos y los tres roles; las
reglas de negocio de clientes y pacientes.

# R-018 Revisión de la entrega 1.2 de la FASE 1: documento por número base y filtros de los listados (2026-11-05)

La revisión de la entrega 1.2 (clientes) encontró seis hallazgos; el usuario pidió corregir los cinco primeros en la misma
entrega, en `feature/fase-01-02-clientes`.

| Tema | Decisión | Quién |
|---|---|---|
| Filtros de los listados | `per_page=abc`, `per_page=-3` y `search[]=x` daban 500 en clientes y pacientes, y `per_page` no tenía tope. `IndexClientRequest` e `IndexPatientRequest` validan los filtros comunes de `ListFilters`: `search` texto de hasta 100, `per_page` entero de 1 a 100 (10 por defecto) y `page` entero desde 1. Regla en `CLAUDE.md` §3 | Usuario |
| Tests de edición | Una empresa no acepta `first_name` ni `last_name`, una persona no acepta `business_name` y el email va en minúsculas también al editar: estaban probados solo en el alta | Usuario |
| Edición como el alta | Un campo obligatorio enviado vacío responde solo "El … es obligatorio." (antes, tres mensajes de formato). Los campos del otro tipo admiten `null`, también al cambiar de tipo, porque el frontend manda todos los campos; un valor sigue rechazado | Usuario |
| Documento por número base | En Paraguay el RUC de una persona es su cédula más el dígito verificador. Columna `clients.document_base` (dígitos antes del guion, sin ceros adelante; un documento con letras es su propia base, en mayúsculas), única por clínica y completada por el modelo al guardar. `99999903`, `099999903` y `99999903-6` son el mismo documento: 422 con "Este número de documento ya está en uso." La baja le agrega el mismo sufijo y la libera. Regla en `CLAUDE.md` §6.1 | Usuario |
| Migración | `2026_11_05_000001_add_document_base_to_clients` calcula la base de los clientes existentes. Si dos clientes de una clínica comparten la base, se detiene con sus ids antes de tocar la tabla (en MySQL el DDL no se deshace). Probada en MySQL 8.4 (`syncvet_test_mysql`): subir, bajar y subir, con datos previos y con un duplicado | Implementación |
| Sin corregir | El microchip se sigue cortando a 30 caracteres: con un chip ISO de 15 y un id de más de 5 dígitos, dos bajas pueden chocar. Queda para la entrega 1.3, que amplía la columna | Revisión, fuera del pedido |

**Qué no cambió.** Rutas, permisos, `error_code` y el contrato de respuesta. Cambia el código HTTP de un filtro mal formado
(de 500 a 422) y, al editar, los mensajes de un campo obligatorio vacío.

# R-019 Entrega 1.2 de la FASE 1: clientes (2026-11-05)

Cierre de la entrega 1.2. Commits `fff480a`, `54677d8` y `544f5cd`, en `feature/fase-01-02-clientes`. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#entrega-12).

| Tema | Decisión | Quién |
|---|---|---|
| Validación | Suite en verde sobre `544f5cd` en SQLite (368 casos, 5371 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 5387 aserciones), corrida en el cierre. El usuario validó la entrega con la colección de Postman contra `syncvet` recreada con el `DemoSeeder`: 48 pruebas en verde | Usuario, el 2026-11-05 |
| "Errors 1" del Collection Runner | No se reprodujo: Newman, sobre `syncvet` recreada, corrió los 24 requests sin errores de script, y el log de la API no tiene errores. Queda PENDIENTE DE VERIFICACIÓN; la colección no cambió | Cierre |
| Reglas de clientes | Dígito verificador del RUC (R-015), documento único por número base y edición que valida como el alta (R-018): `CLAUDE.md` §6.1 | Usuario |
| Sufijo de baja | `//deleted_<id>_<timestamp>`, sin cortar en clientes (documento a 50 caracteres, pregunta 15); el microchip se amplía en la 1.3 | Diseño (R-016), implementación |
| Deudas | 9 a 12 resueltas. La 1 sigue abierta solo para pacientes: se cierra con la 1.3 | Diseño (R-016) |
| Preparación | `development/setup.md` documenta `DEMO_PASSWORD` entre comillas simples y recrear la base después de cambiar a una rama con migraciones nuevas: en la validación, el login dio 500 por una base vieja | Usuario |
| Siguiente | Entrega 1.3: pacientes, con autorización del usuario | Diseño (R-016) |

**Qué no cambió.** Rutas, permisos, `error_code` y el contrato de respuesta, salvo lo que detalla R-018.

# R-020 Entrega 1.3 de la FASE 1: pacientes (2026-11-06)

Cierre de la entrega 1.3, en `feature/fase-01-03-pacientes` (creada desde `feature/fase-01-02-clientes`). Segundo intento: el
primero quedó sin subir en otra computadora y se descartó; sus hallazgos, aprobados por el usuario, se aplicaron directamente. El
detalle está en el [resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#entrega-13).

| Tema | Decisión | Quién |
|---|---|---|
| Tests | Cada regla de `CLAUDE.md` §6.2 en `tests/Feature/Patients`, con el rol recepcionista (la baja, con un rol de test con `patients.destroy`). Deuda 1 resuelta | Diseño (R-016) |
| `code` | Prohibido también en el alta (422): antes se ignoraba | Usuario |
| `custom_fields` | Los Requests no lo aceptan (si llega, se ignora) y la respuesta no lo muestra (R-015, pregunta 14) | Usuario |
| Especie o raza inactiva al editar | Se acepta la que el paciente ya tiene; otra inactiva → 422 | Diseño (R-016) |
| Microchip | El sufijo de baja lleva el id y no se corta; la columna pasa a 60 caracteres (30 del chip, el sufijo y el id), no a 50 como decía la pregunta 15. Migración probada en `syncvet_test_mysql`: subir, bajar y subir | Usuario |
| `deceased_at` | Llegaba en UTC y se guardaba 3 horas corrida: `PatientService` lo convierte a `America/Asuncion`, con test. Resolver la conversión de todas las fechas con hora en un lugar común antes de la FASE 3: deuda 13 | Usuario |
| Cambio de dueño de un fallecido | Permitido (pregunta 17) | Usuario |
| `photo_path` | Sigue en la respuesta hasta la 1.5 (la pregunta 14 lo reemplaza por `has_photo` allí) | Usuario |
| `PATIENT_IN_USE` | Solo en los tests: la API todavía no crea consultas, aplicaciones ni turnos | Usuario |
| Postman | Colección de pacientes, y la de clientes repetible: el primer request genera documentos, emails y microchip únicos de la corrida | Usuario |
| Validación | Suite en verde en SQLite (434 casos, 5579 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 5595 aserciones). Newman: cada colección dos veces seguidas sin recrear la base, todo en verde | Implementación |
| Siguiente | Entrega 1.4: especies y razas, con autorización del usuario | Diseño (R-016) |

**Qué no cambió.** Rutas, permisos, `error_code` y el contrato de respuesta, salvo `custom_fields` fuera del detalle del paciente
y `code` en el alta, que ahora responde 422.

# R-021 Revisión de la entrega 1.3 de la FASE 1: correcciones (2026-11-06)

La revisión de la entrega 1.3 (pacientes) encontró dos hallazgos de gravedad media, cuatro de baja y dos avisos; el usuario pidió
corregir los ocho en `feature/fase-01-03-pacientes`, cada uno con un test que falle al quitar la protección. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#correcciones-de-la-revisión-r-021).

| Tema | Decisión | Quién |
|---|---|---|
| Fallecido con el formulario completo | `ensureNotDeceased` compara valores, no nombres de campo: los campos bloqueados pueden llegar con el valor que ya tienen (con los casts del modelo: "12.5" y "12.50" son iguales); se rechaza solo si cambian. Regla en `CLAUDE.md` §6.2 | Usuario |
| Test del sufijo más largo | No guarda un paciente con id 999999999: en MySQL el autoincremento no vuelve atrás con la transacción del test, y los pacientes siguientes de la suite recibían ids de 10 dígitos (la baja de un chip de 30 daba 500). El sufijo se arma con el id como dato, y el valor de 60 caracteres se guarda en una fila existente | Usuario |
| Fallecimiento anterior al nacimiento | 422 en `deceased_at` con "La fecha de fallecimiento no puede ser anterior a la fecha de nacimiento.", contra la fecha de nacimiento del pedido o la del paciente, comparando el día en la hora de Asunción (`UpdatePatientRequest::after`). Regla nueva en `CLAUDE.md` §6.2 | Usuario |
| Listado de los pacientes de un cliente | `clients/{client}/patients` oculta a los fallecidos salvo `include_deceased=1`, validado en `IndexClientPatientRequest`, como el listado general. Regla aclarada en `CLAUDE.md` §6.2 | Usuario |
| Búsqueda literal | `search=%` devolvía todo el listado. `whereContains`/`orWhereContains` (`App\Support\SearchTerm`) escapan `%` y `_` con `ESCAPE '!'`, el mismo en SQLite y MySQL; la barra invertida queda como un carácter más. En los listados de clientes y de pacientes. Regla en `CLAUDE.md` §3 | Usuario; el carácter de escape, implementación |
| Postman | Cada colección genera sus documentos en un rango propio (clientes, corrida de 500000 a 749998; pacientes, de 750000 a 999998). La guía remite a las colecciones y ya no tiene tablas de pasos propias: la tabla manual de la FASE 1 creaba a Toby en la clínica A y rompía la colección de clientes | Usuario |
| Documentos | `README.md` con las 31 migraciones actuales; el handoff con el hash del checkpoint | Usuario |
| Validación | Suite en verde en SQLite (449 casos, 5626 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 5642 aserciones). Newman: cada colección dos veces seguidas sin recrear la base, todo en verde | Implementación |

**Qué no cambió.** Rutas, permisos y `error_code`. Cambian: un fallecido acepta el formulario completo sin cambios; un fallecimiento
anterior al nacimiento responde 422; `clients/{client}/patients` oculta a los fallecidos (antes los mostraba) y acepta
`include_deceased`; `search` con `%` o `_` ya no funciona como comodín.

# R-022 Entrega 1.3 de la FASE 1: validación y cierre (2026-11-06)

Cierre de la entrega 1.3 (pacientes). Commits `fd2334f` (implementación, R-020), `837484a` (correcciones de la revisión, R-021) y
`be47879` (hash del checkpoint en el handoff), en `feature/fase-01-03-pacientes`. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#validación-del-usuario-r-022).

| Tema | Decisión | Quién |
|---|---|---|
| Validación | El usuario corrió en Postman las dos colecciones contra `syncvet`: clientes, 48 de 48 pruebas; pacientes, 61 de 61; sin errores. Entrega validada | Usuario, el 2026-11-06 |
| Suite del cierre | Sobre `be47879` (el código de `837484a`): 449 casos en verde; en SQLite, 5626 aserciones con 2 omitidos (solo de MySQL); en MySQL 8.4.3 (`syncvet_test_mysql`), 5642 aserciones. Newman: cada colección dos veces seguidas sin recrear la base, todo en verde | Cierre |
| Protecciones quitadas de a una | No se repiten en el cierre: valen las de la implementación y las de las correcciones (seis y seis) | Usuario |
| Revisión | Se hizo en la misma sesión de Claude Code que la implementación, no en una sesión aparte. Queda anotado en el resultado de la entrega | Usuario |
| `demo_password` en Postman | La guía indica guardarla en un Environment "SyncVet local": reimportar una colección la reemplaza con su `demo_password` vacía, y un Environment tiene prioridad sobre las variables de la colección y no se toca al reimportar | Usuario |
| Pacientes | El módulo sigue EN DESARROLLO: falta la foto del paciente (entrega 1.5) | Cierre |
| Siguiente | Entrega 1.4: especies y razas, con autorización del usuario | Diseño (R-016) |

**Qué no cambió.** Código, rutas, permisos, `error_code`, contrato de respuesta y colecciones: el cierre solo documenta.

# R-023 Entrega 1.4 de la FASE 1: especies y razas (2026-11-06)

Implementación de la entrega 1.4 en `feature/fase-01-04-especies` (creada desde `feature/fase-01-03-pacientes`). Falta la revisión
y la validación del usuario: no es el cierre. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#entrega-14).

| Tema | Decisión | Quién |
|---|---|---|
| ABM | `species` y `species/{species}/breeds` con alta, edición (`PATCH`/`PUT` parciales), inactivación y baja; sin detalle (`show`). `SpeciesController` reescrito con `SpeciesResource`; `BreedController` y `BreedResource` nuevos | Diseño (R-016) |
| Baja | `is_active` oculta de las altas nuevas; `SoftDeletes` solo sin uso (pacientes, también eliminados): 409 `SPECIES_IN_USE` o `BREED_IN_USE`. La baja de una especie se lleva sus razas | Usuario (R-015, pregunta 18) |
| Nombre único | Sin mayúsculas, acentos ni espacios de los extremos (`App\Support\CatalogName`), comparado en la aplicación; la especie frente a las globales y a las propias, la raza dentro de su especie. 422 `VALIDATION_ERROR` en `name`, desde el Service, con la fila de la clínica bloqueada (`CatalogLock`; orden de bloqueo clínica → catálogo) | Usuario (preguntas 9 y 10) |
| Globales | No se editan, no se inactivan ni se eliminan (403 `CATALOG_GLOBAL_NOT_EDITABLE`, antes que el nombre repetido); no se ocultan por clínica | Usuario (pregunta 11) |
| Permisos | `species.destroy` nuevo (119 → 120). El veterinario suma `species.store`, `species.update` y `species.destroy`; el recepcionista, solo `species.index`. Las cuatro rutas de razas, en `ROUTES_WITH_ANOTHER_PERMISSION` con los permisos de especie. Foto de roles actualizada | Usuario (pregunta 6) |
| `SharedCatalogBuilder` | Las altas masivas fijan la clínica en contexto en cada fila y rechazan otra o un global explícito; `upsert` no actualiza `clinic_id`; `insertUsing` e `insertOrIgnoreUsing` fallan siempre; sin clínica, fallo cerrado | Diseño (R-016) |
| `upsert` con el id | Rechazado (en las filas o en la clave de unicidad): `ON DUPLICATE KEY UPDATE` no admite un filtro por clínica y un upsert por id actualizaría un global o un registro de otra clínica | Implementación |
| Largo del nombre | Se valida hasta 60 (especie) y 80 (raza), como el diseño; las columnas pasan a 90 y 110 (no quedan en 80 y 100) para el sufijo de baja con el id (`//deleted_<id>_<timestamp>`, R-021) | Implementación |
| Formato del nombre | Letras, números, espacios, puntos, guiones, apóstrofos y paréntesis; sin `/`, que es del sufijo de baja | Implementación |
| Deuda 8 | Resuelta: `NestedBindingTest` pide una raza de otra especie de la misma clínica y espera 404; falla sin `scopeBindings()` | Diseño (R-016) |
| Concurrencia | `CatalogLockTest` verifica que el alta y el cambio de nombre bloquean la clínica (`for update` en MySQL) antes de leer los nombres, dentro de la transacción del Service. No simula dos pedidos simultáneos: PHPUnit corre con una sola conexión dentro de la transacción de `RefreshDatabase` | Implementación |
| Postman | Colección de especies y razas, repetible, con su propio rango de corrida (400000 a 499998). En una base que no se recrea hay que volver a sembrar los roles (`RolesAndPermissionsSeeder`) | Usuario |
| Validación | Suite en verde en SQLite (557 casos, 6330 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 557 casos, 6346 aserciones). Migración probada en MySQL: subir, bajar y subir. Newman: la colección de especies dos veces seguidas sin recrear la base (38 requests y 84 aserciones cada vez), y las de clientes y pacientes otra vez, todo en verde | Implementación |
| Siguiente | Revisión y validación de la 1.4; después, la 1.5 con autorización del usuario | Diseño (R-016) |

**Qué cambió en la API.** Rutas nuevas: `POST species`, `PATCH`/`PUT`/`DELETE species/{species}` y las cuatro de
`species/{species}/breeds`. `GET species` conserva su forma (`id`, `name`, `breeds[].id`, `breeds[].name`) y suma `is_active`,
`is_global` y, en cada raza, `species_id`, `is_active` e `is_global`; acepta `include_inactive`. El alta y la edición de pacientes
rechazan una especie o raza eliminada (422). Sin cambios en los `error_code` existentes ni en el contrato de respuesta.

# R-024 Revisión de la entrega 1.4 de la FASE 1: correcciones (2026-11-06)

La revisión de la entrega 1.4 (especies y razas) encontró un hallazgo de gravedad media, cuatro de baja y tres de documentación; el
usuario aceptó los desvíos de la implementación y pidió corregir los hallazgos en `feature/fase-01-04-especies`, cada uno con un
test que falle al quitar la protección. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#correcciones-de-la-revisión-r-024).

| Tema | Decisión | Quién |
|---|---|---|
| Baja y alta de paciente simultáneas | La baja de una especie bloquea la especie y sus razas (`lockForUpdate`), y la de una raza, la raza, y revisan el uso dentro de su transacción. El alta y la edición de un paciente toman un `sharedLock` sobre la especie y la raza dentro de la suya y las vuelven a buscar: si una baja terminó antes, 422 en `species_id` o `breed_id` con el mensaje de la regla `exists`. Orden de bloqueo nuevo en `CLAUDE.md` §3: especie → raza → serie | Usuario |
| Tests de la carrera | Una sola conexión no puede esperar un bloqueo: el test pone el otro pedido en el único momento en que puede terminar sin esperar (el paciente antes del bloqueo de la baja → 409; la baja antes del bloqueo del paciente → 422), y en MySQL exige `for update` y `lock in share mode` en ese orden. El alta dentro del evento `deleting` de la revisión no se reproduce tal cual: con el bloqueo, en una base real ese alta espera | Implementación |
| Globales | 403 `CATALOG_GLOBAL_NOT_EDITABLE` antes de validar el cuerpo: lo revisa `authorize()` del Form Request de edición; el Service lo sigue revisando | Usuario |
| Nombre con letras | `CatalogMessages::NAME_REGEX` exige al menos una letra; el mensaje lo dice ("debe tener al menos una letra y solo puede contener…") | Usuario; el texto del mensaje, implementación |
| Espacios internos | `CatalogName::clean` junta toda serie de espacios (también tabs y espacios duros) en uno y quita los de los extremos. Se aplica al guardar (el nombre en `Species` y `Breed`) y al comparar (`normalize`) | Usuario; el lugar (el modelo), implementación |
| `insert([])` | `insert` e `insertOrIgnore` de `SharedCatalogBuilder` sin filas no hacen nada, como en Laravel | Usuario |
| Documentos | `README.md` con 120 permisos; `CLAUDE.md` §6.2 con especie y raza no eliminadas; el handoff con `8a614c5` como el commit de la implementación (un commit no puede llevar su propio hash: el de estas correcciones se informa al subirlo) | Usuario |
| Postman | La colección de especies suma el nombre sin letras, la global con un cuerpo inválido y la global con dos espacios internos, y comprueba los espacios juntados en un alta (41 requests, 93 aserciones) | Implementación |
| Validación | Suite en verde en SQLite (577 casos, 6449 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 6465 aserciones). Quince protecciones quitadas de a una, cada una con un test que falla. Newman: especies dos veces seguidas sin recrear la base, y clientes y pacientes, todo en verde | Implementación |

**Qué cambió en la API.** Rutas, permisos, `error_code` y contrato de respuesta, sin cambios. Cambian: una global con un cuerpo
inválido responde 403 (antes, 422); un nombre sin letras responde 422; los espacios internos repetidos se guardan como uno y
cuentan para el nombre repetido; un alta o edición de paciente simultánea con la baja de su especie o raza responde 422.

# R-025 Entrega 1.4 de la FASE 1: validación y cierre (2026-11-06)

Cierre de la entrega 1.4 (especies y razas). Commits `8a614c5` (implementación, R-023) y `aadc1d9` (correcciones de la revisión,
R-024), en `feature/fase-01-04-especies`. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#validación-del-usuario-r-025).

| Tema | Decisión | Quién |
|---|---|---|
| Validación | El usuario corrió en Postman las tres colecciones contra `syncvet`: especies, 93 de 93 pruebas, dos veces; pacientes, 61 de 61; clientes, 48 de 48; sin errores. Entrega validada | Usuario, el 2026-11-06 |
| Suite del cierre | No se repitió: el código no cambió desde la corrida de las correcciones (`aadc1d9`): 577 casos en verde; en SQLite, 6449 aserciones con 2 omitidos (solo de MySQL); en MySQL 8.4.3 (`syncvet_test_mysql`), 6465 aserciones | Cierre |
| Hash en el handoff | Queda como lo dejaron las correcciones: el último commit de código es el hijo de `8a614c5`, sin su hash; no se agrega un commit solo para escribirlo | Usuario |
| Revisión | Se hizo en la misma sesión de Claude Code que la implementación, no en una sesión aparte (anotado en el resultado de la entrega) | Cierre |
| Especies y razas | El módulo pasa a COMPLETADO: la 1.4 era su última entrega en el diseño (R-016) | Cierre |
| Siguiente | Entrega 1.5: foto del paciente, con autorización del usuario | Diseño (R-016) |

**Qué no cambió.** Código, rutas, permisos, `error_code`, contrato de respuesta y colecciones: el cierre solo documenta.

# R-026 Entrega 1.5 de la FASE 1: foto del paciente (2026-11-06)

Implementación de la entrega 1.5 en `feature/fase-01-05-foto` (creada desde `feature/fase-01-04-especies`). Falta la revisión y
la validación del usuario: no es el cierre. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#entrega-15).

| Tema | Decisión | Quién |
|---|---|---|
| Rutas | `POST`, `GET` y `DELETE patients/{patient}/photo`, con `PatientPhotoController` y `PatientPhotoService`. Subir y borrar responden `ShowPatientResource`; ver responde el archivo | Diseño (R-016) |
| Permisos | `patients.upload-photo`, `patients.show-photo` y `patients.delete-photo` (120 → 123). El recepcionista y el veterinario, los tres; el administrador, por `Gate::before`. Foto de los roles actualizada | Usuario (preguntas 7 y 13) |
| Tipo y tamaño | JPG, PNG o WEBP de hasta 5 MB, por el contenido (`image` y `mimetypes`, que leen los bytes); otro tipo → 422. Más grande → 413 `PAYLOAD_TOO_LARGE`, como el pedido cortado por `post_max_size` o el archivo cortado por `upload_max_filesize` | Usuario (preguntas 7 y 16; el 413 de la foto grande, al autorizar la entrega) |
| 413 genérico | `bootstrap/app.php` mapea el 413 a `PAYLOAD_TOO_LARGE`; `development/setup.md` documenta `upload_max_filesize` y `post_max_size` | Usuario (pregunta 16) |
| Almacenamiento | Disco `local` (privado), `clinics/{clinic_id}/patients/{patient_id}/{uuid}.{ext}`, con la extensión del tipo detectado; sin redimensionar | Diseño (R-015 y R-016) |
| Reemplazo y borrado | `photo_path` se lee con la fila del paciente bloqueada y se cambia en una transacción; el archivo anterior se borra después del commit (`DB::afterCommit`); si la transacción falla, se borra el nuevo | Diseño; el bloqueo, implementación |
| Descarga | El archivo, con `Content-Type` del tipo guardado, `X-Content-Type-Options: nosniff`, `Content-Disposition: inline` con el código del paciente y `Cache-Control: private, no-store`. La única respuesta exitosa sin `ApiResponse` (`CLAUDE.md` §4) | Usuario (pregunta 7) |
| Sin foto o archivo perdido | 404 `PATIENT_PHOTO_NOT_FOUND`; el archivo perdido deja un aviso en el log | Diseño (R-016) |
| Fallecido | Sube y borra la foto, sin `PATIENT_DECEASED`: no es un dato clínico | Usuario (pregunta 12) |
| Contrato | `has_photo` en `PatientResource` y en el detalle; `photo_path` ya no sale de la API. La colección de pacientes cambia ese paso | Usuario (pregunta 14) |
| Otra clínica | Un paciente de otra clínica responde 404 `RESOURCE_NOT_FOUND` en las tres rutas (scope de la clínica en el *route binding*) | Diseño (R-016) |
| Postman | Colección de la foto, repetible, con su rango de corrida (000000 a 099998, documento `990…`) y dos archivos versionados en `docs/testing/postman/archivos`; la carpeta de trabajo de Postman y Newman es la raíz del repositorio | Usuario |
| Validación | Suite en verde en SQLite (606 casos, 6684 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 6700 aserciones). Doce protecciones quitadas de a una, cada una con un test que falla. Newman: la colección de la foto dos veces seguidas sin recrear la base (23 requests y 60 aserciones cada vez), y las de especies, clientes y pacientes, todo en verde | Implementación |
| Siguiente | Revisión y validación de la 1.5; con su cierre, la FASE 1 queda completa | Diseño (R-016) |

**Qué cambió en la API.** Rutas nuevas: las tres de `patients/{patient}/photo`. El paciente deja de mostrar `photo_path` y muestra
`has_photo`. Un pedido que supera `post_max_size` responde 413 `PAYLOAD_TOO_LARGE` (antes, `HTTP_ERROR`). `error_code` nuevo:
`PATIENT_PHOTO_NOT_FOUND`.

# R-027 Revisión de la entrega 1.5 de la FASE 1: correcciones (2026-11-06)

La revisión de la entrega 1.5 (foto del paciente), con foco en la seguridad de los archivos subidos, encontró cuatro hallazgos de
gravedad baja y dos de documentación; el usuario aceptó los desvíos de la implementación y pidió corregirlos en
`feature/fase-01-05-foto`, cada uno con un test que falle al quitar la protección. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#correcciones-de-la-revisión-r-027).

| Tema | Decisión | Quién |
|---|---|---|
| Disco | Disco propio `patient_photos` en `storage/app/private/patient-photos`, sin `serve`. `local` también pasa a `serve => false`: su raíz contiene la carpeta de las fotos, y con `serve` Laravel las serviría por `/storage/patient-photos/...` con una URL firmada, sin token ni clínica. Nadie usa `temporaryUrl()` | Usuario; `serve` en `local`, implementación |
| `photo_path` | Fuera de `$fillable` de `Patient`: solo lo cambia `PatientPhotoService`. Ver, borrar y reemplazar lo usan solo si es un archivo de la carpeta del paciente (`clinics/{clinic_id}/patients/{id}/` y un nombre `{uuid}.{jpg,png,webp}`); si no, 404 `PATIENT_PHOTO_NOT_FOUND` y un aviso en el log | Usuario; el nombre exigido y que el reemplazo no borre un archivo ajeno, implementación |
| Volver a codificar (cambia R-015) | Al subir, la imagen se vuelve a codificar con GD en su mismo formato (`ImageReencoder`): se pierden los metadatos (EXIF, también el GPS) y lo que venga pegado después de la imagen. Si GD no la decodifica, 422 con el mensaje de tipo. Sigue sin redimensionar. GD con JPEG, PNG y WEBP es obligatoria (`development/setup.md`) | Usuario |
| Orientación | La orientación del EXIF se aplica a los píxeles antes de descartarlo (extensión `exif`): sin eso, una foto vertical de un teléfono quedaría acostada | Implementación |
| Megapíxeles | Más de 25 megapíxeles (según el encabezado, antes de decodificar) → 422 "La foto no debe superar los 25 megapíxeles.": una imagen chica en bytes puede ocupar gigas al decodificarse. `memory_limit` de al menos 256M | Implementación; a confirmar por el usuario |
| Calidad | JPEG y WEBP con calidad 90; PNG y WEBP conservan la transparencia. La imagen se decodifica una vez, en el `after()` del Form Request, y el Service guarda los bytes nuevos | Implementación |
| Descarga | Suma `Content-Security-Policy: default-src 'none'; sandbox` | Usuario |
| Borrado que falla | Un borrado de archivo que falla (después del commit, o el del nuevo si la transacción falla) deja un aviso en el log | Usuario |
| Deuda 14 | Archivos huérfanos: la baja del paciente deja su foto, y un proceso que muere antes del commit deja el archivo nuevo. Limpieza programada más adelante | Usuario |
| Documentos | `development/setup.md` (disco propio, sin `serve`, y GD y `exif` obligatorias) y el handoff (la 1.5 no trae migraciones) | Usuario |
| Validación | Suite en verde en SQLite (616 casos, 6756 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 6772 aserciones). Once protecciones quitadas de a una, cada una con un test que falla. Newman: la colección de la foto dos veces seguidas sin recrear la base y la de pacientes, en verde | Implementación |

**Qué cambió en la API.** Rutas, permisos, `error_code` y contrato de respuesta, sin cambios. Cambian: la foto guardada (y
descargada) es la imagen vuelta a codificar, sin metadatos; una imagen que GD no decodifica o de más de 25 megapíxeles responde
422 `VALIDATION_ERROR` en `photo`; la descarga suma `Content-Security-Policy`; `photo_path` ya no se puede asignar en masa; la ruta
`/storage/{path}` de Laravel deja de existir.

# R-028 Entrega 1.5 y cierre de la FASE 1 (2026-11-06)

Cierre de la entrega 1.5 (foto del paciente), con las correcciones de su revisión (R-027), y de la FASE 1 completa. Commits
`1650605` (implementación, R-026) y `79f7e8e` (correcciones, R-027), en `feature/fase-01-05-foto`. El detalle está en la
[validación de la entrega](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#validación-del-usuario-r-028) y en el
[cierre de la fase](../../03-plan-desarrollo/fases/backend/fase-01-clientes-y-pacientes.md#cierre-de-la-fase-1).

| Tema | Decisión | Quién |
|---|---|---|
| Validación | El usuario corrió en Postman, contra `syncvet` y sobre `79f7e8e`, la colección de la foto (60 de 60) y la de pacientes (61 de 61), sin errores. Entrega validada | Usuario, el 2026-11-06 |
| Decisiones de R-027 | Confirmadas: la orientación del EXIF aplicada a los píxeles, el límite de 25 megapíxeles y la calidad 90 | Usuario |
| Suite del cierre | MySQL 8.4.3 (`syncvet_test_mysql`) corrida otra vez en el cierre: 616 casos, 6772 aserciones, sin omitidos. SQLite, de la corrida de las correcciones sobre el mismo código: 616 casos, 6756 aserciones, 2 omitidos (solo de MySQL) | Cierre |
| Revisión | Se hizo en la misma sesión de Claude Code que la implementación, no en una sesión aparte (anotado en el resultado de la entrega) | Cierre |
| Pacientes | El módulo pasa a COMPLETADO: la 1.5 era su última entrega en el diseño (R-016) | Cierre |
| FASE 1 | COMPLETADO: las cinco entregas cerradas y el criterio de cierre de la fase cumplido, punto por punto en el cierre de la fase. Sin merge a `main`: lo autoriza el usuario | Usuario |
| Deudas abiertas | 2, 3, 5, 6, 13 y 14 ([`deuda-tecnica.md`](../../03-plan-desarrollo/deuda-tecnica.md)). Resueltas en la fase: 1, 8, 9, 10, 11 y 12 | Cierre |
| Siguiente | FASE 2, datos maestros: empieza por el análisis (paso 1) y sigue con el diseño (paso 2), con autorización del usuario | Usuario |
| Documentos | `CLAUDE.md` §1 con la FASE 1 completada; `README.md` con el estado, sin el pendiente de los tests de clientes y pacientes (deuda 1, resuelta) y con `gd` y `exif` en los requisitos (R-027) | Cierre |
| Merge a `main` | La FASE 1 se integró a `main` con el merge `7b35ea2` (`feature/fase-01-05-foto`, que contiene el diseño y las cinco entregas encadenadas), autorizado por el usuario el 2026-11-06. Agregado en el diseño de la FASE 2 (R-029): el handoff y el cierre de la fase seguían diciendo "sin merge a `main`" | Usuario |

**Qué no cambió.** Código, rutas, permisos, `error_code`, contrato de respuesta y colecciones: el cierre solo documenta.

# R-029 Respuestas de negocio y alcance de la FASE 2 (2026-11-06)

Respuestas del usuario a las preguntas del análisis de la FASE 2 (paso 1), que incluyen las tres del alcance inicial (P5, P8 y P11)
y la de usuarios inactivos que la FASE 0 dejó para esta fase. El diseño que las aplica está en
[`fase-02-datos-maestros.md`](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md), pendiente de aprobación. Las preguntas que el diseño no puede
decidir solo están al final de ese documento.

| Tema | Decisión | Quién |
|---|---|---|
| P1 Usuarios inactivos | `users.is_active`, con una migración. Un usuario inactivo no entra: con la contraseña correcta, 403 "usuario inactivo"; con una incorrecta, el error genérico de siempre (`INVALID_CREDENTIALS`). Al inactivarlo se revocan todos sus tokens. La baja (`SoftDeletes` con el sufijo `//deleted_`) solo sin uso; con uso, 409 | Usuario |
| P2 Quién gestiona usuarios | Solo el administrador | Usuario |
| P3 Roles | Un usuario puede tener varios roles | Usuario |
| P4 Administradores | Un administrador no se inactiva ni se da de baja a sí mismo, y nadie deja la clínica sin un administrador activo: 409 | Usuario |
| P5 Veterinario y sucursales | Trabaja en las sucursales de su usuario (`branch_user`), sin una tabla aparte | Usuario |
| P6 Veterinario | Necesita un usuario con el rol `veterinario`. En uso, se inactiva; sin uso, se da de baja, y si se vuelve a dar de alta al mismo usuario se restaura el anterior en lugar de crear uno nuevo (`veterinarians.user_id` es único) | Usuario |
| P7 Comisión | `commission_percentage` se guarda; no se calcula nada hasta los reportes | Usuario |
| P8 Precios | Un precio único por servicio (`standard_price`). La factura guardará su precio | Usuario |
| P9 Sucursales | `establishment` se edita solo mientras la sucursal no tenga facturas (en esta fase siempre se puede; el punto queda preparado). Sucursales y depósitos solo se inactivan. La sucursal y el depósito por defecto no se inactivan | Usuario |
| P10 Depósitos | Una sucursal tiene varios depósitos, uno por defecto | Usuario |
| P11 Datos de la clínica | Se guardan la razón social, el nombre de fantasía y el RUC de la clínica, y la dirección y el teléfono de cada sucursal. El timbrado, la actividad económica y el punto de expedición quedan para la fase de SIFEN | Usuario |
| P12 Logo | Se muestra después del login (en `me` y en la configuración). No se agrega un slug a `clinics` | Usuario |
| P13 Configuración | `system_settings` con una lista cerrada de claves (Enum) y validación por clave | Usuario |
| P14 Auditoría | La ve solo el administrador, con filtros por fecha, usuario, evento y registro, sin valores sensibles, filtrada por la clínica. Los `LOGIN_FAILED` sin clínica no se muestran | Usuario |
| P15 Fotos huérfanas (deuda 14) | La foto de un paciente dado de baja se conserva. El comando de limpieza solo borra archivos que no sean el `photo_path` ni el logo de nadie | Usuario |
| Email del usuario | Único en toda la base (es el login): en el MVP, una persona que trabaja en dos clínicas necesita dos emails | Usuario |
| Entregas | En el orden del análisis: 2.1 sucursales y depósitos, 2.2 usuarios (dividida en 2.2a y 2.2b), 2.3 veterinarios, 2.4 servicios, 2.5 configuración y logo con la deuda 14, 2.6 auditoría con la deuda 13 | Usuario; la división de la 2.2, diseño |
| Documentación | Se corrigen el handoff, el cierre de la FASE 1 y R-028 con el merge `7b35ea2` de la FASE 1 a `main` | Usuario |
| Aprobación del diseño | El usuario aprueba el diseño de la FASE 2 tal como está ([`fase-02-datos-maestros.md`](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md)), con las siete entregas y las preguntas 16 a 31 del diseño según la propuesta de cada una (códigos de sucursal y depósito escritos por el administrador, depósito por defecto en el alta de la sucursal, `PATCH me/password`, precio entero, claves de `system_settings`, RUC no editable, logo de hasta 2 MB, limpieza a las 24 horas, matrícula a 60 caracteres, `USER_IS_VETERINARIAN`, entre otras). Sigue la entrega 2.1 en `feature/fase-02-01-sucursales`, creada desde `docs/fase-02-diseno` | Usuario, el 2026-11-06 |

**Qué no cambió.** Código, rutas, permisos, `error_code` y contrato de respuesta: el diseño y su aprobación solo documentan.

# R-030 Entrega 2.1 de la FASE 2: sucursales y depósitos (2026-11-07)

Implementación de la entrega 2.1 en `feature/fase-02-01-sucursales` (creada desde `docs/fase-02-diseno`), con los desvíos del
diseño que informó la implementación y la decisión del usuario sobre cada uno. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#entrega-21).

| Tema | Decisión | Quién |
|---|---|---|
| Rutas | Siete: `branches` (listado, alta, detalle y edición, sin baja), `branches/{branch}/warehouses` (listado y alta) y `warehouses/{warehouse}` (edición). 26 → 33 | Diseño (R-029) |
| Permisos | Sin permisos nuevos (123). El recepcionista y el veterinario suman `branches.index` | Usuario (pregunta 19) |
| `is_default: false` | 422 solo en la sucursal o el depósito que es el por defecto; en otro no cambia nada (el frontend manda el formulario completo) | Implementación (desvío 1); aceptado por el usuario |
| Primera por defecto | Si la clínica no tiene sucursal por defecto, la que se crea pasa a serlo; igual el primer depósito de una sucursal sin depósito por defecto | Implementación (desvío 2); aceptado por el usuario |
| Únicos | Código, nombre y establecimiento los revisa el Service con la clínica bloqueada (`CatalogLock`), no reglas `unique` del Form Request: dos altas simultáneas no terminan en un 500 por el índice único. Misma respuesta, 422 `VALIDATION_ERROR` | Implementación (desvío 3); aceptado por el usuario |
| Mismo `establishment` | Con facturas, reenviar el mismo valor responde 200: no es un cambio | Implementación (desvío 4); aceptado por el usuario |
| Listado de depósitos | Paginado, con `ListFilters` e `include_inactive` | Implementación (desvío 5); aceptado por el usuario |
| Nombres | `CatalogMessages::NAME_REGEX`: al menos una letra, como especies y razas | Implementación (desvío 6); aceptado por el usuario |
| Depósito en sucursal inactiva | Se podía crear: el diseño no lo mencionaba. **Rechazado**: 422 en `branch`, "La sucursal está inactiva." (corregido en R-031) | Implementación (desvío 7); rechazado por el usuario |
| Orden de bloqueo | Nuevo: clínica → sucursal → usuario. Inactivar una sucursal vacía `current_branch_id` usuario por usuario, para la auditoría. `CLAUDE.md` §3: clínica → sucursal → depósito o usuario | Implementación (desvío 8); aceptado por el usuario |
| Deuda 2 | `Branch` y `Warehouse` revisados: casts y relaciones correctos; suman valores por defecto, el código en mayúsculas, el nombre limpio y `Branch::invoices()` | Implementación |
| Validación | Suite en verde en SQLite (662 casos, 7505 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 7521 aserciones). Las cinco protecciones del diseño quitadas de a una, cada una con un test que falla | Implementación |

**Qué cambió en la API.** Rutas nuevas: las siete de sucursales y depósitos. `error_code` nuevos: `BRANCH_DEFAULT_NOT_DEACTIVATABLE`,
`BRANCH_ESTABLISHMENT_NOT_EDITABLE` y `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE`. El recepcionista y el veterinario suman
`branches.index`. Rutas, permisos y contratos existentes, sin cambios.

# R-031 Revisión, correcciones y cierre de la entrega 2.1 de la FASE 2 (2026-11-07)

La revisión de la entrega 2.1, en una sesión de Claude Code aparte de la implementación, encontró un hallazgo de gravedad media
(el desvío 7, rechazado por el usuario) y cuatro de baja (tests que pasaban con la regla rota). El usuario pidió corregirlos y
validó la entrega. Implementación (R-030), correcciones y cierre van en un solo commit en `feature/fase-02-01-sucursales`. El
detalle está en las [correcciones](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#correcciones-de-la-revisión-r-031) y la
[validación](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#validación-del-usuario-r-031).

| Tema | Decisión | Quién |
|---|---|---|
| Sucursal inactiva | `WarehouseService::create()` revisa `is_active` sobre la sucursal ya bloqueada: 422 en `branch`, "La sucursal está inactiva." Reactivada, el alta vuelve a responder 201 | Usuario |
| Tests faltantes | La búsqueda de depósitos por código, la sucursal nueva sin usuarios en `branch_user` y el orden clínica → sucursal → usuario al inactivar | Usuario |
| Voseo | `VoseoTest` suma el verbo `crear`. La descripción de `clinic:create` pasa de "Crea una clínica…" (tercera persona, que el test marcaba) a "Alta de una clínica…" | Usuario; el cambio de la descripción, implementación |
| Postman | La colección de sucursales suma el pedido 24b (depósito en la sucursal recién inactivada → 422): 37 requests y 107 pruebas | Usuario |
| Validación | El usuario corrió la colección en Postman (Collection Runner, una iteración): 107 de 107 pruebas, sin errores. Entrega validada | Usuario, el 2026-11-07 |
| Suite del cierre | La de las correcciones, sobre el mismo código: 664 casos; en SQLite, 7525 aserciones con 2 omitidos (solo de MySQL); en MySQL 8.4.3 (`syncvet_test_mysql`), 7541 aserciones. La mutación del chequeo de la sucursal inactiva hace fallar su test | Cierre |
| Sucursales y depósitos | El módulo pasa a COMPLETADO: la 2.1 era su única entrega en el diseño (R-029) | Cierre |
| Deuda 2 | Sigue abierta: revisada en `Branch` y `Warehouse`; faltan `User`, `Veterinarian`, `Service` y `AuditLog` en las entregas siguientes | Cierre |
| Siguiente | Entrega 2.2a (usuarios), en `feature/fase-02-02a-usuarios`, creada desde `feature/fase-02-01-sucursales`. Sin merge a `main` hasta el final de la FASE 2 | Usuario |

**Qué cambió en la API.** Crear un depósito en una sucursal inactiva responde 422 en `branch` (antes, 201). Rutas, permisos,
`error_code` y contrato de respuesta, sin otros cambios.

# R-032 Entrega 2.2a de la FASE 2: usuarios (2026-11-07)

Implementación de la entrega 2.2a en `feature/fase-02-02a-usuarios` (creada desde `feature/fase-02-01-sucursales`), con los
desvíos del diseño que informó la implementación y la decisión del usuario sobre cada uno. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#entrega-22a).

| Tema | Decisión | Quién |
|---|---|---|
| Rutas | Cinco: `users` (listado, alta, detalle y edición, sin baja hasta la 2.2b) y `roles` (listado). 33 → 38 | Diseño (R-029) |
| Permisos | Sin permisos nuevos (123): `users.*` y `roles.index` ya existían. Solo el administrador (P2) | Diseño (R-029) |
| Bloqueo de la clínica | En toda alta y edición, no solo en la edición que toca los roles: se ordenan con la inactivación de una sucursal (clínica → sucursal → usuario, R-030), y el Service vuelve a leer las sucursales con la clínica bloqueada (una inactivada entre la validación y el guardado → 422) | Implementación (desvío 1); aceptado por el usuario |
| Sucursal actual | "La primera" es la primera activa de la lista; sin ninguna activa (solo una inactiva que el usuario ya tenía), queda sin sucursal actual. Ampliada en R-033 para el usuario que no tiene una | Implementación (desvío 2); aceptado por el usuario |
| Email único | Lo revisa el Service, no una regla `unique` del Form Request: dos altas simultáneas en clínicas distintas que chocan con el índice reciben 422, no un 500 | Implementación (desvío 3); aceptado por el usuario |
| Revocación de tokens | Cualquier `password` enviado revoca los tokens del usuario, aunque sea igual a la anterior | Implementación (desvío 4); aceptado por el usuario |
| Auditoría de sucursales | El cambio de sucursales queda como `updated`, con `branch_ids` antes y después (el diseño solo pedía `ROLES_SYNCED` para los roles) | Implementación (desvío 5); aceptado por el usuario |
| `roles` en `UserResource` | Lista de `{name, label}`, la misma forma que `GET roles`, desde el Enum `UserRole`; un test exige que el Enum coincida con los roles del seeder | Implementación (desvío 6); aceptado por el usuario |
| Último administrador activo | En la 2.2a, "activo" es no eliminado: `is_active` llega con la 2.2b. No cuentan los de otra clínica. **Aceptado con una condición:** la 2.2b cuenta solo los administradores con `is_active` y lo prueba con un test | Implementación (desvío 7); aceptado por el usuario con esa condición |
| Validación del email | Sigue `EMAIL_DNS_CHECK`, como la de los clientes: en desarrollo rechaza `example.com`, y la colección de Postman usa `@mailinator.com` (necesita internet). Los usuarios del `DemoSeeder` no se tocan | Implementación (desvío 8); aceptado por el usuario |
| Orden del listado | Por apellido, nombre e id (el diseño no lo fijaba) | Implementación (desvío 9); aceptado por el usuario |
| Deuda 2 | `User` revisado contra la tabla y las claves foráneas: casts (`password` → `hashed`) y relaciones (`clinic`, `currentBranch`, `branches`, `veterinarian`) correctos, sin cambios | Implementación |

**Qué cambió en la API.** Rutas nuevas: las cinco de usuarios y roles. `error_code` nuevo: `USER_LAST_ADMIN` (409). Rutas,
permisos y contratos existentes, sin cambios (login, `me` y logout incluidos).

# R-033 Revisión, correcciones y cierre de la entrega 2.2a de la FASE 2 (2026-11-07)

La revisión de la entrega 2.2a, en una sesión de Claude Code aparte de la implementación, no encontró errores en el código: encontró
dos puntos de diseño (el administrador que cambia su propia contraseña cierra su propia sesión, y el usuario sin sucursal actual que
recibe sucursales sigue sin una), tests que faltaban y un test inestable. El usuario decidió cada punto y pidió corregirlos, sumó el
cambio de los nombres de personas y validó la entrega. Implementación (R-032), correcciones y cierre van en un solo commit en
`feature/fase-02-02a-usuarios`. El detalle está en las
[correcciones](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#correcciones-de-la-revisión-r-033) y la
[validación](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#validación-del-usuario-r-033).

| Tema | Decisión | Quién |
|---|---|---|
| Contraseña propia | `PATCH`/`PUT users/{user}` con `password` sobre el usuario autenticado → 422 en `password`, "Para cambiar tu contraseña usá tu perfil." (también vacía o nula). La propia se cambia con `PATCH me/password` (2.2b), que pide la actual y conserva el token en uso | Usuario |
| Sucursal actual | Un usuario sin sucursal actual que recibe `branch_ids` toma una con la regla del alta (la por defecto si está en la lista; si no, la primera activa) | Usuario |
| Nombres de personas | **Desvío que cambia una regla de la FASE 1** (§6.1): nombre y apellido de clientes y de usuarios aceptan el apóstrofo y el guion entre letras (D'Alessandro, Ana-María), nunca al principio, al final ni repetidos; los clientes suman la `ü`, que los usuarios ya aceptaban. Una sola regla, `App\Support\PersonName::REGEX`. El mensaje pasa a "… solo puede contener letras y espacios, y apóstrofos o guiones entre letras." | Usuario; el lugar de la regla (`App\Support`, porque la usan clientes y usuarios), usuario |
| Tests faltantes | Un administrador real (rol `administrador`, con token) que se quita su propio rol: solo, 409; con otro, 200. La revocación con tokens reales: el usuario editado pierde los suyos y el administrador conserva el suyo. La contraseña y su hash fuera de la auditoría de la edición. Los nombres, en clientes y usuarios | Usuario |
| Test inestable | `UserIndexTest` dependía del apellido al azar del usuario que hace el pedido, que a veces se ordenaba primero: `actAsUserManager` le fija nombre, apellido y email | Revisión |
| Postman | La colección de usuarios suma el pedido 16b (el administrador cambia su propia contraseña → 422): 30 requests | Usuario |
| Validación | El usuario corrió la colección en Postman (Collection Runner, una iteración): 0 errores. Número de pruebas: 78 de 78 pruebas, en 30 requests (cifra tomada de Newman: la colección corrida otra vez el 2026-11-07 contra la base de desarrollo, con `SUC-USR` ya creada, sin fallas; el Collection Runner del usuario no informó la cifra). Entrega validada | Usuario, el 2026-11-07 |
| Suite del cierre | Sobre el código del commit (después de las correcciones y de mover la regla de nombres a `App\Support\PersonName`): 706 casos; en SQLite, 8277 aserciones con 2 omitidos (solo de MySQL); en MySQL (`syncvet_test_mysql`), 8293 aserciones. La mutación del chequeo de la contraseña propia hace fallar su test | Cierre |
| Deuda 2 | Sigue abierta: revisada en `Branch`, `Warehouse` y `User`; faltan `Veterinarian`, `Service` y `AuditLog` | Cierre |
| Siguiente | Entrega 2.2b (usuarios inactivos, baja, sucursal actual y contraseña propia), en `feature/fase-02-02b-usuarios`, creada desde `feature/fase-02-02a-usuarios`. Sin merge a `main` hasta el final de la FASE 2 | Usuario |

**Qué cambió en la API.** `password` sobre el propio usuario en `users/{user}` responde 422. Clientes: nombre y apellido aceptan el
apóstrofo, el guion y la `ü` (antes, 422), con otro mensaje de error. Rutas, permisos, `error_code` y contrato de respuesta, sin
otros cambios.

# R-034 Entrega 2.2b de la FASE 2: usuarios inactivos, baja, sucursal actual y contraseña propia (2026-11-07)

Implementación de la entrega 2.2b en `feature/fase-02-02b-usuarios` (creada desde `feature/fase-02-02a-usuarios`), con los
desvíos del diseño que informó la implementación y la decisión del usuario sobre cada uno. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#entrega-22b).

| Tema | Decisión | Quién |
|---|---|---|
| Rutas | Tres: `DELETE users/{user}` (`users.destroy`), `PATCH me/current-branch` (`auth.current-branch`) y `PATCH me/password` (`auth.password`), las dos de `me` sin permiso (`ROUTES_WITHOUT_PERMISSION`). 38 → 41 | Diseño (R-029) |
| Permisos | Sin permisos nuevos (123): `users.destroy` ya existía. La baja, solo el administrador (P2) | Diseño (R-029) |
| Migración | `2026_11_07_000000_add_is_active_to_users`: `is_active` booleano, no nulo, por defecto `true`, después de `password`; sin índice. 32 → 33. Probada en MySQL durante la implementación: sube, baja y vuelve a subir (`tinyint(1)`, por defecto 1) | Diseño (R-029); prueba, implementación |
| Último administrador | Regla generalizada: si un administrador activo deja de serlo (pierde el rol, se inactiva o se da de baja), tiene que quedar otro administrador activo de la clínica. Quitarle el rol a un administrador ya inactivo responde 200: no cambia la cantidad de activos | Implementación (desvío 1); aceptado por el usuario |
| Bloqueo de `me/current-branch` | Clínica → sucursal → usuario, el mismo orden que la inactivación de una sucursal (R-030). Una sucursal inactivada entre la validación y el guardado → 422 | Implementación (desvío 2); aceptado por el usuario |
| Intentos de `me/password` | Los errores de validación (una contraseña nueva débil, por ejemplo) responden 422 antes del límite y no cuentan: solo cuenta lo que llega a verificar la contraseña actual | Implementación (desvío 3); aceptado por el usuario |
| Auditoría de la baja | Dos registros: `updated` (el email con el sufijo de baja) y `deleted` | Implementación (desvío 4); aceptado por el usuario |
| `RoleAccessTest` | Las dos rutas de `me` sin cuerpo responden 422 `VALIDATION_ERROR` en lugar de 200: el test lo acepta solo para esas dos; `me` y `logout` siguen exigiendo 200 | Implementación (desvío 5); aceptado por el usuario |
| Tests inestables de la 2.2a | Dos aserciones de `UserStoreTest` contaban usuarios por `first_name = 'Lucía'`, que la factory (`es_ES`) puede generar al azar: ahora cuentan el total y el email | Implementación (desvío 6); aceptado por el usuario |
| Claves foráneas en MySQL | `test_usage_covers_every_foreign_key_to_users` traía en MySQL las tablas de las otras bases del servidor: limitado al esquema actual | Implementación (desvío 7); aceptado por el usuario |
| Límite de `me/password` | **Agregado al diseño:** 5 intentos por minuto por usuario, contados antes de verificar la contraseña actual, como el login; el sexto responde 429 `AUTH_TOO_MANY_ATTEMPTS` con `Retry-After` aunque la actual sea correcta; un cambio correcto limpia el contador. El contador es del usuario: el de otro no se afecta | Usuario |
| Condición del desvío 7 de la 2.2a (R-032) | Cumplida: `USER_LAST_ADMIN` cuenta solo los administradores con `is_active`; lo prueba `UserDeactivateTest::test_an_inactive_administrator_does_not_count` (un administrador inactivo no evita el 409, ni al quitar el rol ni al inactivar) y `UserDeleteTest::test_the_last_active_administrator_is_not_deleted` | Implementación |

**Qué cambió en la API.** Rutas nuevas: las tres de arriba. `error_code` nuevos: `USER_INACTIVE` (403), `USER_SELF_ACTION` (409) y
`USER_IN_USE` (409). `users/{user}` acepta `is_active`; `GET users` acepta `include_inactive` y por defecto muestra solo los activos;
`UserResource` y `ShowUserResource` suman `is_active`. Login, `me` y logout sin otros cambios: un usuario inactivo recibe 403
`USER_INACTIVE` en el login con la contraseña correcta y en `me` con un token vigente, y puede hacer logout.

# R-035 Revisión, correcciones y cierre de la entrega 2.2b de la FASE 2 (2026-11-07)

La revisión de la entrega 2.2b, en una sesión de Claude Code aparte de la implementación, no encontró errores en el código: encontró
reglas sin test o con tests que pasarían con la regla rota, y una referencia vieja en un comentario. El usuario pidió corregir los
cinco hallazgos y validó la entrega. Implementación (R-034), correcciones y cierre van en un solo commit en
`feature/fase-02-02b-usuarios`. El detalle está en las
[correcciones](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#correcciones-de-la-revisión-r-035) y la
[validación](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#validación-del-usuario-r-035).

| Tema | Decisión | Quién |
|---|---|---|
| Hallazgos 1 a 5 | Corregidos: tests del inactivo en las rutas del perfil, del login del inactivo con la contraseña incorrecta, del uso por `created_by` y por una segunda columna, de la asignación revisada en el Service y del orden de bloqueo de `me/current-branch`, y el comentario de `UserService::USAGE`. Cada test nuevo, con la mutación que lo hace fallar | Usuario |
| Validación | El usuario corrió la colección `syncvet-fase-02-usuarios` en Postman (Collection Runner, una iteración): 53 requests, 130 de 130 pruebas, 0 errores. Entrega validada | Usuario, el 2026-11-07 |
| Suite del cierre | Sobre el código después de las correcciones: 742 casos; en SQLite, 8642 aserciones con 2 omitidos (solo de MySQL); en MySQL (`syncvet_test_mysql`), 8658 aserciones | Cierre |
| Siguiente | Entrega 2.3 (veterinarios), en `feature/fase-02-03-veterinarios`, creada desde `feature/fase-02-02b-usuarios`. Sin merge a `main` hasta el final de la FASE 2 | Usuario |

**Qué cambió en la API.** Nada más que lo de R-034: las correcciones son tests y un comentario.

# R-036 Entrega 2.3 de la FASE 2: veterinarios (2026-11-09)

Implementación de la entrega 2.3 en `feature/fase-02-03-veterinarios` (creada desde `feature/fase-02-02b-usuarios`), con los
desvíos del diseño que informó la implementación y la decisión del usuario sobre cada uno. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#entrega-23).

| Tema | Decisión | Quién |
|---|---|---|
| Rutas | Cinco: `apiResource('veterinarians')` (listado, alta, detalle, edición con `PATCH` y `PUT`, y baja). 41 → 46 | Diseño (R-029) |
| Permisos | Sin permisos nuevos (123): `veterinarians.*` ya existían, y la recepción y el veterinario ya tenían `veterinarians.index` | Diseño (R-029) |
| Migración | `2026_11_09_000000_widen_veterinarians_license_number`: `license_number` de 30 a 60 caracteres (pregunta 29); se valida hasta 30. La bajada se detiene con un mensaje claro si alguna matrícula pasa de 30, sin cortarla. 33 → 34. Probada en MySQL durante la implementación: sube, baja (con una matrícula larga se detiene y la columna queda en 60) y vuelve a subir | Diseño (R-029); la bajada que se detiene y la prueba, implementación |
| Restauración | Los campos que no llegan en el pedido vuelven a su valor por defecto, como en un alta (si no, quedaría la matrícula vieja con el sufijo de baja). La auditoría guarda `updated` (los cambios) y `restored`, porque `restore()` guarda el modelo | Implementación (desvío 1); aceptado por el usuario |
| Orden de bloqueo | En el alta y la restauración: clínica (`CatalogLock`), después el usuario, después la ficha (también eliminada) y la serie al final, según la precisión 1 del usuario. Compatible con `UserService`, que bloquea clínica → usuario antes de buscar la ficha al quitar el rol | Implementación (desvío 2); aceptado por el usuario |
| `USER_IS_VETERINARIAN` | Salta cuando el usuario tiene una ficha no eliminada y los roles nuevos no incluyen `veterinario`, aunque hoy no tenga el rol | Implementación (desvío 3); aceptado por el usuario |
| Mensajes del usuario | Propio para el usuario sin el rol: "El usuario no tiene el rol veterinario."; para uno inactivo, de otra clínica, eliminado o inexistente: "El usuario no existe o está inactivo." | Implementación (desvío 4); aceptado por el usuario |
| Listado | Sin `status`, solo los activos, y no había forma de traer activos e inactivos juntos | Implementación (desvío 5); corregido a pedido del usuario en la revisión (R-037): `include_inactive=1` |
| Uso "también eliminados" | Ninguna de las seis tablas de uso tiene borrado lógico hoy; la consulta con `DB::table` los contará cuando lo tengan | Implementación (desvío 6); aceptado por el usuario |
| Matrícula y mayúsculas | No se normalizaba: en MySQL "mv-1001" y "MV-1001" eran la misma matrícula y en SQLite no | Implementación (desvío 7); corregido a pedido del usuario en la revisión (R-037) |
| Deuda 2 | `Veterinarian` revisado contra la tabla y las claves foráneas: relaciones (`user`, también eliminado) y casts correctos; suma los valores por defecto de `status` y `commission_percentage` y su documentación | Implementación |

**Qué cambió en la API.** Rutas nuevas: las cinco de veterinarios. `error_code` nuevos: `VETERINARIAN_IN_USE` (409) y
`USER_IS_VETERINARIAN` (409, en `PATCH`/`PUT users/{user}`). `ShowUserResource` suma `veterinarian` (`id`, `code`, o `null`). Rutas,
permisos y contratos existentes, sin otros cambios.

# R-037 Revisión, correcciones y cierre de la entrega 2.3 de la FASE 2 (2026-11-09)

La revisión de la entrega 2.3, en una sesión de Claude Code aparte de la implementación, no encontró errores de comportamiento fuera
de los desvíos 5 y 7: encontró una discrepancia de permisos que decidió el usuario, una regla sin test y detalles. El usuario pidió
corregir los hallazgos y validó la entrega. La implementación y las correcciones quedaron en dos commits WIP (`4ff4a4c` y `c6a6847`);
el cierre va en un commit aparte en `feature/fase-02-03-veterinarios`. El detalle está en las
[correcciones](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#correcciones-de-la-revisión-r-037) y la
[validación](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#validación-del-usuario-r-037).

| Tema | Decisión | Quién |
|---|---|---|
| Matrícula (desvío 7) | Se pasa a mayúsculas y sin espacios en los extremos antes de validar, en el alta, la edición y la restauración (vacía, nula): la regla `unique` compara el valor normalizado. El Service la normaliza también, por si se lo llama sin el Form Request | Usuario |
| `include_inactive` (desvío 5) | En el listado, como en sucursales y usuarios: si llega `status`, manda `status`; `include_inactive=1` sin `status` trae activos e inactivos; un valor inválido → 422 | Usuario |
| Atributo compartido | `include_inactive` se llama "incluir los inactivos" en `lang/es/validation.php` (antes "incluir inactivas"): sirve para sucursales, depósitos, especies, usuarios y veterinarios | Usuario |
| Permisos del detalle | La recepción y el veterinario solo listan veterinarios (`veterinarians.index`); el detalle, con la comisión y las notas, es solo del administrador. El listado no muestra `commission_percentage` ni `notes`, con test. Sin cambios en el seeder | Usuario |
| Filtro de clínica en el Service | Tests nuevos: el alta con el usuario de otra clínica que tiene una ficha eliminada (422, la ficha sigue eliminada) y el Service llamado sin el Form Request con usuarios de otra clínica. Quitar `inClinic()` de `lockedUser()` hace fallar un test | Usuario |
| Detalles | `Veterinarian` usa `VeterinarianStatus::ACTIVE->value` como valor por defecto; un test de la migración, solo en MySQL, baja sin matrículas de más de 30 (la columna vuelve a 30 sin cortar ninguna) y vuelve a subir | Usuario |
| Baja con uso en Postman | No está en la colección: la cubren los tests (`VeterinarianDeleteTest`) y se agrega en la FASE 3 con las agendas (anotado en su documento). El `DemoSeeder` no se toca | Usuario |
| Restos de cada corrida | Cada corrida de Postman deja un usuario inactivo y una ficha eliminada (la ficha eliminada es uso del usuario, que no se puede eliminar); anotado en la guía | Usuario |
| Postman | La colección suma el alta con la matrícula de vet-a en minúsculas (422), `include_inactive=1`, `status` que manda y `include_inactive` inválido (422): 32 requests | Usuario |
| Validación | El usuario corrió la colección `syncvet-fase-02-veterinarios` en Postman (Collection Runner, una iteración): 75 de 75 pruebas, 0 errores. Entrega validada | Usuario, el 2026-11-09 |
| Suite del cierre | Sobre el código después de las correcciones (`c6a6847`): 797 casos; en SQLite, 9217 aserciones con 3 omitidos (solo de MySQL); en MySQL (`syncvet_test_mysql`), 9237 aserciones sin omitidos | Cierre |
| Deuda 2 | Sigue abierta: revisada en `Branch`, `Warehouse`, `User` y `Veterinarian`; faltan `Service` y `AuditLog` | Cierre |
| Siguiente | Entrega 2.4 (servicios), en `feature/fase-02-04-servicios`, creada desde `feature/fase-02-03-veterinarios`. Sin merge a `main` hasta el final de la FASE 2 | Usuario |

**Qué cambió en la API.** Frente a R-036: `GET veterinarians` acepta `include_inactive`; la matrícula se guarda en mayúsculas y sin
espacios en los extremos, y una que solo difiere en mayúsculas de otra responde 422 también en SQLite; el nombre del campo
`include_inactive` en los mensajes de validación pasa a "incluir los inactivos" en todos los listados. Rutas, permisos y `error_code`,
sin otros cambios.

# R-038 Entrega 2.4 de la FASE 2: servicios (2026-11-09)

Implementación de la entrega 2.4 en `feature/fase-02-04-servicios` (creada desde `feature/fase-02-03-veterinarios`), con los
desvíos del diseño que informó la implementación y la decisión del usuario sobre cada uno. El detalle está en el
[resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#entrega-24).

| Tema | Decisión | Quién |
|---|---|---|
| Rutas | Cinco: `apiResource('services')` (listado, alta, detalle, edición con `PATCH` y `PUT`, y baja). 46 → 51 | Diseño (R-029) |
| Permisos | Sin permisos nuevos (123): `services.*` ya existían, y la recepción y el veterinario ya tenían `services.index` y `services.show` | Diseño (R-029) |
| Migraciones | Ninguna (34): `services.name` de 150 admite el nombre de 120 más el sufijo de baja | Diseño (R-029) |
| Obligatorios en el alta | `name`, `category` y `standard_price`; la duración (30), el IVA (10) y el estado (`active`) toman su valor por defecto. El precio, para que un servicio no quede en 0 por olvido | Implementación (desvío 1); aceptado por el usuario |
| Nombre fijo de un servicio usado | Cuenta como cambio solo lo que cambia el nombre ya limpio: reenviar el mismo con espacios de más pasa; cambiar solo las mayúsculas es un cambio | Implementación (desvío 2); aceptado por el usuario |
| Formato del nombre | Usaba `CatalogMessages::NAME_REGEX` (sin `/` ni `%`) | Implementación (desvío 3); resuelto a pedido del usuario en la revisión (R-039): regex propio de servicios |
| Precio en la respuesta | Texto con dos decimales (`"150000.00"`), como la comisión de los veterinarios; se guarda con BCMath | Implementación (desvío 4); aceptado por el usuario |
| Precios rechazados | Un número de JSON con `.0` → 422; un texto con espacios en los extremos se acepta (`TrimStrings`) | Implementación (desvío 5); aceptado por el usuario |
| Orden del listado | Por nombre e id | Implementación (desvío 6); aceptado por el usuario |
| Rango de Postman | Sin rango propio (no queda uno de seis dígitos libre): el número de la corrida va en los nombres de sus servicios, que ninguna otra colección crea | Implementación (desvío 7); aceptado por el usuario |
| Nombre del Service | `ServiceCatalogService` | Implementación (desvío 8); aceptado por el usuario |
| Deuda 2 | `Service` revisado: relaciones y casts correctos; suma el cast de `duration_minutes`, los valores por defecto de las columnas y el nombre limpio al guardar | Implementación |

**Qué cambió en la API.** Rutas nuevas: las cinco de servicios. `error_code` nuevos: `SERVICE_NAME_NOT_EDITABLE` (422) y
`SERVICE_IN_USE` (409), con `ServiceException`, que ya existía. Rutas, permisos y contratos existentes, sin cambios.

# R-039 Revisión, correcciones y cierre de la entrega 2.4 de la FASE 2 (2026-11-09)

La revisión de la entrega 2.4, en una sesión de Claude Code aparte de la implementación, encontró un error de comportamiento (el
código repetido respondía como un nombre repetido), el desvío 3 para decidir y dos huecos de tests. El usuario pidió corregirlos,
corregir lo mismo en veterinarios (de la 2.3) y validó la entrega. La implementación y las correcciones quedaron en dos commits WIP
(`a2079a2` y `a5fad25`); el cierre, con la corrección de veterinarios, va en un commit aparte en `feature/fase-02-04-servicios`. El
detalle está en las [correcciones](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#correcciones-de-la-revisión-r-039) y la
[validación](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#validación-del-usuario-r-039).

| Tema | Decisión | Quién |
|---|---|---|
| Desvíos | Se aceptan el 1, 2, 4, 5, 6, 7 y 8; el 3 se resuelve con un regex propio | Usuario |
| Nombre de un servicio (desvío 3) | `ServiceMessages::NAME_REGEX`: al menos una letra, sin `//` (el comienzo del sufijo de baja: un nombre con `//deleted_<id>_<ts>` podría chocar en el índice con el de una baja) y letras, números, espacios y `. , - ' ( ) / + %`. `CatalogMessages::NAME_REGEX` (sucursales, especies y razas) no cambia | Usuario |
| Violaciones de índice único | Se identifican por el nombre del índice (o `tabla.columna` en SQLite), nunca buscando el nombre de la columna en el mensaje de la excepción, que trae el SQL. Una violación que no es la esperada sigue su camino: la del código llega a `DocumentSequenceService` (409 `DOCUMENT_CODE_CONFLICT`). Regla nueva en `CLAUDE.md` §3 | Usuario |
| Servicios (hallazgo 1) | `ServiceCatalogService::isNameViolation()`, con tests del 409 por el código y del 422 del índice en el alta y la edición | Usuario |
| Veterinarios (corrección de la 2.3 en el cierre de la 2.4) | `VeterinarianService::isLicenseViolation()`: un código `VET-` repetido respondía 422 "Ya hay un veterinario con esa matrícula.", aunque el pedido no la trajera. Tests del 409 por el código (con y sin matrícula) y del 422 del índice en el alta y la edición | Usuario |
| Tests (hallazgos 3 y 4) | Un servicio usado cambia la tasa de IVA, la duración y la categoría (200); `include_inactive=1&status=inactive` trae solo los inactivos | Usuario |
| Auditoría del precio (hallazgo 5) | Informativo, sin cambios: el valor anterior sale de `getRawOriginal` (`AuditLogger`), un número en SQLite y texto en MySQL; sin floats | Usuario |
| Postman | La colección suma el alta con "/" en el nombre (201) y su baja: 27 requests y 64 pruebas. Sin servicios activos al terminar | Usuario |
| Validación | El usuario corrió la colección `syncvet-fase-02-servicios` en Postman (Collection Runner, una iteración): 64 de 64 pruebas, 0 errores. Entrega validada | Usuario, el 2026-11-09 |
| Suite del cierre | Sobre el código con la corrección de veterinarios: 859 casos; en SQLite, 9869 aserciones con 3 omitidos (solo de MySQL); en MySQL (`syncvet_test_mysql`), 9889 aserciones sin omitidos | Cierre |
| Deuda 2 | Sigue abierta: revisada en `Branch`, `Warehouse`, `User`, `Veterinarian` y `Service`; falta `AuditLog` (2.6) | Cierre |
| Siguiente | Entrega 2.5 (configuración de la clínica, logo y archivos huérfanos), en `feature/fase-02-05-configuracion`, creada desde `feature/fase-02-04-servicios`. Sin merge a `main` hasta el final de la FASE 2 | Usuario |

**Qué cambió en la API.** Frente a R-038: el nombre de un servicio acepta además `,`, `/`, `+` y `%` (sin `//`), y el mensaje de
`name.regex` cambia; un código repetido en el alta de un servicio o de un veterinario responde 409 `DOCUMENT_CODE_CONFLICT` en lugar
del 422 del nombre o de la matrícula. Rutas, permisos y `error_code`, sin otros cambios.

# R-040 Entrega 2.5 de la FASE 2: configuración de la clínica, logo y archivos huérfanos (2026-11-09)

Implementación de la entrega 2.5 en `feature/fase-02-05-configuracion` (creada desde `feature/fase-02-04-servicios`). El detalle está
en el [resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#entrega-25).

| Tema | Decisión | Quién |
|---|---|---|
| Rutas | Cinco: `GET` y `PATCH`/`PUT` `system-settings`, y `POST`, `GET` y `DELETE` `system-settings/logo`. 51 → 56 | Diseño (R-029) |
| Permisos | Cuatro nuevos, 123 → 127: `system-settings.show`, `.upload-logo`, `.show-logo` y `.delete-logo` (`.update` ya existía). El recepcionista y el veterinario suman `show` y `show-logo` | Diseño (R-029) |
| Migraciones | Ninguna (34): `clinics` y `system_settings` existían. Modelo nuevo `SystemSetting` (`BelongsToClinic`, `Auditable`) | Diseño (R-029) |
| Claves | Enum `SystemSettingKey`, lista cerrada con la regla de cada clave: `contact_email`, `contact_phone`, `website` y `document_footer`; `logo_path`, interna. `null` borra la fila | Diseño (R-029, pregunta 23) |
| Logo | JPG, PNG o WEBP de hasta 2 MB, disco propio `clinic_logos`, la ruta en `logo_path` y nunca en la API (`has_logo` en la configuración y en `me`) | Diseño (R-029, P12 y pregunta 25) |
| Reutilización | Lo común con la foto del paciente pasa a `App\Support\PrivateImageStore` (guardar, reemplazar después del commit, validar la ruta, descargar) y a `App\Http\Requests\PrivateImageRequest` (tipo por el contenido, 413, megapíxeles, imagen vuelta a codificar). Los tests de la foto no cambian | Diseño (R-029) |
| Limpieza (deuda 14) | `files:prune-orphans` con `--dry-run` (`OrphanFileService`), programado a diario a las 03:00 | Diseño (R-029, P15 y pregunta 26) |
| `CatalogLock::forCurrentClinic()` | Devuelve la clínica bloqueada (antes, nada): la edición de los datos la usa | Implementación |

**Qué cambió en la API.** Rutas nuevas: las cinco de la configuración. `error_code` nuevo: `CLINIC_LOGO_NOT_FOUND` (404), con
`ClinicException`, que ya existía. `me` (y el login) suma `clinic.has_logo`. Rutas, permisos y contratos existentes, sin otros
cambios: la foto del paciente responde igual con `PrivateImageStore`.

# R-041 Revisión, correcciones y cierre de la entrega 2.5 de la FASE 2 (2026-11-09)

La primera validación con Postman encontró un 500 en la lectura de la configuración; la revisión, en una sesión de Claude Code aparte
de la implementación, encontró dos hallazgos de gravedad media y varios menores. El usuario decidió cada uno y validó la entrega. La
implementación, la corrección del 500 y las correcciones de la revisión quedaron en tres commits WIP (`fe10d2b`, `62b160e` y
`f0b906a`); el cierre va en un commit aparte en `feature/fase-02-05-configuracion`. El detalle está en las
[correcciones](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#correcciones-de-la-revisión-r-041) y la
[validación](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#validación-del-usuario-r-041).

| Tema | Decisión | Quién |
|---|---|---|
| El 500 de la primera validación | `GET system-settings` respondía 500 en la base de desarrollo: el `DemoSeeder` marca sus clínicas con la clave `demo_data` (R-010), que no estaba en `SystemSettingKey`, y el cast `key` del modelo lanzaba un `ValueError` al leer todas las filas. La lectura toma las filas sin el modelo. Tests: `SystemSettingsTest::test_an_unknown_key_in_the_database_is_ignored_with_a_warning` y `test_the_demo_seeder_marker_is_an_internal_key` | Usuario |
| `demo_data` (desvío 1) | Clave interna de `SystemSettingKey`, como `logo_path`: no se edita (422) ni sale en la respuesta; `DemoSeeder::MARKER` la toma del enum | Implementación; aceptado por el usuario |
| Claves desconocidas (desvío 2) | Una fila con una clave fuera del enum se ignora al leer, con un aviso en el log, y no se borra | Implementación; aceptado por el usuario |
| Auditoría del logo | La fila `logo_path` nunca guarda la ruta en `audit_logs`: guarda `"(archivo)"` en el alta, la modificación y la baja (`SystemSetting::auditValues()`, un gancho nuevo de `Auditable` que aplica `AuditLogger`). `SystemSetting::auditContext()` suma siempre la clave: una modificación ya no queda como `{"value": …}` sin saber de qué clave es. La 2.6 no necesita ocultar la ruta del logo | Usuario |
| `has_logo` | Igual que `has_photo`: `logo_path !== null`, sin revisar la ruta | Usuario |
| Borrar el logo con una ruta inválida | `DELETE system-settings/logo` con un `logo_path` fuera de la carpeta de la clínica limpia la referencia (200) sin tocar ningún archivo, con el aviso en el log; sin logo sigue en 404. El diseño decía 404: sin este cambio, el administrador no podía sacar un logo roto | Usuario |
| Foto con una ruta inválida | El mismo caso en la foto del paciente queda como deuda 15 (borrarla responde 404 y deja la referencia) | Usuario |
| Clave en otras mayúsculas | En MySQL (comparación e índice único sin mayúsculas), un `PATCH` de `website` con una fila `WEBSITE` respondía 200 y escribía en esa fila, que la lectura ignora: el valor se perdía (la revisión lo informó como un 500; no lo era). La edición toma esa fila y le pone la clave del enum; la lectura compara exacto. Test en SQLite y en MySQL | Usuario |
| Limpieza que falla en una clínica | `files:prune-orphans` revisa cada clínica entera antes de borrar en ella; si la revisión falla (un enlace simbólico, un error del disco), queda en el log, no borra nada en esa clínica y sigue con las demás. Termina con 1 | Usuario |
| Tests | Clínicas con ids X y X0 y un `photo_path` cruzado entre clínicas (falla con `withoutGlobalScopes()`); el 413 del logo cortado por PHP; la clínica que falla; la auditoría sin rutas; la clave en otras mayúsculas | Usuario |
| Menores | `ClinicPaths` en su propio archivo; comentarios de `DemoSeeder` y de la migración de `system_settings`; `array_flip` en `OrphanFileService` | Usuario |
| Validación | El usuario corrió en Postman (Collection Runner, una iteración) `syncvet-fase-02-configuracion`: 67 de 67 pruebas, 0 errores; y, por `PrivateImageStore`, la de la foto (`syncvet-fase-01-foto`): 60 de 60, 0 errores. Entrega validada | Usuario, el 2026-11-09 |
| Suite del cierre | Sobre el código de `f0b906a`: 921 casos; en SQLite, 10477 aserciones con 3 omitidos (solo de MySQL); en MySQL (`syncvet_test_mysql`), 10497 aserciones sin omitidos | Cierre |
| Deuda 14 | Resuelta: `files:prune-orphans`. En producción necesita el cron del *scheduler* (FASE 10, entrega 10.5) | Cierre |
| Siguiente | Entrega 2.6 (auditoría y deuda 13), en `feature/fase-02-06-auditoria`, creada desde `feature/fase-02-05-configuracion`. Sin merge a `main` hasta el final de la FASE 2 | Usuario |

**Qué cambió en la API.** Frente a R-040: `DELETE system-settings/logo` con un `logo_path` fuera de la carpeta de la clínica responde
200 y limpia la referencia (antes, 404 `CLINIC_LOGO_NOT_FOUND`). Rutas, permisos y `error_code`, sin otros cambios.

# R-042 Entrega 2.6 de la FASE 2: consulta de la auditoría y fechas con hora en un lugar común (2026-11-09)

Implementación de la entrega 2.6 en `feature/fase-02-06-auditoria` (creada desde `feature/fase-02-05-configuracion`), en el commit WIP
`18d5db2`. El detalle está en el [resultado de la entrega](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#entrega-26).

| Tema | Decisión | Quién |
|---|---|---|
| Rutas | Dos: `GET audit-logs` y `GET audit-logs/{auditLog}`, sin rutas de escritura (405). 56 → 58 | Diseño (R-029) |
| Permisos | Sin nuevos (127): `audit-logs.index` y `audit-logs.show` ya existían; solo el administrador (P14), los demás roles reciben 403 | Diseño (R-029) |
| Migraciones | Ninguna (34): los índices de `audit_logs` cubren los filtros | Diseño (R-029) |
| Clínica | `AuditLog` ya tenía el `ClinicScope`: el listado y el detalle ven solo la clínica en contexto; un registro de otra clínica o sin clínica (`LOGIN_FAILED` con un email desconocido) → 404 | Diseño (R-029) |
| Filtros | `from` y `to`, `user_id` (de la clínica, también eliminado), `event`, `auditable_type` (lista cerrada), `auditable_id` (exige el tipo) y `search` literal en el nombre o el email del usuario; orden por `created_at` e `id` descendentes | Diseño (R-029) |
| Valores sensibles | El listado no trae `old_values` ni `new_values`. La foto del paciente se audita como `"(archivo)"` (`Patient::auditValues()`), como el logo (R-041); el detalle oculta `photo_path` en los registros anteriores | Diseño (R-029) y usuario (que la ruta de la foto no se guarde) |
| Deuda 13 | Cast `App\Casts\AppDateTime` en los atributos con hora de los modelos (15 modelos), con la conversión de zona en `App\Support\ApiDateTime::toLocal()`; `PatientService` ya no convierte `deceased_at`; `UpdatePatientRequest` y los filtros de la auditoría usan `ApiDateTime`. `DateTimeCastCoverageTest` lo exige | Diseño (R-029) |
| `AppDateTimeTest` (desvío 1) | En `tests/Feature/Database`, no en `tests/Unit`: necesita la base y así corre en SQLite y en MySQL | Implementación; aceptado por el usuario |
| Lista de tipos (desvío 2) | `AuditLog::AUDITABLE_TYPES` es una lista escrita; `AuditableTypesTest` falla si no coincide con los modelos con `Auditable` (como las listas `USAGE`) | Implementación; aceptado por el usuario |
| Casts (desvío 3) | Todos los casts `datetime` pasan a `AppDateTime`, no solo los asignables, salvo `created_at`, `updated_at` y `deleted_at` (los llena Eloquent con `now()`, ya en la hora de Asunción) y `ScheduleHour::starts_at`/`ends_at` (horas del día, `time`, sin zona) | Implementación; aceptado por el usuario |
| `event` (desvío 4) | Sale como `{value, label}`, como `category` y `status` en servicios | Implementación; aceptado por el usuario |
| `auditable_id` sin tipo (desvío 5) | El 422 va en `auditable_type`: "Para filtrar por el id del registro, indicá también su tipo." | Implementación; aceptado por el usuario |
| Formato de `from` y `to` (desvío 6) | Solo una fecha o una fecha con hora, por una expresión regular: "tomorrow" o "13:00" → 422, aunque la regla `date` de Laravel los acepta | Implementación; aceptado por el usuario |
| Registros viejos (desvío 7) | El detalle cambia el `photo_path` viejo por `"(archivo)"` sin quitar la clave (se sigue viendo que la foto cambió); reutiliza `SystemSetting::AUDIT_FILE` | Implementación; aceptado por el usuario |

**Qué cambió en la API.** Rutas nuevas: las dos de la auditoría. Sin `error_code` nuevos (solo los genéricos). `deceased_at` responde
igual que en la FASE 1 (se guarda en la hora de Asunción y sale en UTC con `Z`). Rutas, permisos y contratos existentes, sin otros
cambios.

# R-043 Revisión, correcciones y cierre de la entrega 2.6 de la FASE 2 (2026-11-10)

La revisión, en una sesión de Claude Code aparte de la implementación, encontró que la base de zonas de PHP del entorno de desarrollo
no tenía el cambio de Paraguay a UTC-3 todo el año, un hallazgo de gravedad media y varios menores. El usuario decidió cada uno y
validó la entrega. La implementación y las correcciones quedaron en dos commits WIP (`18d5db2` y `3df22d1`); el cierre va en un commit
aparte en `feature/fase-02-06-auditoria`. El detalle está en las
[correcciones](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#correcciones-de-la-revisión-r-043) y la
[validación](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#validación-del-usuario-r-043).

| Tema | Decisión | Quién |
|---|---|---|
| Zona horaria de Paraguay | Desde el 15/10/2024, `America/Asuncion` es UTC-3 todo el año (ley promulgada el 14/10/2024); la base de zonas de la IANA lo incluye desde la 2025a. PHP 8.3.16 de Laragon traía la 2024.2: de marzo a octubre usaba `-04:00` y corría una hora toda fecha con hora. Se instaló la extensión `timezonedb` (base 2026.6). `tests/Unit/AsuncionTimezoneTest` exige `-03:00` en invierno y en verano y, si no, falla pidiendo actualizar PHP o `timezonedb`. Pasos de Laragon en `development/setup.md`; requisito del servidor en `development/deployment.md` y en la FASE 10 (entrega 10.5) | Usuario |
| Ruta del logo en registros viejos (hallazgo 1) | Los registros de `system_setting` anteriores a R-041 (en la base de desarrollo, a veces sin la clave) tenían la ruta del logo: el detalle muestra `"(archivo)"` en cualquier valor con la forma `clinics/{id}/logo/…`, también anidado | Usuario |
| Fracción de segundo (hallazgo 2) | `from` con fracción incluía el segundo anterior (`created_at` no tiene fracción): `from` se redondea hacia arriba al segundo siguiente y `to` hacia abajo (`ApiDateTime::ceilSecond` y `floorSecond`); se siguen aceptando fracciones (el frontend manda `toISOString()`, con `.000Z`) | Usuario |
| Fecha anterior a la ley (hallazgo 3) | `AppDateTimeTest` suma `2024-07-01T12:00:00Z` → 08:00 (Asunción en `-04:00`) y julio de 2026 → `-03:00`; un `-03:00` fijo en lugar de `America/Asuncion` hace fallar el primero | Usuario |
| Formato (hallazgo 4) | `ApiDateTime::FILTER_FORMAT`: horas de 00 a 23, minutos y segundos de 00 a 59 y desplazamientos de hasta ±14:00; `T24:00:00` y `-2400` → 422 | Usuario |
| Tests (hallazgo 5) | `event=login_failed`; el detalle de un registro cuyo usuario fue eliminado; `DateTimeCastCoverageTest` recorre también las subcarpetas de `app/Models` | Usuario |
| Deuda 16 | Nueva: las columnas `timestamp` de MySQL se guardan según la zona de la sesión, que es la del servidor (`config/database.php` no fija `timezone`), y `useCurrent()` de `inventory_movements` depende de eso. Se analiza antes del diseño de la FASE 3 | Usuario |
| Suites en paralelo | No correr la de SQLite y la de MySQL a la vez: comparten los discos falsos de `Storage::fake` (`development/testing.md`) | Usuario |
| Desvíos 1 a 7 | Aceptados (R-042) | Usuario |
| Validación | El usuario corrió en Postman (Collection Runner, una iteración), después de las correcciones de la revisión, `syncvet-fase-02-auditoria`: 47 de 47 pruebas, 0 errores; y, como regresión por la deuda 13 (`deceased_at`), `syncvet-fase-01-pacientes`: 61 de 61, 0 errores. Entrega validada | Usuario, el 2026-11-10 |
| Suite del cierre | Sobre el código de `3df22d1`, una después de la otra: 988 casos; en SQLite, 10816 aserciones con 3 omitidos (solo de MySQL); en MySQL (`syncvet_test_mysql`), 10836 aserciones sin omitidos | Cierre |
| Deuda 13 | Resuelta: `AppDateTime` y `ApiDateTime` | Cierre |
| Deuda 2 | Revisados los seis modelos de la FASE 2 (`AuditLog`, el último, en la 2.6). Sigue abierta para los modelos generados de las fases siguientes | Cierre |
| Siguiente | Todas las entregas de la FASE 2 cerradas; falta el cierre de la fase y su merge a `main`, con autorización del usuario. Antes del diseño de la FASE 3, la deuda 16 | Usuario |

**Qué cambió en la API.** Frente a R-042: `from` y `to` rechazan una hora o un desplazamiento fuera de rango (422), y `from` con
fracción de segundo ya no incluye el segundo anterior; el detalle de la auditoría muestra `"(archivo)"` en lugar de la ruta de un logo.
Rutas, permisos y `error_code`, sin otros cambios.

# R-044 Cierre de la FASE 2 y merge a `main` (2026-11-10)

Cierre de la FASE 2 (datos maestros), con las siete entregas cerradas y validadas por el usuario (R-030 a R-043). El detalle está en el
[cierre de la fase](../../03-plan-desarrollo/fases/backend/fase-02-datos-maestros.md#cierre-de-la-fase-2).

| Tema | Decisión | Quién |
|---|---|---|
| Entregas | 2.1 (R-030, R-031), 2.2a (R-032, R-033), 2.2b (R-034, R-035), 2.3 (R-036, R-037), 2.4 (R-038, R-039), 2.5 (R-040, R-041) y 2.6 (R-042, R-043): COMPLETADO, cada una con su validación de Postman y sus cifras en el documento de la fase | Cierre |
| FASE 2 | COMPLETADO: el criterio de cierre de la fase se cumple, punto por punto en el cierre de la fase | Usuario |
| Cifras al cerrar | Rutas de la API de 26 a 58; permisos de 123 a 127; migraciones de 32 a 34. Suite sobre el código de `3df22d1` (lo posterior solo cambia documentación): 988 casos; 10816 aserciones en SQLite con 3 omitidos (solo de MySQL) y 10836 en MySQL (`syncvet_test_mysql`), sin omitidos | Cierre |
| Deudas | Resueltas en la fase: 13 (R-042, R-043) y 14 (R-040, R-041). La 2, revisada en los seis modelos de la fase (`Branch`, `Warehouse`, `User`, `Veterinarian`, `Service` y `AuditLog`) y abierta para los modelos de las fases siguientes. Abiertas: 2, 3, 5, 6, 15 (nueva, R-041) y 16 (nueva, R-043) | Cierre |
| Módulos | Sucursales y depósitos, usuarios, roles y permisos, veterinarios, servicios, configuración de la clínica y auditoría: COMPLETADO (`docs/modules/README.md`) | Cierre |
| Pendiente para la FASE 3 | La deuda 16 (la zona de las columnas `timestamp` de MySQL), antes del diseño; en Postman, la baja de un veterinario con uso (R-037) y un servicio usado (2.4), cuando la API cree agendas o turnos | Usuario |
| Merge a `main` | Autorizado por el usuario el 2026-11-10: merge `--no-ff` desde `feature/fase-02-06-auditoria`, que contiene el diseño (`docs/fase-02-diseno`) y las siete entregas encadenadas. Antes del push de `main`, la suite completa en `main`, en SQLite y después en MySQL. Las ramas de la fase se conservan. El hash del merge no puede ir en el commit que lo precede: se informa al usuario y se anota en el handoff al empezar la FASE 3 | Usuario |
| Siguiente | Análisis de la FASE 3 (agenda y turnos), empezando por la deuda 16 | Usuario |

**Qué no cambió.** Código, rutas, permisos, `error_code`, contrato de respuesta y colecciones: el cierre solo documenta.

# R-045 Suite más rápida, antes de la FASE 3 (2026-11-10)

Rama `chore/suite-rapida`, desde `main`. Sin cambios en reglas de negocio, en la API ni en el código de `app/`: solo tests, scripts de
composer y documentación. El detalle y las cifras están en [`development/testing.md`](../../04-desarrollo/testing.md#velocidad).

| Tema | Decisión | Quién |
|---|---|---|
| Medición | Antes, con Xdebug en modo `debug` (el de Laragon): SQLite de 7 min 40 s a 8 min 55 s; MySQL, 9 min 53 s. El tiempo estaba repartido entre muchos tests; el más lento, `RoleAccessTest::test_each_route_requires_exactly_its_own_permission` (30 s con Xdebug, 4,7 s sin él) | Implementación |
| Xdebug | Los tests corren con `XDEBUG_MODE=off` (scripts de composer, con `@putenv`, que heredan los procesos de `--parallel`). Xdebug no se desinstala. SQLite, en serie: 3 min 17 s; MySQL, en serie: de 4 min 50 s a 6 min 22 s | Implementación |
| Paralelo | `brianium/paratest` 7.20 (desarrollo). Estable en Windows: 3 corridas en SQLite (73 a 75 s) y 3 en MySQL (3 min 30 s a 4 min 10 s), todas en verde. Cada proceso tiene su base (`…_test_N`) y sus discos falsos. `composer test:fast` lo usa durante el trabajo; las cifras del cierre siguen saliendo de la suite en serie (en paralelo hay 2 aserciones menos de `ExceptionArgumentsTest`, por la traza más corta) | Implementación; el merge, a autorizar por el usuario |
| Scripts | `composer test` (SQLite en serie), `composer test:mysql` y `composer test:fast` (SQLite en paralelo), sin el límite de 300 s de composer | Implementación |
| Red | Ya estaban `Http::preventStrayRequests()` global, Have I Been Pwned simulado y `EMAIL_DNS_CHECK=false`. Nuevo `EmailDnsCheckTest` (3 casos): con la validación DNS prendida, un dominio `.invalid` se rechaza sin consultar al DNS en el alta y la edición de clientes, en los usuarios y en `contact_email`; con la validación apagada, se acepta | Implementación |
| Sin cambios | SQLite sigue en archivo (con `:memory:`, `RefreshDatabase` migraría en cada test); `BCRYPT_ROUNDS` ya era 4; las imágenes de los tests ya eran de menos de 1 KB | Implementación |
| Test dependiente del reloj | `ClientDeleteTest::test_the_same_long_document_can_be_deleted_twice` falló una vez en paralelo con MySQL: tomaba el timestamp después del `DELETE` y el segundo cambió en el medio. Ahora congela el reloj (`freezeTime()`), con las mismas aserciones | Implementación |
| Suite | 991 casos (988 + 3 de `EmailDnsCheckTest`). SQLite: 10833 aserciones (10816 + 17), 3 omitidos (solo de MySQL). MySQL: 10853 aserciones (10836 + 17), sin omitidos | Implementación |
| Auditoría de dependencias | `composer audit` informa avisos de seguridad de `symfony/http-foundation`, `symfony/http-kernel` y `symfony/routing`, que ya estaban antes (no son de paratest). Fuera del alcance de esta rama | Usuario, a decidir |

# R-046 Avisos de seguridad de las dependencias (2026-11-10)

Rama `chore/dependencias`, desde `main` (después del merge de `chore/suite-rapida`, R-045). Solo cambia `composer.lock`: ni
`composer.json`, ni el código, ni la API.

| Tema | Decisión | Quién |
|---|---|---|
| Avisos | `composer audit` informaba 6 avisos en 3 paquetes, todos en la versión 7.4.22 y corregidos en la 7.4.23. La base de avisos no trae la gravedad de ninguno. `symfony/http-foundation`: CVE-2026-118514 (un pedido `HEAD` saltea la opción `methods` de `access_control` y de los firewalls; corrección incompleta de CVE-2026-45075), reglas de ruta aplicadas delante de PHP que se saltean agregando la ruta al nombre del script, `private_ranges` de los proxies de confianza que acepta direcciones IPv4 mapeadas, 6to4, Teredo o NAT64, y URLs generadas que pueden apuntar a un host elegido por el cliente. `symfony/http-kernel`: CVE-2026-123339 (los fragmentos inline dejan que unas cabeceras reenviadas no confiables fijen el host, el esquema y el puerto de las URLs del pedido principal). `symfony/routing`: CVE-2026-118516 (los marcadores de host pueden coincidir con el comienzo de la ruta en los matchers compilados) | Implementación |
| Actualización | `composer update symfony/http-foundation symfony/http-kernel symfony/routing --with-dependencies`, dentro de las restricciones de `composer.json` (sin cambios en él): `symfony/http-foundation`, `symfony/http-kernel` y `symfony/routing` de 7.4.22 a 7.4.23; con ellas, `symfony/deprecation-contracts` y `symfony/event-dispatcher-contracts` de 3.7.1 a 3.8.1. Ninguna versión mayor | Usuario |
| Resultado | `composer audit` sin avisos | Implementación |
| Suite | `composer test:fast`: 991 casos en verde, 3 omitidos. Para el cierre, en serie: SQLite, 991 casos con 10833 aserciones y 3 omitidos (solo de MySQL); MySQL (`syncvet_test_mysql`), 991 casos con 10853 aserciones, sin omitidos. Las mismas cifras que en R-045 | Implementación |
| Regla nueva | En cada cierre de fase, `composer audit` sin avisos (`CLAUDE.md` §2, `development/testing.md`) | Usuario |

# R-047 Análisis y diseño de la FASE 3: agenda y turnos, y la solución de la deuda 16 (2026-11-10)

Rama `docs/fase-03-diseno`, desde `main` (después de `115435b`). Solo documentación y el script de `/validar-docs`: ni código de `app/`,
ni migraciones, ni la API. El análisis (solo lectura) confirmó la FASE 2 cerrada e integrada a `main` con el merge `cc78b38`, y después
`27f3294` (R-045) y `115435b` (R-046); la suite en paralelo en verde (991 casos, 3 omitidos). El diseño está en
[`fases/fase-03-agenda-y-turnos.md`](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md).

| Tema | Decisión | Quién |
|---|---|---|
| Deuda 16 | `'timezone' => env('DB_TIMEZONE', '-03:00')` en la conexión `mysql`, sin migraciones (la sesión de desarrollo ya es UTC-3, así que los datos no cambian). Regla en `CLAUDE.md` §3: las horas de negocio las pone PHP, nunca `NOW()`, `CURRENT_TIMESTAMP` ni `useCurrent()` en código nuevo; las columnas nuevas de hora de negocio, en `datetime`. Excepción documentada al "nunca un `-03:00` fijo": es la sesión de MySQL, no una conversión en PHP (MySQL de Laragon no tiene cargadas las zonas con nombre). Va como entrega 3.0 | Usuario |
| Deuda 17 | Nueva, de prioridad baja: el tope de 2038 de las columnas `timestamp` | Usuario |
| P1 Recurrentes (D3) | Todos al crear la serie (semanal o cada N semanas, con fecha de fin o cantidad, hasta 52), agrupados por `series_id`; si una fecha choca con otro turno, un bloqueo o el horario, se rechaza la serie entera con la lista de fechas en conflicto. D3 pasa a decidida | Usuario |
| P2 Sobreturno | Solo explícito (`overbook: true`), con permiso propio del administrador y la recepción; columna `is_overbooked`; se superpone con otro turno, pero no sale del horario ni entra en un bloqueo | Usuario |
| P3 Cancelación | Sin límite de anticipación; motivo obligatorio | Usuario |
| P4 Sin paciente | Con paciente, con un cliente sin paciente, o con nombre y teléfono de contacto (columnas nuevas); para fichar hace falta el paciente | Usuario |
| P5 Duración | `ends_at` por defecto: `starts_at` más la duración del servicio, o `slot_minutes` de la agenda; editable | Usuario |
| P6 Fuera del horario | Fuera del horario o en un bloqueo → 422 siempre | Usuario |
| P7 Mover y cancelar | El administrador y la recepción; el veterinario ficha y marca atendido; un turno fichado, atendido, ausente o cancelado no se mueve | Usuario |
| P8 Cola del día | El veterinario, la de sus agendas en su sucursal actual; la recepción, la de toda la sucursal | Usuario |
| P9 a P11 Agendas | Las arma solo el administrador; veterinario, servicio o los dos (al menos uno); un veterinario o servicio inactivo no recibe agendas nuevas; se inactiva siempre y se elimina solo sin turnos; `Schedule` y `ScheduleHour` auditables | Usuario |
| P12 Ausente | Lo marca la recepción a mano; el automático, más adelante | Usuario |
| Entregas | 3.0 (deuda 16), 3.1 (agendas, horario y bloqueos, con los pendientes de Postman de la FASE 2), 3.2a (turnos únicos), 3.2b (estados), 3.3 (recurrentes) y 3.4 (disponibilidad y cola del día): los estados antes que la recurrencia | Usuario |
| Diseño propuesto | Permisos 127 → 135 (`schedule-blocks.index`, `.store` y `.destroy`, `appointments.overbook`, `.attend`, `.no-show` y `.queue`, y `schedules.availability`); rutas 58 → 77; migraciones 34 → 36. `branch_id` y `veterinarian_id` del turno salen de la agenda; `online_booking` fuera de la API y `AppointmentSource::ONLINE` rechazada en el MVP; `attended_at` y `no_show_at` como columnas `datetime`; "atendido" y la consulta de la FASE 4, como propuesta para esa fase. Preguntas del diseño 13 a 25, sin responder | Diseño, a aprobar por el usuario |
| Hallazgos del análisis | `ClientService::delete` no cuenta los turnos como uso del cliente, y `PatientService::delete` revisa el uso antes de su transacción y sin bloqueo: el diseño los corrige en la 3.2a (pregunta 21) | Diseño, a aprobar por el usuario |
| `/validar-docs` | `database/testing.sqlite_test_N` (`development/testing.md`) era un falso positivo de "rutas inexistentes": es la base que crea cada proceso de `composer test:fast`. El script suma `GENERATED_PATHS`, con su prueba de regresión | Implementación |
| Handoff | Se anotan los merges `cc78b38` (FASE 2), `27f3294` (R-045) y `115435b` (R-046), y la suite de 991 casos | Implementación |

**Qué no cambió.** Código de `app/`, rutas, permisos, migraciones, `error_code` y colecciones de Postman: el diseño solo documenta.
`CLAUDE.md` §3 suma desde ahora la regla de no usar la hora de MySQL en código nuevo; la zona fija de la sesión llega con la entrega 3.0.

# R-048 Aprobación del diseño de la FASE 3 (2026-11-10)

El usuario aprobó el diseño de la FASE 3 (R-047) y respondió las preguntas del diseño 13 a 25. Rama `docs/fase-03-diseno`; solo
documentación. El detalle está en las [preguntas del diseño](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#preguntas-del-diseño) y en el
[orden de bloqueo](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#orden-de-bloqueo).

| Tema | Decisión | Quién |
|---|---|---|
| Preguntas 13, 14, 16, 17, 18, 20, 21, 22, 23, 24 y 25 | Según la propuesta: baja física de la agenda (excepción a "datos maestros con `SoftDeletes`"); una agenda con turnos no cambia de sucursal ni de veterinario; turnos desde el comienzo del día de hoy; mover conserva el estado; el veterinario marca atendido solo lo suyo o de agendas sin veterinario; `attended_at` y `no_show_at` como columnas; los turnos son uso del cliente y las bajas de paciente y cliente revisan el uso con la fila bloqueada; un bloqueo sobre turnos se crea y los lista; `ScheduleBlock` auditable; una agenda con turnos futuros se inactiva; la cola del veterinario es una vista por defecto | Usuario |
| Pregunta 15 | Según la propuesta (409 también con un turno del mismo veterinario en otra agenda), con dos precisiones: el sobreturno explícito también se superpone con el turno del mismo veterinario en otra agenda; y la fila del veterinario (`lockForUpdate` sobre `veterinarians`) se bloquea **antes** que la de la agenda | Usuario |
| Orden de bloqueo | Uno solo para la fase, compatible con la FASE 2: clínica → veterinario → servicio → agenda → paciente → turno. Cambia el de R-047 (que bloqueaba la agenda antes que el veterinario y el servicio, también en la 3.1). El alta de un turno lee la agenda sin bloqueo, bloquea en orden y la vuelve a leer: si cambió su veterinario o su servicio, repite (hasta 3 veces; después, 409 `CONFLICT`). El fichado ya no bloquea al paciente | Diseño, aprobado por el usuario |
| Pregunta 19 | Cambiada: el ausente se deshace el mismo día del turno, por el administrador y la recepción, con `POST appointments/{appointment}/undo-no-show` y el permiso `appointments.no-show` (excepción en `ROUTES_WITH_ANOTHER_PERMISSION`); vuelve a confirmado con `no_show_at` en `null` y queda en la auditoría; pasado el día, 422 `APPOINTMENT_NO_SHOW_NOT_UNDOABLE`. Como vuelve a ocupar el horario, revisa la superposición (409 `APPOINTMENT_OVERLAP`, salvo un sobreturno) | Usuario |
| Cifras | Rutas 58 → **78** (una más que en R-047: deshacer el ausente); permisos 127 → **135** (sin cambios: deshacer usa `appointments.no-show`); migraciones 34 → 36 | Diseño |
| Diseño | Aprobado | Usuario, el 2026-11-10 |
| Siguiente | Entrega 3.0 (deuda 16) en `feature/fase-03-00-zona-mysql`, desde `docs/fase-03-diseno` | Usuario |

**Qué no cambió.** Código, rutas, permisos, migraciones, `error_code` y colecciones: la aprobación solo documenta.

# R-049 Entrega 3.0 de la FASE 3: zona de la sesión de MySQL (deuda 16), revisada, corregida y cerrada (2026-11-10)

Rama `feature/fase-03-00-zona-mysql`, desde `docs/fase-03-diseno`. Implementación en el commit WIP `7c92097`, correcciones de la
revisión en `a0f9a4e` y el cierre en un commit aparte. El detalle está en el
[resultado de la 3.0](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#entrega-30).

| Tema | Decisión | Quién |
|---|---|---|
| Zona de la sesión | `'timezone' => env('DB_TIMEZONE') ?: '-03:00'` en las conexiones `mysql` y `mariadb`, como decidió R-047, con `?:` en lugar del segundo argumento de `env()`: una variable vacía daba `""` y `SET time_zone=''` rompía toda conexión (hallazgo 2 de la revisión). `DB_TIMEZONE=-03:00` en `.env.example`. Sin migraciones ni cambios en los datos | Usuario (R-047); `?:`, revisión, corrección pedida por el usuario |
| Reloj de MySQL | `NoDatabaseNowTest` rechaza en `app/`, `routes/`, las migraciones, los seeders y las factories `useCurrent()` y toda función de reloj de MySQL dentro de un texto, en cualquier combinación de mayúsculas: `NOW()`, `CURRENT_TIMESTAMP`, `CURDATE()`, `CURRENT_DATE`, `CURTIME()`, `CURRENT_TIME`, `SYSDATE()`, `LOCALTIME`, `LOCALTIMESTAMP`, `UTC_TIMESTAMP()`, `UTC_DATE()`, `UTC_TIME()` y `UNIX_TIMESTAMP()` sin argumentos. Las dos columnas de hoy (`inventory_movements.created_at` y `failed_jobs.failed_at`) quedan permitidas por archivo y columna (hallazgos 1 y 4) | Diseño (R-047); ampliación, revisión, pedida por el usuario |
| Fechas viejas | En una fecha anterior al 15/10/2024 con Asunción en UTC-4, la ida y la vuelta por PHP son exactas, pero el instante dentro de MySQL queda corrido una hora: esas fechas no se comparan con la hora de MySQL ni se convierten en SQL (`CLAUDE.md` §3; caso de 2023 en `MySqlSessionTimezoneTest`; hallazgo 3) | Revisión, pedida por el usuario |
| Desvíos 1 a 3 | Aceptados: la reconexión sobre una copia de la conexión `mysql` (para no cortar la transacción de `RefreshDatabase`); el buscador con el tokenizer de PHP; la documentación de `development/`, la deuda técnica, el registro y el roadmap en el cierre. El 4 (Postman en la implementación) lo cubre la validación | Usuario |
| Documentación | `development/setup.md` (sección nueva, `DB_TIMEZONE`) y `development/deployment.md`: la variable en el `.env`, sin dejarla vacía | Usuario |
| Deudas | 16 resuelta; 17 (el tope de 2038 de `timestamp`) sigue abierta, de prioridad baja | Usuario |
| Validación | El 2026-11-10, con `DB_TIMEZONE=-03:00` en su `.env`, la colección `syncvet-fase-02-auditoria` como regresión (la entrega no tiene endpoints): 47 de 47 pruebas, 0 errores | Usuario |
| Tests | Suite del 2026-11-10 sobre `a0f9a4e`: 1007 casos; 10851 aserciones en SQLite (8 omitidos, solo de MySQL) y 10885 en MySQL, sin omitidos; `composer test:fast`, 1007 casos en verde | Implementación |
| Siguiente | Entrega 3.1 (agendas, horario semanal y bloqueos) en `feature/fase-03-01-agendas`, creada desde `feature/fase-03-00-zona-mysql`. Sin merge a `main` | Usuario |

**Qué no cambió.** Código de `app/`, rutas (58), permisos (127), migraciones (34), `error_code`, la API y las colecciones de Postman.

# R-050 Entrega 3.1 de la FASE 3: agendas, horario semanal y bloqueos, revisada, corregida y cerrada (2026-11-10)

Rama `feature/fase-03-01-agendas`, desde `feature/fase-03-00-zona-mysql`. Implementación en el commit WIP `aa96fed`; las correcciones
de la revisión y el cierre van en el commit de cierre. El detalle está en el [resultado de la 3.1](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#entrega-31).

| Tema | Decisión | Quién |
|---|---|---|
| Alcance | Agendas con su horario semanal y sus bloqueos según el diseño (P9 a P11, preguntas 13, 14 y 22 a 24): 8 rutas nuevas (58 → 66), 3 permisos nuevos `schedule-blocks.index`, `.store` y `.destroy` (127 → 130), sin migraciones (34). `ScheduleException` nueva (409 `SCHEDULE_IN_USE` y 422 `SCHEDULE_NOT_EDITABLE`); `AppointmentStatus::occupiesSlot()`; `Schedule`, `ScheduleHour` y `ScheduleBlock` auditables | Diseño (R-047, R-048) |
| Bloqueos | Crearlos y borrarlos sigue siendo solo del administrador en el MVP: el veterinario no bloquea su propia agenda (por vacaciones, por ejemplo); lo pide al administrador. El recepcionista y el veterinario solo los listan | Usuario (en la revisión) |
| Orden de bloqueo de la edición | La edición de una agenda nunca bloquea un veterinario después de la agenda (hallazgo 1 de la revisión: un cambio de sucursal sin `veterinarian_id` leía el veterinario con la agenda ya bloqueada, y el alta de un turno de la 3.2a, veterinario → agenda, podía trabarse con ella). Lee el veterinario de la agenda sin bloquearla, bloquea con `sharedLock` ese y el nuevo (por id), después el servicio y la agenda, y con la agenda bloqueada verifica que su veterinario no cambió: si cambió, deshace y repite, hasta 3 intentos (`ScheduleService::UPDATE_ATTEMPTS`); después, 409 `CONFLICT`, "La agenda cambió mientras se editaba: volvé a intentarlo." (`CLAUDE.md` §3 y §4) | Revisión, corrección pedida por el usuario |
| Tests de la revisión | Las cuatro reglas que pasaban con su mutación tienen test (veterinario nuevo inactivo al editar, sucursal de destino inactiva, nombre único al mover de sucursal, y fichados y atendidos en `affected_appointments`); el horario anterior queda intacto si la edición falla (por la validación y por `SCHEDULE_NOT_EDITABLE`); la superposición de tramos en el alta (hallazgos 2 y 3). Un comentario de `routes/api.php` y un docblock de `ServiceDeleteTest` en su lugar (hallazgo 4) | Revisión, pedida por el usuario |
| Precisión del diseño | `DELETE schedule-blocks/{scheduleBlock}` no lleva la agenda en la URL, así que el caso "bloqueo de otra agenda en la URL → 404" de la tabla de tests del diseño no existe. El 404 de una agenda o un bloqueo de otra clínica está cubierto en las 7 rutas con parámetro (`RouteBindingTest`, `ScheduleBlockTest`) (hallazgo 5) | Revisión, registrada por pedido del usuario |
| Desvíos 1 a 5 | Aceptados: (1) los turnos afectados por un bloqueo no muestran `contact_name` ni `contact_phone` hasta la 3.2a (las columnas llegan con su migración); (2) al mover una agenda de sucursal se revisa que el veterinario trabaje en la nueva, y si está inactivo se acepta igual (es el que la agenda ya tiene); (3) el listado de bloqueos muestra por defecto los que terminan desde hoy, con `from`/`to`, y `search` busca en el motivo; (4) el horario acepta hasta 6 tramos por día y 42 en total, y `hours: null` responde 422; (5) la documentación va en el cierre. El informe de la implementación no está en el repositorio: el resumen es del usuario | Usuario |
| Pendientes para la 3.2a | En su colección de Postman: mover una agenda con turnos (422 `SCHEDULE_NOT_EDITABLE`) y la baja de una agenda con un turno real (409 `SCHEDULE_IN_USE`). En su diseño: evaluar `DB::transaction` con reintentos ante un *deadlock* (MySQL, 1213) en el alta y el mover de turnos, para que nunca respondan 500 (anotado en el [diseño de la 3.2a](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#32a-turnos-únicos)) | Usuario |
| Pendientes de la FASE 2 | Probados en la colección `syncvet-fase-03-agendas`: la baja del veterinario con una agenda (409 `VETERINARIAN_IN_USE`, R-037), la baja del servicio usado (409 `SERVICE_IN_USE`) y su cambio de nombre (422 `SERVICE_NAME_NOT_EDITABLE`) (entrega 2.4) | Diseño |
| Deuda 2 | Revisados `Schedule`, `ScheduleHour` y `ScheduleBlock` ([deuda técnica](../../03-plan-desarrollo/deuda-tecnica.md)) | Implementación |
| Validación | El 2026-11-10, en otra PC, con el Collection Runner (una iteración): `syncvet-fase-03-agendas`, 68 de 68 pruebas, 0 errores. Las correcciones de la revisión no cambian la API | Usuario |
| Tests | Suite del 2026-11-10 sobre el código del cierre, en serie: 1108 casos; 11845 aserciones en SQLite (8 omitidos, solo de MySQL) y 11879 en MySQL, sin omitidos; `composer test:fast`, 1108 casos en verde | Cierre |
| Siguiente | Entrega 3.2a (turnos únicos) en `feature/fase-03-02a-turnos`, creada desde `feature/fase-03-01-agendas`. Sin merge a `main` | Usuario |

**Qué no cambió.** Migraciones (34), las colecciones de Postman anteriores y los contratos, permisos y `error_code` de las fases
anteriores.

# R-051 Entrega 3.2a de la FASE 3: turnos únicos, revisada, corregida y cerrada (2026-11-10)

Rama `feature/fase-03-02a-turnos`, desde `feature/fase-03-01-agendas`. Implementación en el commit WIP `fa03b6f`, correcciones de la
revisión en `b0dd969` y el cierre en un commit aparte, solo de documentación. El detalle está en el
[resultado de la 3.2a](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#entrega-32a).

| Tema | Decisión | Quién |
|---|---|---|
| Alcance | Turnos únicos según el diseño (P2, P4 a P7, preguntas 15 a 17 y 21): alta, detalle, listado, edición y mover, sin superposición en la agenda ni con el mismo veterinario en otra agenda (409 `APPOINTMENT_OVERLAP`), dentro del horario (422 `APPOINTMENT_OUTSIDE_HOURS`), fuera de los bloqueos (422 `APPOINTMENT_IN_BLOCK`), editables solo a confirmar o confirmados (422 `APPOINTMENT_NOT_EDITABLE`), y el sobreturno con `appointments.overbook`. 4 rutas nuevas (66 → 70), 1 permiso nuevo (130 → 131) y 1 migración (34 → 35: contacto, sobreturno y el índice `(veterinarian_id, starts_at)`). `CLIENT_IN_USE` suma los turnos, y las bajas del cliente y del paciente revisan el uso con su fila bloqueada | Diseño (R-047, R-048) |
| Desvíos 1 a 13 | Aceptados: (1) el cliente con `sharedLock` después del paciente; (2) una edición que no mueve no bloquea el veterinario ni las agendas; (3) mover sin `ends_at` conserva la duración; (4) mover exige la agenda y su veterinario activos; (5) reintentos ante cambios entre la lectura y el bloqueo y ante un *deadlock*; (6) el paciente fallecido ya asignado se acepta al editar; (7) con paciente, otro cliente → 422; (8) `contact_name` con su regex; (9) el listado por `starts_at`, solo `from` es solo ese día, `status` uno o varios; (10) `is_overbooked` tal como está; (11) el contacto en `affected_appointments`; (12) la baja del cliente con los pacientes no eliminados y los turnos; (13) los mensajes que el diseño no fija. El 5, el 7 y el 13 cambiaron con las decisiones de abajo | Usuario |
| Cliente del turno | Con paciente, el cliente del turno es el **dueño actual** del paciente. Al editar se acepta `client_id` igual al guardado en el turno (del mismo paciente) o al dueño actual, y queda el dueño actual; otro → 422. Reenviar el mismo `patient_id` actualiza el cliente al dueño actual; sin tocar el paciente ni el cliente, el turno queda como estaba (hallazgo 2 de la revisión: después de un cambio de dueño, el formulario completo daba 422) | Usuario |
| Reintentos | Un *deadlock* (40001 o 1213) se repite hasta 3 veces (`AppointmentService::ATTEMPTS`); una espera vencida (1205) no se repite y responde 409 `CONFLICT` enseguida, para no triplicar `innodb_lock_wait_timeout` (hallazgo 7). Nunca un 500 | Usuario |
| Horario | Dos tramos del mismo día que se tocan (08:00–12:00 y 12:00–16:00) cuentan como un horario continuo: un turno de 11:30 a 12:30 entra. Un hueco real entre tramos sigue dando 422 `APPOINTMENT_OUTSIDE_HOURS`. Cambia la regla del diseño ("dentro de **un** tramo") | Usuario (en la revisión) |
| Sobreturno | `is_overbooked` marca solo el turno dado como sobreturno, si al guardarlo se superponía con otro; se recalcula al moverlo y no cambia si después se cancela el otro turno (desvío 10) | Usuario |
| Cambio de dueño | `PatientService::changeOwner` en una transacción: el paciente (`lockForUpdate`) y después los dos clientes, el actual y el nuevo, por id (`lockForUpdate`), en el sentido de los turnos (paciente → cliente); el dueño nuevo eliminado entre la validación y el bloqueo → 422 en `client_id` (hallazgo 8). El usuario había pedido clientes → paciente; eligió este orden cuando se le mostró que el otro chocaba con el de los turnos y podía dar un *deadlock* con un 500 en el cambio de dueño | Usuario |
| Mensaje de `source` | "El origen debe ser recepción, teléfono o WhatsApp: la reserva online no está disponible.", con las etiquetas en español en lugar de los valores internos (desvío 13) | Usuario |
| Tests de la revisión | Dos procesos con una agenda solo de servicio, donde el bloqueo de la agenda es lo único que ordena (hallazgo 1: sin él, dos 201 en el mismo horario y ningún test fallaba); el reloj cerca de la medianoche (3); los rechequeos del reintento al mover (4); el sobreturno con otro turno en el horario (5); el contacto en `affected_appointments` (6). Cada test nuevo falla con su mutación | Revisión, pedidos por el usuario |
| Criterio de cierre de la fase | Cumplido el primer punto: dos altas simultáneas del mismo horario, una 201 y la otra 409, en MySQL con dos procesos aparte, con una agenda con veterinario y con una solo de servicio | Cierre |
| Pendientes de la 3.1 | Resueltos: mover una agenda con turnos (422 `SCHEDULE_NOT_EDITABLE`) y la baja de una agenda con un turno real (409 `SCHEDULE_IN_USE`) en la colección de turnos; los reintentos ante un *deadlock* en el alta y el mover | Implementación |
| Deuda 2 | Revisado `Appointment` ([deuda técnica](../../03-plan-desarrollo/deuda-tecnica.md)) | Implementación |
| Validación | El 2026-11-10, después de las correcciones, con el Collection Runner (una iteración): `syncvet-fase-03-turnos`, 37 requests, 67 de 67 pruebas, 0 errores | Usuario |
| Tests | Suite del 2026-11-10 sobre `b0dd969`, en serie: 1212 casos; 12835 aserciones en SQLite (10 omitidos, solo de MySQL) y 12879 en MySQL, sin omitidos; `composer test:fast`, 1212 casos en verde | Cierre |
| Siguiente | Entrega 3.2b (estados del turno) en `feature/fase-03-02b-estados`, creada desde `feature/fase-03-02a-turnos`. Sin merge a `main` | Usuario |

**Qué no cambió.** Las colecciones de Postman anteriores y los contratos, permisos y `error_code` de las fases anteriores, salvo el
mensaje de `CLIENT_IN_USE` (suma los turnos, pregunta 21) y el cambio de dueño, que ahora responde 422 en `client_id` si el dueño nuevo
se elimina en el medio.

# R-052 Las skills de lectura no bloquean la edición de la sesión (2026-11-10)

Rama `feature/fase-03-02b-estados`, en un commit aparte del trabajo de la 3.2b. Solo cambia el frontmatter de dos skills: ni el
código, ni la API, ni los tests.

| Tema | Decisión | Quién |
|---|---|---|
| Problema | `/retomar` y `/validar-docs` declaraban `disallowed-tools: Edit Write NotebookEdit`: después de usarlas, la sesión seguía sin poder editar archivos, aunque el pedido siguiente fuera una corrección | Usuario |
| Cambio | Se quita `disallowed-tools` de `.claude/skills/retomar/SKILL.md` y de `.claude/skills/validar-docs/SKILL.md`. `allowed-tools` queda: solo aprueba de antemano los comandos de lectura, no restringe | Usuario |
| Solo lectura | La regla sigue escrita en el cuerpo de cada skill ("No modificar archivos…" y "Es de solo lectura…"): la cumple quien la usa, no el frontmatter | Usuario |

# R-053 Entrega 3.2b de la FASE 3: estados del turno, revisada, corregida y cerrada (2026-11-10)

Rama `feature/fase-03-02b-estados`, desde `feature/fase-03-02a-turnos`. Implementación en el commit WIP `a1a1ab4`, correcciones de la
revisión en `d811fa1` y el cierre en un commit aparte, solo de documentación. El cambio de las skills de lectura de la misma rama
(`964db22`) es R-052. El detalle está en el [resultado de la 3.2b](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#entrega-32b).

| Tema | Decisión | Quién |
|---|---|---|
| Alcance | Las transiciones del turno según el diseño (P3, P7, P12 y preguntas 18 a 20): confirmar, fichar, atendido, ausente, deshacer el ausente el mismo día y cancelar con motivo, en `POST appointments/{appointment}/…`; cualquier otra, también repetir la misma, → 422 `APPOINTMENT_INVALID_TRANSITION`; atendido y cancelado son finales. 6 rutas nuevas (70 → 76), 2 permisos nuevos (`appointments.attend` y `appointments.no-show`; 131 → 133; `undo-no-show` usa `appointments.no-show`) y 1 migración (35 → 36: `attended_at` y `no_show_at`, `datetime`) | Diseño (R-047, R-048) |
| Fichar (pregunta 18) | Fichar queda restringido igual que marcar atendido: cambia la respuesta "Fichar, cualquiera" | Usuario (en la implementación) |
| Regla del veterinario | Se aplica por el rol (o la ficha): sin `appointments.update`, un veterinario con ficha (no eliminada) ficha y marca atendido los turnos de su ficha y los de agendas sin veterinario; sin ficha, o con la ficha eliminada, solo los de agendas sin veterinario. El administrador y quien tiene `appointments.update`, cualquiera (hallazgo 2: un usuario con el rol y sin ficha podía con cualquier turno) | Usuario (en la revisión) |
| Turno ajeno | 403 `APPOINTMENT_NOT_YOURS`, "Ese turno no es de tu agenda.", en lugar del `FORBIDDEN` genérico (desvío 5, hallazgo 1) | Usuario (en la revisión) |
| Deshacer el ausente | Acepta `overbook: true` con `appointments.overbook`, como el alta: con el horario tomado por otro turno, entra con `is_overbooked: true`; sin `overbook`, 409 `APPOINTMENT_OVERLAP` (hallazgo 3). Un turno que ya era sobreturno vuelve sin mandar `overbook`, pero exige el permiso, con el horario tomado o libre; sin el permiso, 403 `FORBIDDEN` | Usuario (en la revisión); las dos precisiones, propuestas por la implementación y aceptadas |
| Desvíos 1 a 12 | Aceptados los 1 a 4 y 6 a 12: (1) `App\Enums\AppointmentTransition` con orígenes y destino; (2) controller y service aparte, y deshacer el ausente en `AppointmentService`; (3) paciente fallecido al fichar → 422 en `patient_id`; (4) la regla del veterinario antes que el estado; (6) todas las transiciones con los reintentos de la 3.2a; (7) deshacer el ausente sin revisar la agenda activa; (8) fichar el día del turno a cualquier hora y el ausente desde el inicio, también días después; (9) el test de dos procesos con fichar y ausente; (10) `RunsConcurrentRequests`; (11) los mensajes; (12) la agenda del día en Postman. El 5 cambió (arriba) | Usuario |
| Correcciones de la revisión | Además de las de arriba: 5 tests de medianoche (fichar a las 23:50 y a las 00:10; deshacer el ausente a las 23:50 y a las 00:10, y de un turno de hace 3 días marcado ausente hoy), con los que calcular el día en UTC hace fallar deshacer el ausente (hallazgo 4); se borran `AppointmentStatus::canTransitionTo()` e `isFinal()`, sin uso (hallazgo 5); el cierre usa R-053, porque R-052 ya era el cambio de las skills (hallazgo 6) | Usuario |
| Postman | `syncvet-fase-03-turnos`, de 37 a 58 requests y de 67 a 107 pruebas: las transiciones, la agenda del día de la corrida (de 00:00 a 23:55), deshacer el ausente con el horario tomado (409 sin `overbook`, 200 con él) y el 403 `APPOINTMENT_NOT_YOURS` con `vet-a` | Implementación y revisión |
| Deuda 2 | `Appointment` suma `attended_at` y `no_show_at` con `AppDateTime` y `startsToday()` ([deuda técnica](../../03-plan-desarrollo/deuda-tecnica.md)) | Implementación |
| Validación | El 2026-11-10, después de las correcciones, con el Collection Runner (una iteración): `syncvet-fase-03-turnos`, 58 requests, 107 de 107 pruebas, 0 errores | Usuario |
| Tests | Suite del 2026-11-10 sobre `d811fa1`, en serie: 1303 casos; 13373 aserciones en SQLite (11 omitidos, solo de MySQL) y 13422 en MySQL, sin omitidos; `composer test:fast`, 1303 casos en verde | Cierre |
| Siguiente | Entrega 3.3 (turnos recurrentes) en `feature/fase-03-03-recurrentes`, creada desde `feature/fase-03-02b-estados`. Sin merge a `main` | Usuario |

**Qué no cambió.** Los contratos, permisos y `error_code` anteriores; el `PATCH` de turnos sigue rechazando un turno que no está a
confirmar ni confirmado (422 `APPOINTMENT_NOT_EDITABLE`), así que un cancelado no se reabre por ningún camino.

# R-054 Entrega 3.3 de la FASE 3: turnos recurrentes, revisada, corregida y cerrada (2026-11-11)

Rama `feature/fase-03-03-recurrentes`, desde `feature/fase-03-02b-estados`. Implementación en el commit WIP `04789c9`, correcciones de
la revisión en `e4fe8c5` y el cierre en un commit aparte, solo de documentación. El detalle está en el
[resultado de la 3.3](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#entrega-33).

| Tema | Decisión | Quién |
|---|---|---|
| Alcance | Series de turnos al darlos (P1): `POST appointments` con `recurrence` (`every_weeks` de 1 a 12 y uno de `count`, de 2 a 52, o `until`), todas las fechas o ninguna (409 `APPOINTMENT_SERIES_CONFLICT` con `errors.conflicts`); cancelar con `scope`. Sin rutas, permisos ni migraciones nuevas (76, 133, 36) | Diseño (R-047, R-048) |
| `overbook` en la serie (desvío 1) | Se acepta (el diseño lo prohibía): vale para todas las fechas, con `appointments.overbook`, y saltea solo la superposición; cada turno queda con `is_overbooked` si se superponía | Usuario (al pedir la entrega; confirmado en la revisión) |
| Alcance `series` (desvío 2) | Un tercer alcance, toda la serie, además de `single` y `following` (`App\Enums\AppointmentCancelScope`) | Usuario (al pedir la entrega; confirmado en la revisión) |
| Desvíos 3 a 5 | Aceptados: (3) con `following` y `series`, de los demás turnos solo los a confirmar o confirmados que todavía no empezaron; el pedido, con las reglas de siempre; (4) `meta.cancelled_count` en toda cancelación, también `single`; (5) mover la serie entera queda fuera del MVP, `recurrence` en el `PATCH` → 422 | Usuario |
| Decisiones de implementación | Aceptadas: "los siguientes" por `starts_at` y por id en el mismo instante; con `until`, de 2 a 52 turnos; `recurrence: null` es un turno único; las reglas de toda la serie con su error de siempre sobre el primer turno y las de cada fecha en `errors.conflicts`, sin cortar en la primera; la serie responde `AppointmentResource` con `meta.series_id` | Implementación; aceptadas por el usuario |
| Tope de un año con `count` (hallazgo 7) | La última fecha, como mucho un año después del primer turno, también con `count` (antes, `count: 52` con `every_weeks: 12` llegaba a 2038) | Usuario (en la revisión) |
| Valores nulos (hallazgo 8) | `recurrence.every_weeks: null` es 1 y `scope: null` es `single` (el frontend manda el formulario completo) | Usuario (en la revisión) |
| Una hora por cancelación (hallazgo 9) | `cancelled_at` es la misma en todos los turnos y es la que decide cuáles ya empezaron | Usuario (en la revisión) |
| Correcciones de la revisión | Además: Newman (`const data` → `body` en 13 scripts de `syncvet-fase-03-turnos`, hallazgo 1); tests de `until` con `every_weeks` (2), de un error y de un *deadlock* a mitad de la serie (3), de dos cancelaciones simultáneas (4, MySQL), del desempate por id y del turno movido antes (5) y de una serie de otra clínica (6) | Usuario |
| Postman | Colección aparte, `syncvet-fase-03-series` (25 requests, 42 pruebas); `syncvet-fase-03-turnos` sin cambios de pedidos (58 requests, 107 pruebas) | Implementación y revisión |
| Validación | El 2026-11-11, después de las correcciones, en otra PC, con el Collection Runner y una iteración: `syncvet-fase-03-series`, 42 de 42 pruebas; `syncvet-fase-03-turnos`, 107 de 107; y `syncvet-fase-03-agendas` como regresión, 68 de 68; 0 errores en las tres | Usuario |
| Tests | Suite del 2026-11-11 sobre `e4fe8c5`, en serie: 1363 casos; 13681 aserciones en SQLite (14 omitidos, solo de MySQL) y 13755 en MySQL, sin omitidos | Cierre |
| Siguiente | Entrega 3.4 (disponibilidad y cola del día) en `feature/fase-03-04-disponibilidad`, creada desde `feature/fase-03-03-recurrentes`. Sin merge a `main` | Usuario |

**Qué no cambió.** Los contratos, permisos y `error_code` anteriores; el turno único responde igual (`ShowAppointmentResource` sin
`meta`). La cancelación de un turno suma `meta.cancelled_count` (antes `meta` era `null`).

# R-055 Entrega 3.4 de la FASE 3: disponibilidad y cola del día, revisada, corregida y cerrada (2026-11-11)

Rama `feature/fase-03-04-disponibilidad`, desde `feature/fase-03-03-recurrentes`. Implementación en el commit WIP `38ca91a`,
correcciones de la revisión en `4862658` y el cierre en un commit aparte, solo de documentación. El detalle está en el
[resultado de la 3.4](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#entrega-34).

| Tema | Decisión | Quién |
|---|---|---|
| Alcance | Disponibilidad por agenda (`GET schedules/{schedule}/availability`) y cola del día (`GET appointments/queue`, P8), solo lectura; 2 rutas (76 → 78) y 2 permisos (`schedules.availability` y `appointments.queue`, de los tres roles; 133 → 135), sin migraciones (36) | Diseño (R-047, R-048) |
| Reglas compartidas (desvío 1) | Las reglas del horario (el horario semanal con los tramos que se tocan como uno, los bloqueos y la superposición en la agenda y del veterinario en sus otras agendas) pasan a `App\Services\ScheduleOccupancy`, que usan el alta, la serie, mover, deshacer el ausente y la disponibilidad: la disponibilidad ofrece un horario solo si el alta lo aceptaría. La revisión confirmó que no cambió ninguna regla del alta y que las lecturas siguen después de los bloqueos | Implementación; aceptada por el usuario |
| D1 | Con ficha de veterinario y sin `veterinarian_id`, la cola muestra sus turnos y los de las agendas sin veterinario de su sucursal actual (desde la 3.2b los ficha y los marca atendidos, R-053); con `veterinarian_id`, solo ese. `meta.includes_unassigned` | Usuario (en la revisión) |
| D2 | La disponibilidad acepta `duration_minutes` opcional (entero de 5 a 720 en múltiplos de 5), que manda sobre la duración del servicio y `slot_minutes` (para un turno con `ends_at` propio); si llega `service_id`, se sigue revisando que esté activo | Usuario (en la revisión) |
| D3 | La cola sigue cortando en 200 pero avisa con `meta.truncated` (`true` si cortó, `false` si no) | Usuario (en la revisión) |
| Agenda de otra sucursal (H11, desvío 11) | La disponibilidad de una agenda de otra sucursal que la actual del usuario se consulta, coherente con el alta, que da turnos en cualquier agenda de la clínica | Usuario (en la revisión) |
| Desvíos 2 a 9 | Aceptados: (2) `schedule_id` en la cola; (3) `meta` de la cola; (4) `veterinarian_id` manda sobre la ficha y `all`; (5) el veterinario o el servicio inactivos, 422 como en el alta; (6) un día anterior a hoy, `slots` vacío; (7) se ofrece si `starts_at >= ahora` (el alta acepta un horario de hoy que ya empezó); (8) fechas solas `Y-m-d`; (9) la colección `syncvet-fase-03-disponibilidad` | Usuario |
| Correcciones de la revisión | Tests reales del tope de 200 y del orden por id en los empates (también en el SQL: el índice `(branch_id, starts_at)` ya los devuelve por id); la raza en el test de N+1; la misma cantidad de consultas de la disponibilidad con un día y con 7 días de 00:00 a 23:55 cada 5 minutos; tres grillas completas que comparan el motivo de cada rechazo; `ScheduleOccupancy` carga solo lo necesario (deshacer el ausente, solo los turnos; el alta, los tramos del día) y calcula el texto local de cada horario una vez | Usuario |
| Postman | Colección aparte, `syncvet-fase-03-disponibilidad`: 50 requests y 81 pruebas (47 y 72 antes de las correcciones) | Implementación y revisión |
| Validación | El 2026-11-11, después de las correcciones, con el Collection Runner y una iteración: `syncvet-fase-03-disponibilidad`, 81 de 81 pruebas, 0 errores | Usuario |
| Tests | Suite del 2026-11-11 sobre `4862658`, en serie: 1395 casos; 13960 aserciones en SQLite (14 omitidos, solo de MySQL) y 14034 en MySQL, sin omitidos | Cierre |
| Siguiente | El cierre de la FASE 3 (`composer audit` sin avisos, el módulo de agenda y turnos COMPLETADO y el merge a `main`). Sin merge a `main` en este cierre | Usuario |

**Qué no cambió.** Los contratos, permisos y `error_code` anteriores; el orden de bloqueo (la 3.4 no bloquea nada); las reglas del
alta, la serie, mover y deshacer el ausente (ahora en `ScheduleOccupancy`, con los mismos tests).

# R-056 Cierre de la FASE 3 y merge a `main` (2026-11-11)

Cierre de la FASE 3 (agenda y turnos), con las seis entregas cerradas y validadas por el usuario (R-049 a R-055). El detalle está en el
[cierre de la fase](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#cierre-de-la-fase-3).

| Tema | Decisión | Quién |
|---|---|---|
| Entregas | 3.0 (R-049), 3.1 (R-050), 3.2a (R-051), 3.2b (R-053), 3.3 (R-054) y 3.4 (R-055): COMPLETADO, cada una con su validación de Postman y sus cifras en el documento de la fase | Cierre |
| FASE 3 | COMPLETADO: el criterio de cierre de la fase se cumple, punto por punto en el cierre de la fase | Usuario |
| Criterio de cierre | Dos altas simultáneas del mismo horario (agenda con veterinario y solo de servicio): un 201 y un 409 `APPOINTMENT_OVERLAP` (`AppointmentConcurrencyTest`, MySQL); cada transición, válida e inválida (`AppointmentTransitionTest` y `UndoNoShowTest`). Corridos en el cierre en MySQL: 55 tests y 263 aserciones, sin omitidos | Cierre |
| Regresión de Postman | Newman, 2026-11-11, servidor en el puerto 8001: `syncvet-fase-03-agendas` (35 pedidos, 68 aserciones), `-turnos` (58 y 107), `-series` (25 y 42), `-disponibilidad` (50 y 81), `syncvet-fase-02-veterinarios` (32 y 75) y `-servicios` (27 y 64), sin fallas. La guía de Postman de veterinarios y servicios apunta sus pendientes a la colección de agendas (pedidos 21 a 23) | Cierre |
| `composer audit` | Sin avisos; nada que actualizar (R-046) | Cierre |
| Cifras al cerrar | Rutas de la API de 58 a 78; permisos de 127 a 135; migraciones de 34 a 36. Suite sobre el código de `4862658` (lo posterior solo cambia documentación): 1395 casos; 13960 aserciones en SQLite con 14 omitidos (solo de MySQL) y 14034 en MySQL (`syncvet_test_mysql`), sin omitidos | Cierre |
| Deudas | Resuelta en la fase: 16 (R-049). Nueva: 17 (R-047). La 2, revisada en los modelos de la fase (`Schedule`, `ScheduleHour`, `ScheduleBlock` y `Appointment`) y abierta para las fases siguientes. Abiertas: 2, 3, 5, 6, 15 y 17 | Cierre |
| Módulos | Agenda y turnos: COMPLETADO (`docs/modules/README.md`) | Cierre |
| Pendiente para la FASE 4 | La relación entre "atendido" y la consulta, con la propuesta de la FASE 3 (la consulta se abre desde un turno fichado y lo marca atendido en la misma transacción); anotado también en el documento de la FASE 4 | Usuario |
| Merge a `main` | Autorizado por el usuario el 2026-11-11: merge `--no-ff` desde `feature/fase-03-04-disponibilidad`, que contiene el diseño (`docs/fase-03-diseno`) y las seis entregas encadenadas. Antes del push de `main`, la suite completa en `main`, en serie, en SQLite y después en MySQL. Las ramas de la fase se conservan. El hash del merge no puede ir en el commit que lo precede: se informa al usuario y se anota en el handoff al empezar lo siguiente | Usuario |
| Siguiente | Decidir con el usuario entre empezar el frontend y el análisis de la FASE 4 (historia clínica) | Usuario |

**Qué no cambió.** Código, rutas, permisos, `error_code`, contrato de respuesta y colecciones: el cierre solo documenta (y corrige en la
guía de Postman los pendientes de veterinarios y servicios, ya probados en la colección de agendas).

# R-057 Monorepo: backend, frontend y documentación en un solo repositorio (2026-11-11)

El frontend, que iba a vivir en otro repositorio (`syncvet-frontend`), pasa al mismo repositorio que el backend, con la documentación
de los dos en `docs/`. Rama `chore/monorepo`, desde `main` en `3ead398` (el merge de la FASE 3, R-056; etiqueta local
`pre-monorepo`).

| Tema | Decisión | Quién |
|---|---|---|
| Motivo | Un solo estado de Git, una sola documentación y un solo `/retomar` para el backend y el frontend: el usuario trabaja en más de una PC y un cambio de la API y su pantalla van juntos | Usuario |
| Estructura | `CLAUDE.md`, `README.md` y `.gitignore` en la raíz; `.claude/skills/`; `backend/` (todo Laravel, con su `CLAUDE.md` y su `README.md`); `frontend/` (vacío hasta la F0 del frontend, con `README.md` y `CLAUDE.md`); `docs/` en `00-vision`, `01-arquitectura`, `02-requisitos`, `03-plan-desarrollo`, `04-desarrollo` y `05-pruebas` | Usuario |
| Historia | El movimiento va en un commit propio, solo de renombres (`git mv`, 563 archivos al 100 %), para que `git log --follow` siga cada archivo; el merge a `main`, `--no-ff` y sin squash | Usuario |
| Reparto de `CLAUDE.md` | El de la raíz tiene el proceso y las reglas comunes (metodología, voseo, Git, documentación por cambio); `backend/CLAUDE.md`, el estado, las convenciones, la arquitectura, las reglas de negocio (§6) y la deuda (§7) del backend; `frontend/CLAUDE.md`, las del frontend. Sin `@import`: el `CLAUDE.md` de la raíz pide leer el del proyecto antes de tocarlo y `/retomar` lee los dos | Usuario |
| Documentos | Las reglas de negocio siguen en `backend/CLAUDE.md` §6 y `docs/02-requisitos/README.md` apunta ahí; el estado por módulo pasa a `docs/03-plan-desarrollo/modulos.md`; `principios.md` y las referencias de la competencia, a `00-vision`; `architecture.md` y `technology-stack.md`, a `01-arquitectura`; las fases del backend, a `docs/03-plan-desarrollo/fases/backend/` | Usuario |
| Convención de rutas | Entre comillas invertidas, `docs/…`, `backend/…` y `frontend/…` se leen desde la raíz; `app/`, `tests/`, `config/`, `routes/`, `database/`, `lang/` y `bootstrap/`, desde `backend/`. Los enlaces de Markdown, relativos a su archivo. `/validar-docs` la aplica | Implementación; aprobada por el usuario |
| Carpetas de trabajo | Claude Code, las skills y Newman, desde la raíz (las colecciones suben archivos con rutas relativas a la raíz); `php artisan` y `composer`, desde `backend/` | Implementación; aprobada por el usuario |
| `FictitiousDocumentsTest` | Revisa también las colecciones de Postman (`docs/05-pruebas/postman/*.json`), resueltas desde la raíz del repositorio. Encontró `99300002-8` en la de clientes: una base ficticia con el dígito verificador mal a propósito (para el 422), anotada en `ALLOWED` con su motivo | Usuario |
| Laragon | El vhost automático de nginx apuntaba a `public/` de la raíz: se reemplaza por uno propio, `syncvet.test.conf`, con `root` en `backend/public` (sin el prefijo `auto.`, que Laragon regenera). Pasos por PC en [`setup.md`](../../04-desarrollo/setup.md#migración-al-monorepo-r-057) | Implementación |
| Verificación | Suite en serie desde `backend/`: 1395 casos en SQLite (14 omitidos) con 13964 aserciones y en MySQL con 14038, sin omitidos (13960 y 14034 antes; las 4 de más son de `FictitiousDocumentsTest`). Newman de las 14 colecciones, servidor en el puerto 8001 levantado desde `backend/`: 13 sin fallas y 1 falla en clientes (deuda 18); corregida, clientes 48 de 48. `/validar-docs` sin hallazgos; su prueba de regresión, 40 de 40 | Implementación |
| Discrepancia encontrada | La colección de clientes espera el mensaje del 409 de la baja sin "turnos"; la API lo cambió en la 3.2a (`fa03b6f`). Ya estaba en `main`: no la causa el monorepo. Registrada como deuda 18 y resuelta en esta rama: el texto esperado pasa al mensaje actual de la API; Newman de clientes, 48 de 48 | Usuario |
| Cierre de fase | Cada cierre de fase corre con Newman todas las colecciones de `docs/05-pruebas/postman`, no solo las de la fase: la deuda 18 pasó un cierre (R-056) porque solo se corrieron las de la FASE 3 y las afectadas. Regla en el `CLAUDE.md` de la raíz (§2) y en `/cerrar-entrega` | Usuario |
| Merge a `main` | Autorizado por el usuario el 2026-11-11: `--no-ff` desde `chore/monorepo`, con la suite en serie desde `backend/` en `main` (SQLite y después MySQL) antes del push. La rama y la etiqueta `pre-monorepo` se conservan. El hash del merge se anota en el handoff al empezar lo siguiente | Usuario |

**Qué no cambió.** Código de `app/`, rutas, permisos, `error_code`, contrato de respuesta, migraciones y las colecciones (salvo la ruta
de la guía en sus descripciones y el mensaje esperado de la deuda 18).

# R-058 Análisis del frontend: decisiones y diseño de la F0 (2026-11-12)

Decisiones del usuario sobre el análisis del frontend, en `docs/frontend-analisis` (desde `main` en `5244a8b`). Con ellas se escriben
el diseño de la [F0 del frontend](../../03-plan-desarrollo/fases/frontend/f0-base.md) y el de la
[entrega de soporte del backend](../../03-plan-desarrollo/fases/backend/front-00-soporte-del-frontend.md) (B1 a B3).

| Tema | Decisión | Quién |
|---|---|---|
| Stack | El aprobado en el informe de análisis del 2026-11-11, con la alternativa descartada de cada elección en la tabla siguiente. La primera versión de esta sección, en el commit `f015002`, traía otro stack (Axios, sin date-fns ni Prettier, sin las versiones): se corrigió antes de aprobar el diseño | Usuario |
| Sesión | Token en `sessionStorage` y `BroadcastChannel` para cerrar la sesión en todas las pestañas; un temporizador cierra la sesión al vencer el token (12 horas, sin renovación). Las cookies de Sanctum (SPA con estado) quedan como decisión para producción, FASE 10 | Usuario |
| Tipos de la API | Escritos a mano; Scramble (generar OpenAPI desde Laravel) descartado por ahora | Usuario |
| Rutas | TanStack Router, con las URL en castellano (`/ingresar`, `/clientes`, `/agenda`…) | Usuario |
| Desarrollo | El proxy de Vite manda `/api` al backend: mismo origen, sin CORS en desarrollo | Usuario |
| Entrega del backend antes de la F0.2 | Sí, en `feature/front-00-me-sucursales`: B1, `me` trae las sucursales activas asignadas al usuario; B2, CORS acotado por variable de entorno; B3, `meta.pagination` en `snake_case` (`per_page`, `current_page`, `last_page`) como el resto de la API, con los tests y las colecciones de Postman que la usan. La revisión pedida de otros campos fuera de `snake_case` no encontró ninguno más | Usuario; la revisión, análisis |
| Pruebas de punta a punta | Playwright contra la base `syncvet_e2e`, sembrada con el `DemoSeeder` | Usuario |
| Orden | F0 → F1 → F2 → F3 y después la FASE 4 del backend (ver D2 más abajo) | Usuario |
| "Repositorio aparte" | La mención que quedó en `overview.md` y `technology-stack.md` después de R-057 se corrige en la F0 (entrega 0.1) | Usuario |
| P1 de la F0: pestaña nueva | Le pide la sesión a una pestaña abierta por el `BroadcastChannel`; todas comparten el token, así que un 401 cierra todas las pestañas | Usuario |
| P2 de la F0: aviso | Aviso 10 minutos antes del vencimiento | Usuario |
| P3 de la F0: contraseña propia | En la 0.2, en `/perfil` (`PATCH me/password`) | Usuario |
| P1 del backend: sucursales del administrador | `me.branches` trae solo las sucursales asignadas, también al administrador (lo mismo que acepta `me/current-branch`) | Usuario |
| P2 del backend: `SnakeCaseKeysTest` | Entra en la entrega de soporte del backend | Usuario |
| D2: frontend y backend | El frontend recorre F0 → F3 sobre la API de las fases 1 a 3 y después sigue la FASE 4 del backend (resuelve la decisión pendiente D2) | Usuario |
| Diseños | El de la F0 y el de la entrega de soporte del backend, aprobados el 2026-11-12. La rama `feature/front-00-me-sucursales` se crea desde `docs/frontend-analisis`, sin implementar todavía | Usuario |

**Stack aprobado y alternativas descartadas.**

| Tema | Elección | Descartado |
|---|---|---|
| Entorno | Node 26 LTS en todas las PC, con `frontend/.nvmrc` y `engines`; npm 11 | Node 24 (en mantenimiento); pnpm (una herramienta más que instalar en cada PC) |
| Build | Vite 8 con `@vitejs/plugin-react` | Next.js (servidor y SSR que una aplicación interna detrás de un login no necesita) |
| Lenguaje | TypeScript 6 estricto | TypeScript 7 (el compilador nuevo en Go, todavía sin el ecosistema de plugins asentado) |
| Rutas | TanStack Router, URL en castellano | React Router (menos tipado en los parámetros y la búsqueda) |
| Datos del servidor | TanStack Query, reintentos solo ante fallas de red o 5xx | SWR |
| Cliente HTTP | Propio sobre `fetch` | Axios o ky |
| Formularios | React Hook Form + Zod; el 422 con `setError` | Formik + Yup (más lento y menos mantenido); formularios a mano |
| Fechas | date-fns 4 + `@date-fns/tz` (`TZDate`, `America/Asuncion`) | Day.js y Luxon (otra forma de manejar zonas); solo `Intl` (no alcanza para calcular en una zona fija) |
| Interfaz | Tabler (`@tabler/core` y `@tabler/icons-react`) con componentes propios, sin el JS de Tabler y sin Tailwind | `tabler-react` (quedó en React 16) y el JS de Tabler |
| Tipos de la API | Escritos a mano | Scramble para generar OpenAPI y los tipos (descartado por ahora) |
| Tests | Vitest + Testing Library + MSW + jsdom; Playwright contra `syncvet_e2e` con el `DemoSeeder` | Jest (configuración aparte de Vite); happy-dom (menos fiel); Cypress (más pesado, sin varias pestañas) |
| Calidad | ESLint + Prettier | Biome (todavía sin las reglas de hooks de React que da ESLint) |

**Qué no cambió.** Código, rutas, permisos, `error_code`, contrato de respuesta y colecciones: solo documentación.

# R-059 Cierre de la entrega de soporte del frontend (B1 a B3) (2026-11-12)

Entrega del backend pedida por la F0 del frontend (R-058), en `feature/front-00-me-sucursales`: implementada, revisada, corregida y
validada por el usuario. El detalle, en el [resultado de la entrega](../../03-plan-desarrollo/fases/backend/front-00-soporte-del-frontend.md#resultado).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| B1 | `me` (también `data.user` del login y `PATCH me/current-branch`) trae `branches`: las sucursales activas asignadas al usuario, por `code`, exactamente las que acepta `me/current-branch`, también para el administrador. La regla vive solo en `Branch::selectableBy($user)` (H8) | Diseño (R-058); la regla en un lugar, revisión |
| B2 | CORS por `CORS_ALLOWED_ORIGINS`: solo orígenes exactos `http(s)://host[:puerto]`, normalizados en minúsculas y sin la barra final (`App\Support\CorsOrigins`); sin credenciales, `Retry-After` expuesto | Diseño (R-058); revisión, H1 y H6 |
| El `*` de CORS | Se descarta, con un aviso en el log; la revisión lo extendió a todo origen que no sea exacto (php-cors toma `https://*` o `*.dominio` como patrones). El aviso sale como máximo una vez por hora (`Cache::add`) | Usuario; revisión, H1 y H7 |
| B3 | `meta.pagination` en `snake_case` (`per_page`, `current_page`, `last_page`) en los 11 listados, armado por `ApiResponse::paginationMeta()`; `SnakeCaseKeysTest` protege la convención (arreglos, asignaciones por índice, `compact()` y `Arr::set`; sin marcar brazos de `match` ni el contexto del log) | Diseño (R-058); revisión, H2 y H5 |
| Desvíos | Los 1 a 5 de la implementación y los 6 y 7 de la revisión, aceptados (lista en el resultado de la entrega) | Usuario |
| Colección de sucursales | El 422 del alta en la primera validación se reproduce con la versión anterior a B3 de la colección (lee `lastPage`): causa probable, el Runner con la colección vieja importada. La colección pasa a ser repetible sin límite: usa siempre `SUC-PM` (alta o reactivación), rota su establecimiento y limpia las variables al empezar | Análisis; el arreglo, pedido por el usuario |
| R-058 | La columna de alternativas descartadas se ajustó al informe del análisis (SWR; Axios o ky; `tabler-react`, que quedó en React 16, y el JS de Tabler; Node 24, en mantenimiento, y pnpm), con "sin Tailwind" en la elección de la interfaz, a pedido del usuario | Usuario |
| Integración | Sin merge a `main` todavía: la rama se integra al cerrar la F0 del frontend. La F0.1 (esqueleto) se hace en `feature/front-0-1-esqueleto`, creada desde esta rama (en lugar de `feature/frontend-f0-01-esqueleto` desde `main`, como decía el diseño de la F0) | Usuario |
| Rama de la 0.2 | `feature/front-0-2-sesion` (en lugar de `feature/frontend-f0-02-sesion`), para seguir el patrón de la 0.1 | Usuario |

**Validación.** El usuario, el 2026-11-12 (Collection Runner, una iteración, después de las correcciones): sucursales 123 de 123, dos
corridas seguidas, y usuarios 136 de 136, 0 errores. Suite en serie sobre `bdaa78f`: 1445 casos; 14183 aserciones en SQLite (14
omitidos, solo de MySQL) y 14257 en MySQL. Newman, las 14 colecciones dos veces seguidas contra la base de desarrollo, sin fallas
(cifras en la [guía de Postman](../../05-pruebas/guia-postman.md#entrega-de-soporte-del-frontend-front-00)).

**Qué no cambió.** Rutas (78), permisos (135), migraciones (36), `error_code` y códigos HTTP. Cambian, a propósito, `me` (suma
`branches`), las claves de `meta.pagination` y las cabeceras de CORS (antes, sin `config/cors.php`, regía el valor por defecto de
Laravel: cualquier origen).

# R-060 Cierre de la entrega 0.1 del frontend (esqueleto) (2026-11-12)

Entrega 0.1 de la [F0 del frontend](../../03-plan-desarrollo/fases/frontend/f0-base.md), en `feature/front-0-1-esqueleto`:
implementada (`72e75bc`), revisada, corregida (`b5d3ecf`) y validada por el usuario. El detalle, en el
[resultado de la entrega](../../03-plan-desarrollo/fases/frontend/f0-base.md#entrega-01).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Desvíos 1, 2 y 5 | Aceptados: la variable del proxy es `API_PROXY_TARGET`, sin el prefijo `VITE_` (no llega a `dist/`); `@tabler/core` 1.7.4, con la 1.7.5 pendiente para la 0.2a; los tests corren con `TZ=UTC` y sin dividir el código de las rutas | Usuario |
| Desvío 3 | `npm audit`: el aviso de `minimatch` 10.2.6 (por `eslint` y `typescript-eslint`, solo en desarrollo) queda como [deuda 19](../../03-plan-desarrollo/deuda-tecnica.md), prioridad baja | Usuario |
| Desvío 4 (H13) | Se permiten `.each` y los bucles en los tests. La cifra de tests del frontend sale de la última corrida de `npm test` registrada en "Resultado" de la fase, con la línea de formato fijo ``Corrida de `npm test` del frontend (AAAA-MM-DD): N tests en M archivos, en verde.``; `/validar-docs` la toma de ahí (la de fecha más reciente) y avisa si la cantidad de archivos de test no coincide | Usuario; el formato, revisión |
| Correcciones de la revisión | H1 a H15: las fechas sin hora (`birth_date`) con `formatDateOnly`, sin pasar por `Date`; el 422 con claves de arreglo (`branch_ids.0`) a su campo; ESLint contra `window.fetch`, `XMLHttpRequest` y `dangerouslySetInnerHTML`; el voseo en los textos de una palabra; los guaraníes desde el texto, sin coma flotante; y los tests que no detectaban sus mutaciones | Revisión; autorizadas por el usuario |
| 204 | Un 204 (o un 2xx sin cuerpo) es un éxito sin datos: `data = null`. Un 2xx con un cuerpo que no es JSON sigue siendo `HTTP_ERROR` | Usuario |
| Valores vacíos o inválidos | Fechas y guaraníes muestran `''`, nunca "Gs. 0" ni un error que rompa el render | Usuario (pedido); el valor, revisión |
| Velocidad | Sección 8 de `frontend/CLAUDE.md`, a partir del análisis del tiempo de la 0.1 (66 minutos, casi la mitad en npm y en verificaciones repetidas): versiones fijas en el diseño y un solo `npm ci`; `environment: node` para los tests sin DOM; `vitest related` durante el trabajo y la suite, `typecheck` y `lint` una vez al final; Prettier solo sobre los archivos cambiados; validador, skills y documentación general en entregas o cierres aparte. Excluir `frontend/node_modules` y `backend/vendor` del antivirus de Windows (`setup.md`) | Usuario |
| División de la 0.2 | 0.2a, sesión (ingresar, `me`, salir, 401 y 403, vencimiento con su aviso, cerrar en todas las pestañas y pedir la sesión desde una pestaña nueva), en `feature/front-0-2a-sesion` desde `feature/front-0-1-esqueleto`; 0.2b, permisos (menú y rutas según los permisos, selector de sucursal, `/perfil` con la contraseña propia y Playwright contra `syncvet_e2e`), en `feature/front-0-2b-permisos` desde la 0.2a. Reemplaza la rama `feature/front-0-2-sesion` de R-059 | Usuario |
| Sugerencias para la 0.2 | Un botón para mostrar la contraseña en `/ingresar` (0.2a) y en `/perfil` (0.2b); evaluar "Correo electrónico" en lugar de "Email" (pregunta 4 de la F0, en el diseño de la 0.2a) | Usuario |
| Pendientes | Desvío 8 (`toApiDateTime` con el desplazamiento `-03:00`): PENDIENTE DE VERIFICACIÓN con la primera llamada real. Desvío 9 (`/sin-permiso` dentro del layout con sesión): sin decisión, se confirma en la 0.2b | Implementación |

**Validación.** El usuario, el 2026-11-12, en el navegador con `npm run dev`: `/ingresar` con Tabler (logo, título "Ingresá a tu
cuenta", campos obligatorios, botón con ícono) y una URL inventada con la página de no encontrado en voseo y "Volver al inicio".
`/sin-permiso`, el ancho de tablet, la consola y `npm run preview` no figuran en la validación (PENDIENTE DE VERIFICACIÓN). La última
corrida, desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 93 tests en 9 archivos del frontend, en
verde. `/validar-docs`, sin hallazgos (sus tests, 55 de 55).

**Qué no cambió.** El backend: ni código ni tests (rutas, permisos, migraciones, `error_code` y códigos HTTP iguales).

# R-061 Cierre de la entrega 0.2a del frontend (sesión) (2026-11-12)

Entrega 0.2a de la [F0 del frontend](../../03-plan-desarrollo/fases/frontend/f0-base.md), en `feature/front-0-2a-sesion`:
implementada (`93e347c`), revisada, corregida (`ece1faf`) y validada en el navegador. El detalle, en el
[resultado de la entrega](../../03-plan-desarrollo/fases/frontend/f0-base.md#entrega-02a).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Cierre por token (M3 de la revisión) | El vencimiento y el 401 cierran solo las pestañas con ese mismo token: el mensaje `logout` del canal lleva `token_id` y `all`, y las pestañas con otro token ignoran uno con `all: false`. "Salir" a propósito sigue cerrando todas las pestañas y revocando sus tokens | Usuario |
| Desvío 2 | `USER_INACTIVE` y `CLINIC_NOT_AVAILABLE` con sesión cierran como el 401: solo las pestañas con ese token, con el mensaje de la API | Usuario |
| Desvío 1 | El aviso previo dice "Tu sesión vence en N minutos. Guardá los cambios pendientes.", con los minutos que quedan, actualizados (el texto del diseño usaba "estés", una forma de tú) | Usuario |
| Desvío 6 | `/ingresar` con una sesión abierta va a `/`; una falla de red o un 5xx al cargar `me` muestra "No se pudo cargar tu sesión" con "Reintentar" y no cierra la sesión | Usuario |
| Correcciones de la revisión | M1, el 401 cierra solo si el pedido salió con el token actual; M2, "Salir" cierra primero y manda el `POST logout` sin esperarlo, con un tiempo límite de 5 s; M4, el vencimiento también se revisa con `focus`, `pageshow` y un intervalo de 15 s; M5, un test por cada mutación que sobrevivía; B1, el canal valida el formato completo de cada mensaje; B2, los minutos del aviso se actualizan; B3, un 429 sin `Retry-After` deshabilita el botón 60 s; B4, `safeRedirect` revisa la ruta normalizada; B5, se descarta la sesión compartida de un token ya cerrado | Usuario (pedido); el valor de 60 s, la corrección |
| Diseño | Los flujos 3 a 6 de la 0.2a, la tabla [Sesión (0.2)](../../03-plan-desarrollo/fases/frontend/f0-base.md#sesión-02) y la tabla de errores, actualizados con M2, M3 y M4 (desvío 14) | Cierre |
| Aviso al perder la sesión | "Tu sesión terminó. Ingresá de nuevo." en lugar de "Tu sesión venció. Ingresá de nuevo.", por vencimiento, un 401 o el token revocado al inactivar al usuario: a un usuario inactivado no se le venció la sesión. El texto está en `MESSAGES.sessionExpired` (`src/lib/api/errors.ts`); dos tests lo fijan literal (el de la sesión vencida guardada en `LoginPage.test.tsx` y el 401 en `SessionLayout.test.tsx`) | Usuario |
| Cifras de resultados cerrados | `/validar-docs` no compara las cifras de la sección "Resultado" de una entrega ya cerrada (el bloque de su título dice `COMPLETADO` o `COMPLETADA`): eran las de ese momento. `closedResultLines()` en `check-docs.php`, caso 9 de `check-docs-test.php` (59 de 59); la corrida registrada se sigue leyendo de ahí | Usuario |
| `backend/CLAUDE.md` §1 | El frontend ya no figura "sin empezar": una línea con su estado y el enlace a `frontend/CLAUDE.md` | Usuario |
| Pruebas en el navegador | Además de las 5 del usuario, las 8 restantes de "Qué revisar en el navegador" las hizo Claude Code, a pedido del usuario, con Edge sin ventana manejado por el protocolo de DevTools (un script de Node, sin dependencias nuevas) contra el backend real (`syncvet.test`) y `npm run dev` | Usuario (pedido) |
| Pendientes | Desvío 8 de la 0.1 (`toApiDateTime` con `-03:00`): sigue PENDIENTE DE VERIFICACIÓN y pasa a la F3, porque la 0.2a no manda fechas a la API | Implementación |

**Validación.** El usuario, el 2026-11-12, en el navegador con el backend real: ingreso con `admin-a`, `recepcion-a` y `vet-a`,
contraseña incorrecta, la cuenta regresiva del 429, una segunda pestaña que entra sola y salir en una pestaña que cierra la otra. El
resto de "Qué revisar en el navegador", a pedido del usuario, lo corrió Claude Code en Edge sin ventana, por el protocolo de DevTools:
mostrar la contraseña, el vencimiento (`expires_at` pasado, borrado y una sesión que vence sola), el aviso previo y sus minutos,
"Salir" y un usuario inactivado desde otra sesión (el siguiente pedido recibe un 401, porque inactivar revoca los tokens); la
consola, sin errores de la aplicación. La corrida del cierre, desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni
avisos; `npm test`, 195 tests en 15 archivos del frontend, en verde.

**Qué no cambió.** El backend: ni código ni tests (rutas, permisos, migraciones, `error_code` y códigos HTTP iguales).

# R-062 Cierre de la entrega 0.2b del frontend (permisos) (2026-11-12)

Entrega 0.2b de la [F0 del frontend](../../03-plan-desarrollo/fases/frontend/f0-base.md), en `feature/front-0-2b-permisos`:
implementada (`68c68b6`), validada en el navegador (`023a90e`), revisada, corregida (`e77f64a`, `91dca14` y `b1bc017`) y validada
de nuevo. El detalle, en el [resultado de la entrega](../../03-plan-desarrollo/fases/frontend/f0-base.md#entrega-02b).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| `/configuracion` provisoria (desvío 1) | Una página "En construcción" con `system-settings.update`: el administrador la ve en el menú y en el inicio; recepción y veterinario van a `/sin-permiso`. La F2 la reemplaza por la pantalla real | Usuario |
| Preparación de Playwright (desvío 2) | Se niega a correr si la base no es exactamente `syncvet_e2e`; controla además la base que resuelve Laravel y que el backend de `:8010` use la base recién preparada (contraseña al azar por corrida). La contraseña de la corrida queda en `frontend/e2e/.e2e-credentials`, ignorado por Git, para entrar a mano | Usuario |
| Segunda sucursal y servidores de Playwright (desvíos 3 y 4) | La preparación crea por la API "Sucursal Norte" para `admin-a`; Playwright levanta el backend (`:8010`, `DB_DATABASE=syncvet_e2e`) y Vite (`:5174`), o usa un backend ya levantado en `:8010` | Usuario (aceptados en el cierre) |
| Edge instalado (desvío 5) | Playwright usa `channel: 'msedge'` y no descarga navegadores (`E2E_BROWSER_CHANNEL` para otro) | Usuario |
| Repetir la contraseña nueva (desvío 7) | `/perfil` pide "Repetí la contraseña nueva", controlada solo en el navegador: la API no la recibe | Usuario |
| Playwright completo (desvío 10) | Se suman "credenciales incorrectas" y el vencimiento en las dos pestañas con el mismo token: 11 casos, y el criterio de cierre de la F0 queda como estaba | Usuario |
| `/sin-permiso` (desvío 9 de la 0.1, 13 de la 0.2b) | Dentro del layout con sesión, con la barra y el menú | Usuario |
| Correcciones de la revisión | M1, las contraseñas de `/ingresar` y `/perfil` fuera de la caché de mutaciones (`gcTime: 0` y `reset()`); M2, `me` vuelto a pedir al volver a la pestaña; B1, el mensaje de error fuera del grupo con borde de `PasswordField`; B2, test de `replace: true` (el riesgo no era real); B3, B4 y B7, tests de las mutaciones que sobrevivían; B5, `me` pedido al servidor después del `PATCH me/current-branch`; B6, la convención de claves de las consultas (`src/lib/api/query-keys.ts`): el cambio de sucursal invalida `['me']` y solo las consultas de la sucursal. B8: PENDIENTE DE VERIFICACIÓN (el pedido del cierre nombra B1 a B8; en el repositorio constan B1 a B7) | Usuario (pedido) |
| Espera de la pestaña nueva | 1000 ms en lugar de 300 (`SESSION_REQUEST_TIMEOUT_MS`, P1): en la PC de una recepción el equipo puede ser lento. Playwright ya no supone que la sesión llegó: `openSecondTab` la espera con su tiempo límite | Usuario |
| Pruebas en el navegador | Todas las hizo Claude Code, a pedido del usuario, con Edge sin ventana contra `syncvet.test` y `npm run dev`: 14 pruebas sobre la implementación y 11 sobre la corrección, con la demo restaurada | Usuario (pedido) |
| Limpieza de la raíz (PC nueva) | Se borraron los restos del backend de antes del monorepo, sin versionar (unos 81 MB; 551 de los 552 archivos, sin `vendor/`, ya estaban en el historial de Git), y el `.env` viejo se movió fuera del repositorio, a `C:\laragon\www\syncvet-raiz-vieja.env`. El vhost `syncvet.test` de esa PC se apuntó a `backend/public` (paso 5 de la migración al monorepo) | Usuario |

**Validación.** Claude Code, a pedido del usuario, en Edge sin ventana contra el backend real: los menús de los tres roles, la ruta
sin permiso, el 403 de la API, el cambio de sucursal (también en otra pestaña, al recargar y al volver a la pestaña), el usuario sin
sucursal actual, `/perfil` (la repetición, los errores fuera del borde, el cambio y la vuelta a `DEMO_PASSWORD`), el ancho de tablet
y la consola. Playwright, 11 de 11 en dos corridas seguidas. La corrida del cierre, desde `frontend/`: `typecheck`, `lint` y `build`
sin errores ni avisos; `npm test`, 269 tests en 21 archivos del frontend, en verde.

**Qué no cambió.** El backend: ni código ni tests (rutas, permisos, migraciones, `error_code` y códigos HTTP iguales); `composer
test:fast`, 1445 casos (1431 en verde y 14 omitidos, solo de MySQL).

# R-063 Cierre de la F0 del frontend y merge a `main` (2026-11-12)

Cierre de la F0 del frontend (base y login), junto con la entrega de soporte del backend (front-00), con las cuatro entregas cerradas
(R-059 a R-062). El detalle, en el [cierre de la F0](../../03-plan-desarrollo/fases/frontend/f0-base.md#cierre-de-la-f0).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Entregas | front-00 (R-059), 0.1 (R-060), 0.2a (R-061) y 0.2b (R-062): COMPLETADO, cada una con su revisión, sus correcciones y su validación; las cinco ramas de la cadena, en GitHub | Cierre |
| F0 | COMPLETADO: el criterio de cierre se cumple, punto por punto en el cierre de la F0 (ingresar, cambiar de sucursal, salir en todas las pestañas y el vencimiento, de punta a punta con Playwright contra `syncvet_e2e`) | Usuario |
| Regresión del backend | Desde `backend/`, en serie, sobre `2cd113a` (código de `b1bc017`): SQLite, 1445 casos (14 omitidos, solo de MySQL), 14183 aserciones; MySQL, 1445 casos sin omitidos, 14257 aserciones. 78 rutas, 135 permisos y 36 migraciones, sin cambios desde front-00. `composer audit`, sin avisos | Cierre |
| Regresión del frontend | Desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 269 tests en 21 archivos; `npm run test:e2e`, 11 de 11. `npm audit`: solo el aviso de `minimatch` de la deuda 19 (npm lo cuenta 10 veces, una por paquete que lo trae) | Cierre |
| Postman | Newman, las 14 colecciones de `docs/05-pruebas/postman` (R-057), servidor en el puerto 8001: 483 pedidos y 1072 aserciones, sin fallas | Cierre |
| `npm run preview` | Lo que quedaba PENDIENTE DE VERIFICACIÓN de la 0.1, verificado por HTTP: sirve `dist/`, las rutas de la aplicación y el proxy de `/api` | Cierre |
| Deudas | Nuevas en la F0: 19 y 20 (frontend, prioridad baja). Las del backend, sin cambios. Abiertas: 2, 3, 5, 6, 15 y 17 (backend); 19 y 20 (frontend). La 19 aclara que npm cuenta el aviso por paquete | Cierre |
| Pendientes | El desvío 8 de la 0.1 (`toApiDateTime` con `-03:00`) pasa a la F3; B8 de la 0.2b sigue PENDIENTE DE VERIFICACIÓN | Cierre |
| Merge a `main` | Autorizado por el usuario el 2026-11-12: merge `--no-ff` desde `feature/front-0-2b-permisos`, que contiene `docs/frontend-analisis`, `feature/front-00-me-sucursales`, `feature/front-0-1-esqueleto` y `feature/front-0-2a-sesion`. Antes del push de `main`, en `main`: la suite del backend en serie (SQLite y MySQL) y `npm test` del frontend. Las ramas se conservan. El hash del merge no puede ir en el commit que lo precede: se informa al usuario y se anota en el handoff al empezar lo siguiente | Usuario |
| Siguiente | El análisis y el diseño de la F1 del frontend (clientes y pacientes), sobre la API de la FASE 1 | Usuario |

**Qué no cambió.** Código, tests, rutas, permisos, `error_code`, contrato de respuesta y colecciones: el cierre solo documenta.

# R-064 Aprobación del diseño de la F1 del frontend (clientes y pacientes) (2026-11-12)

El usuario aprueba el diseño de la F1 del frontend,
[`f1-clientes-y-pacientes.md`](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md), en `docs/front-f1-diseno`
(desde `main` en `668eb48`), con las 13 [preguntas del diseño](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#preguntas-del-diseño)
respondidas con la recomendación.

| Tema | Decisión | Quién |
|---|---|---|
| Entregas | front-01 (backend: el dueño en `GET patients`), 1.1, 1.2, 1.3a, 1.3b, 1.4 y 1.5, en ramas encadenadas desde `docs/front-f1-diseno`; todo se integra a `main` al cerrar la F1 | Usuario |
| Dueño en el listado de pacientes (pregunta 1) | Entrega de soporte del backend front-01, aditiva: `client: { id, display_name }` en `GET patients`, sin N+1 y sin cambiar otras respuestas | Usuario |
| Interfaz (preguntas 2 a 4, 7 a 9, 11 y 13) | Listados en tabla, 20 por página sin selector; los pacientes del cliente desde `GET clients/{id}`, con los fallecidos detrás de un botón; diálogo simple antes de borrar; alta del paciente en una página propia; "Especies" con menú propio; foto con vista previa y botón; sin aviso de cambios sin guardar en la F1 | Usuario |
| Validación (preguntas 5 y 6) | El dígito verificador del RUC también en el navegador, con los casos de `RucCheckDigitTest`; el email se pasa a minúsculas al mandar | Usuario |
| Pacientes (preguntas 10 y 12) | "Anular el fallecimiento" con confirmación; pacientes en dos entregas (1.3a y 1.3b, con la verificación del desvío 8 de la 0.1 en la 1.3b) | Usuario |
| Rama de front-01 | `feature/front-01-dueno-pacientes` (el nombre que dio el usuario; el diseño decía `feature/front-01-duenio-en-pacientes`) | Usuario |

**Qué no cambió.** Código, tests, rutas, permisos y colecciones: la aprobación solo documenta.

# R-065 Cierre de la entrega front-01 del backend (el dueño en el listado de pacientes) (2026-11-12)

Entrega de soporte del backend pedida por la F1 del frontend (R-064), en `feature/front-01-dueno-pacientes` (desde
`docs/front-f1-diseno`), sin integrar a `main`: se integra al cerrar la F1. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#entrega-front-01).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | `GET patients` trae `client` con `id` y `display_name`, con `whenLoaded` en `PatientResource` y el `with('client')` que el listado ya tenía; sin rutas, permisos ni migraciones (78, 135, 36); sin cambios en `error_code` ni códigos HTTP | Diseño (R-064) |
| Desvío 1 | `client` va última en `PatientResource`, para que el detalle del paciente (`ShowPatientResource`, que la reemplaza) conserve el orden de sus claves | Usuario: aceptado |
| Desvío 2 | El listado no trae `deleted` del dueño (solo `id` y `display_name`); un dueño eliminado se muestra igual (`withTrashed`) | Usuario: aceptado |
| Desvíos informativos | 3 a 7 de la entrega: el detalle ya traía el dueño; el caso de N+1 con 1 y 6 pacientes y un pedido previo; `client` en `null` por defensa; dos aserciones en pedidos existentes de Postman; el intento de cerrar todos los `php.exe` | Registrados |
| Regla de procesos | Claude Code solo detiene los procesos que él mismo levantó, por su PID o su tarea; nunca por nombre (`php.exe`, `nginx`, `node`) ni los de Laragon o del usuario ([`CLAUDE.md`](../../../CLAUDE.md) de la raíz, §2, Procesos) | Usuario |
| Revisión | Sin hallazgos que corregir, en una sesión aparte sobre el WIP `3e3e386`; una observación sin cambio (`countQueries` repetido en tres tests) | Revisión |
| Validación | Desde `backend/`, en serie sobre `3e3e386`: SQLite, 1451 casos y 14225 aserciones (14 omitidos, solo de MySQL); MySQL, 1451 casos y 14299 aserciones, sin omitidos. Newman, las 14 colecciones desde la raíz (puerto 8001): 483 pedidos y 1074 aserciones, sin fallas (pacientes, 61 → 63). La suite del frontend no cambió (`npm test`, 269 tests en 21 archivos del frontend) | Usuario |
| Siguiente | La entrega 1.1 del frontend (clientes: listado y detalle), en `feature/front-1-1-clientes`, creada desde `feature/front-01-dueno-pacientes` | Usuario |

# R-066 Cierre de la entrega 1.1 del frontend (clientes: listado y detalle) (2026-11-13)

Primera entrega de pantallas de la F1 del frontend (R-064), en `feature/front-1-1-clientes` (desde `feature/front-01-dueno-pacientes`
en `6ee193a`), sin integrar a `main`: se integra al cerrar la F1. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#entrega-11).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | `/clientes` (búsqueda con espera de 300 ms, tipo y página en la URL; 20 por página) y `/clientes/$id` (datos y pacientes, con los fallecidos detrás de "Mostrar los fallecidos (N)"); permisos `clients.index` y `clients.show` en las rutas y en el menú ("Clientes", los tres roles); `SearchField` y `SelectField`; sin paquetes nuevos y sin cambios en la API | Diseño (R-064) |
| Desvíos de la implementación | 1 a 11 (la rama, `SelectField` adelantado, la página de no encontrado dentro del layout, `validateSearch` con las tres claves siempre, `NotFoundError` en ESLint, `queryClient.query` en lugar de `ensureQueryData`, el error del listado en la tarjeta, detalles de presentación, 3 fallas por tiempo de la primera corrida, el fallecido simulado en la validación y la deuda 21 del backend) | Usuario: aceptados en la revisión |
| Revisión | En una sesión que la revisó como quien no la escribió, sobre el WIP `3ea03b9`: 8 hallazgos (1 alto: el campo de búsqueda perdía el espacio final; 3 medios; 4 bajos o informativos) | Revisión |
| Correcciones | Hallazgos 1 a 7 corregidos en `104aee1`: el campo toma lo de la URL solo si el cambio vino de afuera; "Esta página no existe." con el enlace a la página 1 (en `DataTable`); la espera probada con relojes falsos; límites de 3 s (`asyncUtilTimeout`) y 15 s (`testTimeout` de jsdom); "Buscando…" en una región `role="status"` fija; `parseSearch` y `stringifySearch` del router (la búsqueda escrita es siempre texto); `useDebouncedValue` solo con primitivos. El hallazgo 8 (un rol con `clients.show` sin `clients.index`), informativo | Usuario |
| Convención de listados | El modelo para los próximos listados, en [`frontend/CLAUDE.md`](../../../frontend/CLAUDE.md) §5 ("Listados" y "Tests"): búsqueda y página en la URL, lo escrito siempre como texto (`TEXT_SEARCH_PARAMS`), el campo local con espera y cuándo toma la URL, la página fuera de rango y cómo probar la espera | Usuario |
| Desvíos de las correcciones | 12 (H1 suma "lo que el campo mandó y todavía no llegó" a la regla del usuario), 13 (una corrida final repetida por 2 errores de `lint`), 14 (la página fuera de rango en `DataTable`, genérico), 15 (la validación usó un `npm run dev` que ya estaba en `:5173`; no lo cerró Claude Code) y 16 (dos pedidos iguales al entrar: probablemente el doble efecto del `StrictMode` de React en desarrollo; verificarlo con `npm run build` y `npm run preview` queda opcional, PENDIENTE DE VERIFICACIÓN) | Usuario: aceptados |
| Validación | El usuario la validó en el navegador el 2026-11-13: "el listado y el detalle se ven bien y es rápido". Desde `frontend/`, sobre el código de `104aee1`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 357 tests en 25 archivos; `npm run test:e2e`, 13 de 13. En Edge sin ventana contra `syncvet.test`: 31 de 31 de la implementación y 4 de 4 de las correcciones. La suite del backend no cambió (la entrega no toca su código) | Usuario |
| `modulos.md` | Los cierres del frontend suman su avance a las observaciones del módulo del backend que usan (en la 1.1, la fila Clientes), sin cambiar su estado ni agregar filas | Usuario |
| Siguiente | La entrega 1.2 del frontend (clientes: alta, edición y baja), APROBADA (R-064) | Diseño (R-064) |

**Qué no cambió.** Código, tests, rutas, permisos, `error_code`, contrato de respuesta y colecciones: el cierre solo documenta.

# R-067 Cierre de la entrega 1.2 del frontend (clientes: alta, edición y baja) (2026-11-13)

Segunda entrega de pantallas de la F1 del frontend (R-064), en `feature/front-1-2-clientes-abm` (desde `feature/front-1-1-clientes`
en `78e9657`), sin integrar a `main`: se integra al cerrar la F1. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#entrega-12).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | `/clientes/nuevo` (`clients.store`) y `/clientes/$id/editar` (`clients.update`) con el mismo formulario de persona y empresa (los campos del otro tipo, en `null`), las reglas de forma de la API en el navegador con sus mensajes (el dígito verificador del RUC con los casos de `RucCheckDigitTest`) y los 422 en cada campo; la baja desde el detalle (`clients.destroy`, solo el administrador) con `ConfirmDialog` y `CLIENT_IN_USE` en el diálogo; "Nuevo cliente" y "Editar" según el permiso; la baja vuelve a la página del listado de donde se vino. Componentes nuevos: `ConfirmDialog`, `RadioGroup`, `CheckboxField` y `TextareaField`; sin paquetes nuevos | Diseño (R-064) |
| Desvíos de la implementación | 1 a 14: aceptados el 1 (la búsqueda del listado en el estado del historial), el 2 (la baja vacía los listados antes de volver), el 3 (guardar y "Cancelar" reemplazan el formulario en el historial), el 5 (detalles de los componentes) y el 6 (el email lo decide la API); el 4 (los RUC reales en `ruc.test.ts`) queda con las excepciones justificadas de los RUC públicos de empresas; los demás, informativos. En las correcciones, el 17 (la única diferencia del email con la API, `"juan perez"@…`), aceptado | Usuario |
| Revisión | En una sesión que la revisó como quien no la escribió, sobre el WIP `657100e`: 7 hallazgos (1 alto: el doble clic en Guardar mandaba dos altas y la segunda daba 500; 4 medios: el foco del diálogo, el email más estricto que la API, 14 mutaciones que pasaban los tests y `FictitiousDocumentsTest` sin revisar el frontend; 1 bajo y 1 informativo) | Revisión |
| RUC reales | Los 7 RUC de `ruc.test.ts` (los de `RucCheckDigitTest`, de la DNIT) son de empresas, de la serie de las personas jurídicas: quedan, en `ALLOWED`. Los ejemplos de 7 dígitos de `FictitiousDocumentsTest` (el rango de las cédulas reales) pasan a números con un cero adelante, que no pueden ser una cédula | Usuario |
| Correcciones | Hallazgos 1 a 6: Guardar se deshabilita también con `isSubmitting` y una `ref` deja pasar un solo envío; en el backend (commit aparte, `594ad20`), `ClientService` responde 422 en `document_number` o `email` cuando choca con un índice único de `clients`, nunca 500 (identificado por el nombre del índice); `ConfirmDialog` con las teclas en `document`, el foco de vuelta a "Cancelar", `aria-disabled` y el fondo sin desplazamiento; el email del navegador, solo la forma mínima; tests de los límites, la caché de la baja, la invalidación de pacientes y la vuelta de la edición; `FictitiousDocumentsTest` revisa también `frontend/src` y `frontend/e2e`; en `bb8cab5` | Usuario |
| Procesos | `frontend/CLAUDE.md` §8: un Vite de una prueba se detiene por el PID de `vite.js` (detener la tarea dejaba vivo a Vite), comprobando después que el puerto quedó libre | Usuario |
| Deuda 22 | `PatientService` guarda el microchip solo con la regla `unique`: dos altas o ediciones simultáneas con el mismo microchip darían 500 (el mismo hueco que tenía `ClientService`). Prioridad media; se corrige al empezar la 1.3a, como commit aparte del backend | Usuario |
| Validación | El usuario revisó los formularios en el navegador ("están bien") y dio la entrega por validada con las correcciones. Edge sin ventana contra `syncvet.test`: 19 de 19 de la implementación y 8 de 8 de las correcciones (el doble clic, el teclado en el diálogo y un email con tilde); la demo sin clientes nuevos. Desde `frontend/`, sobre el código de `bb8cab5`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 502 tests en 29 archivos; `npm run test:e2e`, 17 de 17. Desde `backend/`: `composer test:fast` y la suite en serie, 1458 casos (14262 aserciones en SQLite, 15 omitidos; 14342 en MySQL, sin omitidos); Newman de la colección de clientes, 24 pedidos y 48 aserciones, sin fallas | Usuario |
| Siguiente | La entrega 1.3a del frontend (pacientes: listado, detalle, alta y edición), APROBADA (R-064) | Diseño (R-064) |

**Qué no cambió.** Rutas, permisos, `error_code`, códigos HTTP de lo que ya respondía la API y colecciones. Lo único nuevo del backend
es el 422 donde antes había un 500 (el choque con un índice único de `clients`).

# R-068 Cierre de la entrega 1.3a del frontend (pacientes: listado, detalle, alta y edición) (2026-11-14)

Tercera entrega de pantallas de la F1 del frontend (R-064), en `feature/front-1-3a-pacientes` (desde `feature/front-1-2-clientes-abm`
en `d36c37b`), sin integrar a `main`: se integra al cerrar la F1. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#entrega-13a).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | `/pacientes` (tabla de 20 con el dueño; búsqueda, especie y "Incluir los fallecidos" en la URL), `/pacientes/$id`, el alta desde el cliente (`/clientes/$id/pacientes/nuevo`) y la edición, con la raza dependiente de la especie (la inactivada del paciente, en las opciones), `birth_date` sin pasar por `Date`, el peso con coma decimal y las reglas de forma de la API con sus mensajes; el nombre del paciente como enlace y "Nuevo paciente" en el detalle del cliente; el menú "Pacientes" y los permisos `patients.index`, `.show`, `.store` y `.update`. Componentes: `DateField` nuevo y `SelectField` con error y `ref`; sin paquetes nuevos | Diseño (R-064) |
| Deuda 22 | Resuelta en un commit aparte del backend (`f12f3c7`): `PatientService` traduce la violación del índice único del microchip (por su nombre en MySQL o `patients.microchip` en SQLite) al 422 de la regla `unique` en `microchip`, en el alta y en la edición; `PatientUniqueIndexTest` y `PatientConcurrencyTest` (dos altas simultáneas, MySQL) | Usuario (R-067) |
| Desvíos | Los 11, aceptados: entre ellos, `useSearchText` común a los listados (sin cambiar el de clientes), "(inactivada)" por el test de voseo y el alta que va al detalle del paciente. El 4 (al editar un paciente con una especie inactiva, solo su raza actual) queda así para el MVP; el 6 (el sexo `unknown`), unificado en "Sin determinar", el texto de la API | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `c68511c`: backend sin hallazgos; en el frontend, 6 hallazgos (el sexo con dos textos, la fecha a medio escribir que se mandaba en `null` y borraba la guardada, tres mutaciones que pasaban los tests, la especie de la URL fuera del selector y el `maxLength` que contaba unidades UTF-16) | Revisión |
| Correcciones | En `150216e`: "Sin determinar"; la fecha a medio escribir (`validity.badInput`) da el error de formato y no se manda; "Especie no disponible" para una especie de la URL fuera del selector (no se descarta: una inactivada todavía tiene pacientes); tests del detalle con lo recién guardado, "Incluir los fallecidos" a la página 1 y el 409 de la edición (M1 a M3, ahora detectadas); sin `maxLength` nativo en los textos con letras del paciente y, en el cierre, en todos los de clientes (nombre, apellido, razón social, dirección, ciudad y notas): el largo se cuenta en caracteres, como la API | Usuario |
| Presupuesto por entrega | Nueva sección del `CLAUDE.md` de la raíz, que manda sobre las demás reglas de velocidad: durante la entrega, solo los tests de lo tocado; al final, una corrida completa del lado que cambió; MySQL solo con los tests afectados si hay migraciones, concurrencia o búsqueda de texto; Playwright y Newman solo de lo tocado; hasta 10 controles en el navegador; 3 o 4 mutaciones; la revisión no repite suites. Al cerrar la fase: la suite completa en MySQL y en serie, Playwright completo y Newman de todas las colecciones. `backend/CLAUDE.md` y `frontend/CLAUDE.md`, ajustados | Usuario |
| Validación | El usuario probó la 1.3a en el navegador y salió todo bien (2026-11-14). Edge sin ventana contra `syncvet.test`: 26 de 26 de la implementación y 5 de 5 de las correcciones, con la demo como estaba. Desde `frontend/`: `npm test`, 642 tests en 35 archivos sobre `150216e`, más 3 del último cambio verificados con `vitest related`; `typecheck`, `lint` y `build` sin errores ni avisos; Playwright, 18 de 18 en la implementación y el de pacientes, 1 de 1, después de las correcciones. Desde `backend/`, sobre `f12f3c7`: 1463 casos (14274 aserciones en SQLite en serie, 16 omitidos; 14360 en MySQL); Newman de la colección de pacientes, 63 de 63 | Usuario |
| Siguiente | La entrega 1.3b del frontend (fallecimiento, cambio de dueño y baja del paciente), APROBADA (R-064), en `feature/front-1-3b-pacientes`, creada desde `feature/front-1-3a-pacientes` (el diseño decía `feature/front-1-3b-pacientes-estado`) | Usuario |

**Qué no cambió.** Rutas, permisos, `error_code`, códigos HTTP de lo que ya respondía la API y colecciones. Lo único nuevo del backend
es el 422 donde antes había un 500 (el choque con el índice único del microchip).

# R-069 Cierre de la entrega 1.3b del frontend (pacientes: fallecimiento, cambio de dueño y baja) (2026-11-14)

Cuarta entrega de pantallas de la F1 del frontend (R-064), en `feature/front-1-3b-pacientes` (desde `feature/front-1-3a-pacientes`
en `5f3aac7`), sin integrar a `main`: se integra al cerrar la F1. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#entrega-13b).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | En `/pacientes/$id`: registrar, corregir y anular el fallecimiento (`patients.update`; la fecha y la hora de pared de Asunción, mandadas con `toApiDateTime` y `-03:00`; no futuras ni anteriores al nacimiento), cambiar de dueño (`patients.change-owner`: administrador y recepcionista; buscador de clientes sin el dueño actual) y la baja (`patients.destroy`: solo el administrador; con `PATIENT_IN_USE`, "Registrar el fallecimiento"); la edición de un fallecido deja editables solo las notas. `ConfirmDialog` con `tone`, `size` y `confirmDisabled`; `TimeField` nuevo; sin paquetes nuevos y sin cambios en la API | Diseño (R-064) |
| Desvío 8 de la 0.1 | VERIFICADO: `deceased_at` con `-03:00` se guarda y se lee igual contra la API real (Playwright y Edge), anotado en el [resultado de la 0.1](../../03-plan-desarrollo/fases/frontend/f0-base.md#entrega-01) | Implementación |
| Desvíos | Los 11, aceptados: entre ellos, registrar, corregir y anular mandan solo `deceased_at`; la fecha de fallecimiento se corrige desde el detalle (no en el formulario de edición); `ConfirmDialog` con campos para el fallecimiento y el cambio de dueño; y la hora en 12 horas del selector nativo de Edge, que depende del idioma de Windows | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `9bf37ba`: 5 hallazgos (el foco tras un 422 de la API en "Cancelar" en lugar del campo; el detalle del dueño con la lista vieja un momento después de la baja o del cambio de dueño; dos tests que faltaban, `confirmDisabled` y la respuesta fuera de orden del buscador; `keepPreviousData` repetido y Zod validando los campos bloqueados de un fallecido); 3 de 4 mutaciones detectadas | Revisión |
| Correcciones | H1: `ConfirmDialog`, de forma general, lleva el foco al primer campo con error al terminar la espera (si no hay, a "Cancelar"), probado también en la baja de clientes de la 1.2. H2: la solución de la baja de clientes de la 1.2 (`resetQueries`) para el detalle del dueño en la baja y los de los dos clientes en el cambio de dueño: nunca muestran al paciente borrado o que se fue. H3 y H4: los dos tests. H5: `keepPreviousData` en un solo lugar y, en la edición de un fallecido, Zod valida solo las notas | Usuario |
| Proceso: `cat` sin entrada | Una tarea en segundo plano quedó colgada casi 2 horas por un `cat > /tmp/x` que esperaba el teclado (ya había pasado en la 1.1); se detuvo por su PID y el commit ya tenía lo que quería hacer. Un hook PreToolUse versionado (`.claude/settings.json`, `.claude/hooks/block-cat-stdin.js`) rechaza todo `cat >` o `cat >>` sin heredoc, redirección o tubería, y la regla queda al principio del `CLAUDE.md` de la raíz; commit aparte `c8ca294` | Usuario |
| Validación | El usuario dio la entrega por validada con las correcciones. Edge sin ventana contra `syncvet.test`: 10 de 10 de la implementación y 5 de 5 de las correcciones, con la demo como estaba. Desde `frontend/`: `npm test`, 694 tests en 37 archivos; `typecheck`, `lint` y `build` sin errores ni avisos; Playwright, el caso nuevo en la implementación y 5 de 5 de `pacientes-estado` y `clientes-abm` después de las correcciones | Usuario |
| Siguiente | La entrega 1.4 del frontend (foto del paciente), APROBADA (R-064), en `feature/front-1-4-foto`, creada desde `feature/front-1-3b-pacientes` | Usuario |

**Qué no cambió.** Backend, rutas, permisos de la API, `error_code`, códigos HTTP y colecciones.

# R-070 Cierre de la entrega 1.4 del frontend (foto del paciente) (2026-11-14)

Quinta entrega de pantallas de la F1 del frontend (R-064), en `feature/front-1-4-foto` (desde `feature/front-1-3b-pacientes` en
`e20186e`), sin integrar a `main`: se integra al cerrar la F1. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#entrega-14).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | `api.getBlob` (descargas con el token; los errores JSON como `ApiError`, el 401 cierra la sesión). En `/pacientes/$id`, la tarjeta "Foto": verla como `Blob` con `BlobImage` (la URL de objeto se libera al cambiar o desmontar), subirla o reemplazarla con `FileField`, vista previa y "Subir la foto", y quitarla con `ConfirmDialog`; el navegador frena lo que no es JPG, PNG o WEBP y lo de más de 5 MB, con los mensajes de la API; 413 y 422 en el campo; la foto no se descarga en los listados. Los tres permisos de foto (los tres roles). Sin paquetes nuevos y sin cambios en la API | Diseño (R-064) |
| Desvíos | Los 11, aceptados: entre ellos, `POST` y `DELETE` de la foto responden 200 (no 201); `BlobImage` en lugar de un hook con estado; al quitar, `Query.reset()`; el "rol sin permiso" del navegador, simulado (ningún rol real lo pierde); los tests de la subida con el `FormData` y el `File` de Node. El 6 (el tipo declarado) quedó reemplazado por la corrección 3 | Usuario |
| Foto vertical de un celular | Prueba manual del usuario: la orientación del EXIF la aplica el backend (R-027) | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `dde595a`: 5 hallazgos (el estado de la foto pasaba de un paciente a otro si el router cambiaba solo el `$id`; `getBlob` aceptaba una página HTML como archivo; el navegador rechazaba por la extensión un JPG que la API acepta; "Cambiar" y "Quitar" sobre una foto cuyo archivo falta; el `File` en la caché de mutaciones); 3 de 3 mutaciones detectadas | Revisión |
| Correcciones | 1: **convención nueva**, cada ruta de detalle dibuja su página con `key={id}` (`/pacientes/$id` y `/clientes/$id`; `frontend/CLAUDE.md` §5). 2: `getBlob` da `HTTP_ERROR` con HTML o con un tipo distinto de `expectedType` (la foto pide `image/`). 3: el tipo de la foto por los primeros bytes, como la API. 4: con 404 `PATIENT_PHOTO_NOT_FOUND` al verla, el detalle pasa a `has_photo: false`. 5: la subida con `gcTime: 0` y `reset()`, y el foco tras el error por un pedido propio | Usuario |
| Presupuesto por entrega | Dos reglas nuevas en el `CLAUDE.md` de la raíz: si la corrida final falla por un detalle, se repiten solo el paso que falló y los tests del archivo tocado; si Playwright ya cubre un flujo, en Edge solo lo que Playwright no ve | Usuario |
| Tarea para después de la F1 | Una entrega `chore`: la preparación de Playwright reutiliza `syncvet_e2e` cuando las migraciones no cambiaron y solo restaura los datos de la demo ([handoff](../../03-plan-desarrollo/handoff.md#próximas-tareas)) | Usuario |
| Validación | El usuario dio la entrega por validada con las correcciones. Edge sin ventana contra `syncvet.test`: 10 de 10 de la implementación y 5 de 5 de las correcciones, con la demo como estaba. Desde `frontend/`: `npm test`, 746 tests en 39 archivos; `typecheck`, `lint` y `build` sin errores ni avisos; Playwright, `pacientes-foto` en la implementación y después de las correcciones | Usuario |
| Siguiente | La entrega 1.5 del frontend (especies y razas), APROBADA (R-064), en `feature/front-1-5-especies`, creada desde `feature/front-1-4-foto` | Usuario |

**Qué no cambió.** Backend, rutas, permisos de la API, `error_code`, códigos HTTP y colecciones.

# R-071 Cierre de la entrega 1.5 del frontend (especies y razas) (2026-11-16)

Sexta entrega de pantallas de la F1 del frontend (R-064), en `feature/front-1-5-especies` (desde `feature/front-1-4-foto` en
`28c5eb5`), sin integrar a `main`: se integra al cerrar la F1. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#entrega-15).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | `/especies` (`species.index`, los tres roles) y "Especies" en el menú: las especies con sus razas en una lista que se despliega, "Común" o "Propia" e "Inactivada". Con los permisos de especie (administrador y veterinario), solo en las propias: renombrar, activar o inactivar (sin confirmación) y eliminar (con `SPECIES_IN_USE` o `BREED_IN_USE`, "Inactivar …" desde el diálogo); "Nueva especie" y "Nueva raza" (también en una común). El nombre con Zod y los mensajes de la API; el repetido, 422 en el campo; `CATALOG_GLOBAL_NOT_EDITABLE` en el diálogo, no `/sin-permiso`. Cada cambio vacía la lista de los selectores del paciente (`afterCatalogChanged`). Sin paquetes nuevos y sin cambios en la API | Diseño (R-064) |
| Desvíos | Los 1 a 10, aceptados: entre ellos, "Inactivada" en lugar de "Inactiva" (test de voseo); activar o inactivar sin confirmación; las listas de especies que no están a la vista se vacían (`resetQueries`), no se invalidan (hallado en Edge); `useSingleAction` repetido en `features/species` | Usuario |
| Listado de especies (desvío 11) | Solo las activas por defecto, con "Incluir las inactivas" (`?inactivas=1`) como los demás listados, visible solo con `species.update`; sin el filtro, la consulta de los selectores del paciente (`speciesQueryOptions`) | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `dd15e51`: 3 hallazgos (H1, el listado con las inactivas siempre; H2, un 404 que invalidaba en lugar de vaciar la lista de los selectores, sin test en el botón; H3, el diálogo con `*_IN_USE` seguía preguntando si eliminar) y 2 detalles (un comentario largo; el nombre viejo de la especie un instante en el detalle del cliente, aceptado como informativo); 2 de 3 mutaciones detectadas | Revisión |
| Correcciones | H1 a H3 y el detalle 1, en `26772d1`; 3 de 3 mutaciones detectadas. Desvíos 12 (la 1.3b no quita la pregunta con `PATIENT_IN_USE`), 13 (sin el filtro, el listado puede tardar hasta 5 minutos en mostrar cambios de otra PC) y 14 (también se ajustó el caso 1 de Playwright), aceptados | Usuario |
| Tareas para después de la F1 | En la entrega `chore` ([handoff](../../03-plan-desarrollo/handoff.md#próximas-tareas), tarea 3c): que la colección de Postman de especies borre lo que crea; limpiar los restos "Hurón doméstico NNNNNN" de la base de desarrollo; quitar la pregunta del diálogo de baja del paciente con `PATIENT_IN_USE` (desvío 12); mover `useSingleAction` a `src/lib/hooks` (desvío 6) | Usuario |
| Validación | El usuario dio la entrega por validada con las correcciones. Edge sin ventana contra `syncvet.test`: 6 de 6 de la primera sesión, 6 controles de la segunda (con la repetición del que encontró el desvío 8) y 3 de 3 de las correcciones, con la demo como estaba. Desde `frontend/`: `npm test`, 800 tests en 42 archivos; `typecheck` y `build` sin errores; `lint` en verde después de corregir 2 errores de estilo del test; Playwright, `e2e/especies.spec.ts`, 2 de 2 | Usuario |
| Siguiente | El cierre de la F1: Playwright completo, la suite del backend completa en MySQL, Newman de todas las colecciones y la integración a `main` | Usuario |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP y colecciones.

# R-072 Cierre de la F1 del frontend y merge a `main` (2026-11-16)

Cierre de la F1 del frontend (clientes y pacientes), junto con la entrega de soporte del backend (front-01), con las siete entregas
cerradas (R-065 a R-071). El detalle, en el [cierre de la F1](../../03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#cierre-de-la-f1).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Entregas | front-01 (R-065), 1.1 (R-066), 1.2 (R-067), 1.3a (R-068), 1.3b (R-069), 1.4 (R-070) y 1.5 (R-071): COMPLETADO, cada una con su revisión, sus correcciones y su validación; las ramas de la cadena, en GitHub. El cierre de la 1.5 estaba escrito sin commitear: va en su propio commit (`7ef0a9e`), antes del de este cierre | Cierre |
| F1 | COMPLETADO: el criterio de cierre se cumple, punto por punto en el cierre de la F1 (entregas cerradas; Vitest, Playwright, `typecheck`, `lint` y `build` en verde; el desvío 8 de la 0.1 verificado en la 1.3b; Newman de todas las colecciones sin fallas) | Usuario |
| Regresión del backend | Desde `backend/`, en serie, sobre `7ef0a9e`: SQLite, 1463 casos (16 omitidos, solo de MySQL), 14274 aserciones; MySQL, 1463 casos sin omitidos, 14360 aserciones. Frente a la F0, 18 casos más. 78 rutas, 135 permisos y 36 migraciones, sin cambios. `composer audit`, sin avisos | Cierre |
| Regresión del frontend | Desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 800 tests en 42 archivos; `npm run test:e2e`, 22 de 22 después de ajustar los tests (desvíos 1 y 2). `npm audit`: solo el aviso de `minimatch` de la deuda 19 (ahora en 9 paquetes) | Cierre |
| Desvíos del cierre | 1: `e2e/permisos.spec.ts` esperaba el menú sin "Especies" (la 1.5 solo corrió su spec). 2: cuatro casos escribían en "Buscar clientes" y hacían clic sin esperar la URL; con la PC cargada, la búsqueda devolvía la página al listado. Ahora esperan `?buscar=…`. Solo en los tests | Cierre |
| Postman | Newman, las 14 colecciones de `docs/05-pruebas/postman` (R-057), servidor en el puerto 8001: 483 pedidos y 1074 aserciones, sin fallas | Cierre |
| Deudas | Abiertas en la F1: 21 (backend), 23 y 24 (frontend: el clic durante la espera de la búsqueda y el límite del test de voseo), de prioridad baja. Resuelta: 22. Abiertas: 2, 3, 5, 6, 15, 17 y 21 (backend); 19, 20, 23 y 24 (frontend) | Cierre |
| Merge a `main` | Autorizado por el usuario el 2026-11-16: merge `--no-ff` desde `feature/front-1-5-especies`, que contiene `docs/front-f1-diseno` y las ramas de front-01 a la 1.4. Antes, `main` local adelantado a `origin/main` (`668eb48`, el merge de la F0) con `--ff-only`. Antes del push de `main`, en `main`: la suite del backend en serie (SQLite y MySQL) y `npm test` del frontend. Las ramas se conservan. El hash del merge se informa al usuario y se anota en el handoff al empezar lo siguiente | Usuario |
| Siguiente | La entrega `chore/…` de las tareas 3b y 3c del handoff; después, el análisis y el diseño de la F2 del frontend (datos maestros), sobre la API de la FASE 2 | Usuario |

**Qué no cambió.** Código de la aplicación, rutas, permisos, `error_code`, contrato de respuesta y colecciones: el cierre documenta y
ajusta solo cinco tests de Playwright.

# R-073 Entrega `chore/post-f1`: Playwright más rápido, la colección de especies que limpia y las deudas 23 y 24 (2026-11-16)

Entrega de mantenimiento después de la F1 del frontend, en `chore/post-f1` (desde `main` en `7164c20`, el merge de la F1, R-072),
con las tareas 3b y 3c del [handoff](../../03-plan-desarrollo/handoff.md#próximas-tareas) y las deudas 23 y 24, según el pedido del
usuario. Sin cambios en el backend ni en la API.

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Merge de la F1 | `7164c20`, en `main` (R-072). `npm test` en `main`: 796 de 800 la primera corrida, con la PC cargada; 800 de 800 las siguientes | Usuario |
| Playwright más rápido (3b) | La preparación (`e2e/support/prepare.ts`) compara la huella de `backend/database/migrations` (SHA-256 de los nombres y el contenido, `e2e/support/fingerprint.ts`) con la de la última preparación que terminó bien (`frontend/e2e/.e2e-migrations`, ignorado por Git). Misma huella: `migrate` (crea la base si falta), vaciar las tablas con TRUNCATE (menos `migrations`; solo las que tienen filas o el autoincremental avanzado, con `information_schema_stats_expiry = 0`, así los ids vuelven a empezar como en una base nueva) y `db:seed`; otra huella o `E2E_FRESH=1`: `migrate:fresh --seed`, como antes. En los dos, el `DemoSeeder` con la contraseña de la corrida. La huella se borra antes de tocar la base y se guarda al terminar: una preparación cortada a la mitad obliga a recrear. Las guardas que exigen `syncvet_e2e` (el nombre pedido y el que resuelve Laravel) siguen antes que todo. Con sus tests en `src/test/e2e-prepare.test.ts` (12, antes 6) | Usuario (pedido); diseño en la entrega |
| Tiempos (3b) | Un caso (`sesion.spec.ts`, "salir en una pestaña"), según Playwright: antes, 55,1 s; después, recreando (`E2E_FRESH=1`) 53,9 s y 49,7 s, y reutilizando 25,4 s y 22,6 s. De la preparación, `migrate:fresh --seed` tardaba unos 30 s; vaciar las tablas, unos 3 s, y `db:seed`, unos 2 s. Corrida completa, reutilizando: 22 de 22 en 3,9 min (235 s de reloj), contra 4,1 min al cerrar la F1 (recreando): el ahorro es el de la preparación, unos 30 s por corrida; el resto es el tiempo de los casos | Entrega |
| Colección de especies (3c) | Suma el login del administrador (01c, `{{token_admin}}`) y al final la limpieza: baja del paciente (38) y de su dueña (39), que la API permite. La especie "Hurón doméstico {{run}}" y su raza Albino no se pueden borrar: la baja cuenta también los pacientes borrados (`withTrashed`, `SpeciesService` y `BreedService`); quedan inactivas (pasos 24 y 25) y el paso 40 lo comprueba (409 `SPECIES_IN_USE`). Sigue siendo repetible: Newman, dos veces seguidas contra `syncvet`, 45 pedidos y 99 aserciones cada una, sin fallas | Usuario (pedido) |
| Base de desarrollo (3c) | Las 20 especies "Hurón doméstico NNNNNN" de `syncvet` (ids 10, 13, 16, 19, 22, 25, 28, 31, 34, 37, 40, 43, 46, 49, 52, 55, 58, 63, 66 y 69; las dos últimas, de las corridas de esta entrega): la baja por la API dio 409 `SPECIES_IN_USE` en todas, porque un paciente las usó. Todas ya estaban inactivas, con su raza Albino inactiva. Quedan así; los pacientes y las dueñas de las 18 corridas anteriores siguen en la base (las corridas de antes no los borraban) | Entrega; se informa al usuario |
| Deuda 23 | Confirmada con Vitest: con el detalle sin responder, vencía la espera y el cambio de la búsqueda pisaba la navegación. `useSearchText` guarda la ruta del listado al montar y no llama a `apply` si `router.latestLocation` (cambia apenas sale la navegación) ya es otra: la espera pendiente se cancela, en todos los listados. Un test en `/clientes` y otro en `/pacientes`, que fallan sin el arreglo; las cuatro esperas de `?buscar=` antes del clic en Playwright (`especies`, `pacientes`, `pacientes-estado` y `pacientes-foto`), quitadas. Resuelta | Usuario (pedido) |
| Deuda 24 | El test "los textos de src/ usan voseo" con su propio límite, 30 s. Resuelta | Usuario (pedido) |
| Mutaciones | 3 de 3 detectadas: sin la guarda de `useSearchText` fallan el test de clientes y el de pacientes; sin borrar la huella antes de tocar la base, fallan 4 de los 12 tests de la preparación | Entrega |
| Validación | Desde `frontend/`: `npm test`, 808 tests en 42 archivos (800 + 2 de la deuda 23 + 6 de la preparación); `typecheck` y `build` sin errores; `lint`, en verde después de corregir el tipo de una variable en los dos tests nuevos (repetidos solo `lint` y los tests de esos archivos). Playwright completo: 22 de 22 en 3,9 min, reutilizando la base (4,1 min al cerrar la F1, recreándola). Newman de especies, dos veces: sin fallas | Entrega |
| Pendiente de la 3c | No entraron en este pedido: quitar la pregunta del diálogo de baja del paciente con `PATIENT_IN_USE` (desvío 12 de la 1.5) y mover `useSingleAction` a `src/lib/hooks` (desvío 6 de la 1.5). Siguen en el handoff | Usuario |
| Siguiente | El merge a `main`, después de que el usuario vea los tiempos; después, el análisis y el diseño de la F2 del frontend | Usuario |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP y contrato de respuesta. Las demás colecciones de
Postman.

# R-074 Aprobación del diseño de la F2 del frontend (datos maestros) (2026-11-16)

El usuario aprueba el diseño de la F2 del frontend,
[`f2-datos-maestros.md`](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md), en `docs/front-f2-diseno`
(desde `main` en `1daee8c`, el merge de `chore/post-f1`, R-073), con las 13
[preguntas del diseño](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#preguntas-del-diseño) respondidas con la
recomendación.

| Tema | Decisión | Quién |
|---|---|---|
| Entregas | 2.1 (sucursales y depósitos, con lo que queda de la tarea 3c), 2.2a y 2.2b (usuarios), front-02 (backend), 2.3 (veterinarios), 2.4 (servicios), 2.5 (configuración y logo) y 2.6 (auditoría), en ramas encadenadas desde `docs/front-f2-diseno`; todo se integra a `main` al cerrar la F2 | Usuario |
| Ficha de veterinario en los usuarios (pregunta 1) | Entrega de soporte del backend front-02, aditiva y antes de la 2.3: `veterinarian` (`id` y `code`, o `null`) en `GET users`, sin N+1 y sin cambiar otras respuestas | Usuario |
| Navegación y pantallas (preguntas 2, 4, 5, 9 y 13) | Sucursales, usuarios, la clínica y la auditoría bajo `/configuracion/…`, con una página de entrada; "Veterinarios" y "Servicios" con su ítem para los tres roles; la pantalla de sucursales, solo para el administrador; los depósitos, en diálogos dentro del detalle de la sucursal; el logo también en la barra; activar, inactivar y eliminar como botones del detalle | Usuario |
| Usuarios (preguntas 3, 6 y 7) | Dos entregas (2.2a y 2.2b); la contraseña de otro usuario detrás de "Cambiar la contraseña", con la repetición y el aviso de que se cierran sus sesiones, sin mandar `password` si no cambia; confirmación cuando el administrador se quita su propio rol de administrador | Usuario |
| Servicios (pregunta 8) | El precio acepta los puntos de miles ("150.000") y se manda como texto de solo dígitos | Usuario |
| Auditoría (preguntas 10 a 12) | "Hasta" con hora se manda con los segundos en 59; los campos del detalle, con su nombre de la API; el frontend también oculta contraseñas, tokens y rutas (`maskAuditValues`) | Usuario |
| Rama de la 2.1 | `feature/front-2-1-sucursales`, creada desde `docs/front-f2-diseno` y subida, sin código hasta la autorización del usuario | Usuario |

**Qué no cambió.** Código, tests, rutas, permisos y colecciones: la aprobación solo documenta.

# R-075 Cierre de la entrega 2.1 del frontend (sucursales y depósitos) (2026-11-16)

Primera entrega de la F2 del frontend (R-074), en `feature/front-2-1-sucursales` (desde `docs/front-f2-diseno` en `16aec1d`), sin
integrar a `main`: se integra al cerrar la F2. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-21).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | `/configuracion` como página de entrada (una tarjeta por sección), `/configuracion/sucursales` (listado, alta con su depósito por defecto, detalle con sus depósitos y edición) y los depósitos en diálogos del detalle, solo para el administrador; marcar por defecto, activar e inactivar, con la por defecto que no se desmarca ni se inactiva (409 y 422 en el diálogo); cada cambio invalida `['me']` (el selector de la barra). Sin paquetes nuevos y sin cambios en la API | Diseño (R-074) |
| Tarea 3c | Resuelta: `useSingleAction` en `src/lib/hooks/use-single-action.ts`, sin cambios de comportamiento, con sus tests (desvío 6 de la 1.5); el diálogo de baja del paciente con `PATIENT_IN_USE` muestra solo el mensaje de la API (desvío 12 de la 1.5) | Usuario (pedido, R-074) |
| Desvíos | Los 1 a 9, aceptados: entre ellos, `useSingleAction` sacado en un commit propio y no movido (el hook no tenía archivo propio), "Inactivada" e "Inactivado" por el test de voseo, Playwright con el caso del pedido de la entrega y 5 controles en Edge | Usuario |
| Desvío 10 | Cambiado: en la edición de una sucursal inactiva, "Sucursal por defecto" queda sin marcar y deshabilitada, con "Activá la sucursal para marcarla como la por defecto." (antes, habilitada, con el 422 de la API en el campo). La misma regla para el depósito inactivo, con "Activá el depósito para marcarlo como el por defecto." | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `7de78f3`, `7e27822` y `626759b`: sin hallazgos que cambien el comportamiento. Corregidos en `b30db49`: el desvío 10 y 6 tests que faltaban (doble clic en la confirmación de la sucursal y en "Crear el depósito", 404 en una acción de la sucursal y de un depósito, el teclado en el diálogo de depósito y la sucursal inactiva) | Revisión |
| Mutaciones de la revisión | 3: sin los tests nuevos, las tres sobrevivían; con ellos, sin la guarda de `StatusDialog` y sin la vuelta al listado de `useBranchGone`, detectadas. Sin la guarda de `WarehouseDialog`, sigue sin detectarse: es equivalente en jsdom (el clic llama a `setErrors`, React dibuja el botón en espera en el mismo evento y `Button` ignora el segundo clic); la `ref` queda como segunda defensa | Revisión |
| Validación | El usuario dio la entrega por validada. Claude Code: 5 de 5 en Edge sin ventana contra `syncvet.test`, con la demo sin cambios; Playwright, `e2e/sucursales.spec.ts` y el caso de `/configuracion` de `permisos.spec.ts`, 2 de 2. Desde `frontend/`: `npm test`, 909 tests en 46 archivos, en verde (sobre `b30db49`); `typecheck`, `lint` y `build` sin errores ni avisos; el test del depósito inactivo (`a31b6ef`), con `vitest related` (393 tests en 19 archivos) y ESLint de lo tocado | Usuario |
| Siguiente | La entrega 2.2a (usuarios: listado, detalle, alta y edición), en `feature/front-2-2a-usuarios`, creada desde `feature/front-2-1-sucursales` y subida, sin código hasta la autorización del usuario | Usuario |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP, contrato de respuesta y colecciones.

# R-076 Cierre de la entrega 2.2a del frontend (usuarios: listado, detalle, alta y edición) (2026-11-16)

Segunda entrega de la F2 del frontend (R-074), en `feature/front-2-2a-usuarios` (desde `feature/front-2-1-sucursales`, en el
cierre de la 2.1, `0f7b80b`), sin integrar a `main`: se integra al cerrar la F2. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-22a).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | La tarjeta "Usuarios" en `/configuracion` y `/configuracion/usuarios` (listado con búsqueda, rol, sucursal e "Incluir los inactivos" en la URL; detalle; alta y edición con roles y sucursales en `CheckboxGroupField`), solo para el administrador. La contraseña: obligatoria en el alta, detrás de "Cambiar la contraseña" al editar a otro (sin la clave `password` si no se cambia) y ausente al editarse a sí mismo (el enlace a `/perfil`); fuera de la caché de mutaciones. Editarse invalida `['me']`; quitarse el rol de administrador pide confirmación y vuelve a `/`. Sin paquetes nuevos y sin cambios en la API | Diseño (R-074) |
| Desvíos | Los 11, aceptados: entre ellos, Playwright con el caso del pedido de la entrega, "Inactivado" e "(inactivada)" por el test de voseo, `USER_IS_VETERINARIAN` con el código de la ficha sin enlace hasta la 2.3, los errores del alta y la edición en las opciones de `useMutation` (el `reset()` de `onSettled` descarta los callbacks de `mutate()`) y la regla de nombres de personas común en `src/lib/person-name.ts` | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `fff201c`: sin hallazgos que cambien el comportamiento. Corregido en `e8ecdeb` (menor): el 422 de `password` al editarse a sí mismo va a la alerta (caía en un campo que no se dibuja). 3 tests nuevos y una comprobación en uno existente: el 422 del alta y el de la contraseña filtrada de otro usuario con la contraseña fuera de la caché, el 422 de `password` propio en la alerta e `invalidateBranchQueries` al sacar la sucursal actual | Revisión |
| Mutaciones de la revisión | 3 de 3 detectadas: sin `reset()` en la edición y sin `invalidateBranchQueries` (las dos, solo por los tests nuevos: sin ellos, sobrevivían) y las sucursales inactivas que el usuario ya tiene fuera de las opciones | Revisión |
| Validación | El usuario aceptó los desvíos y pidió el cierre. Claude Code: 5 de 5 en Edge sin ventana contra `syncvet.test` (el usuario temporal, borrado por la API); Playwright, `e2e/usuarios.spec.ts`, 1 de 1 (el usuario entra con la contraseña nueva en otro contexto y la anterior ya no sirve). Desde `frontend/`, sobre `e8ecdeb`: `npm test`, 982 tests en 49 archivos, en verde; `typecheck`, `lint` y `build` sin errores ni avisos | Usuario |
| Siguiente | La entrega 2.2b (usuarios: inactivar, reactivar y baja), en `feature/front-2-2b-usuarios`, creada desde `feature/front-2-2a-usuarios` y subida, sin código hasta la autorización del usuario | Usuario |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP, contrato de respuesta y colecciones.

# R-077 Cierre de la entrega 2.2b del frontend (usuarios: inactivar, reactivar y baja) (2026-11-16)

Tercera entrega de la F2 del frontend (R-074), en `feature/front-2-2b-usuarios` (desde `feature/front-2-2a-usuarios`, en el
cierre de la 2.2a, `0bf3ed0`), sin integrar a `main`: se integra al cerrar la F2. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-22b).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | En el detalle de `/configuracion/usuarios/$id`, "Inactivar" o "Reactivar" (`users.update`, `PATCH users/{id}` con solo `{ is_active }`) y "Eliminar" (`users.destroy`), ocultos en el propio usuario; los avisos de las sesiones al inactivar y del nuevo ingreso al reactivar; `USER_LAST_ADMIN` y `USER_SELF_ACTION` en el diálogo con solo "Cerrar"; `USER_IN_USE` con "Inactivar" (si está activo), que abre el diálogo de inactivar; con el 200 de la baja, al listado. Invalida `['users']` y `['veterinarians']`. Sin paquetes nuevos y sin cambios en la API | Diseño (R-074) |
| Desvíos | Los 8, aceptados: entre ellos, el detalle borrado sale de la caché con `removeQueries` al llegar al listado (no con `resetQueries`: lo volvería a pedir mientras está a la vista), `dialogError` repetido en `UserActions.tsx` y `BranchDialogs.tsx`, y Playwright con el cierre de la sesión abierta como 401 al recargar y `USER_INACTIVE` al volver a ingresar | Usuario |
| Código común | La función que trata los errores en los diálogos (`dialogError`, desvío 2) pasa a código común en la 2.3, que la usa también en veterinarios (anotado en su diseño) | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `2cd6dd4`: sin hallazgos que cambien el comportamiento. Corregido en `3265903` (solo tests): la baja con el 200 cuenta los pedidos del detalle (no se vuelve a pedir) y revisa `['veterinarians']` invalidado, con doble clic (un solo `DELETE`); test nuevo del paso de la baja con `USER_IN_USE` al diálogo de inactivar (el foco en "Cancelar"; Escape lo cierra sin pedido y el foco vuelve a "Eliminar") | Revisión |
| Mutaciones de la revisión | 3 detectadas de 4 probadas: los errores de negocio con el botón para probar de nuevo, la baja sin invalidar `['veterinarians']` y la baja sin `useSingleAction`. No detectada: `resetQueries` del detalle antes de navegar (en jsdom no se vuelve a pedir); queda anotada, sin otro test | Revisión |
| Validación | El usuario aceptó los desvíos y pidió el cierre. Claude Code: 5 de 5 en Edge sin ventana contra `syncvet.test` (el usuario temporal, borrado por la API); Playwright, `e2e/usuarios-estado.spec.ts`, 1 de 1 (la sesión abierta termina al inactivar, el login responde `USER_INACTIVE`, la reactivación deja entrar de nuevo y la baja de `vet-a` ofrece inactivar). Desde `frontend/`, sobre `3265903`: `npm test`, 996 tests en 49 archivos, en verde; `typecheck`, `lint` y `build` sin errores ni avisos | Usuario |
| Siguiente | La entrega front-02 del backend (la ficha del veterinario en `GET users`), en `feature/front-02-usuarios-veterinario` (el nombre de la rama lo eligió el usuario; el diseño decía `feature/front-02-ficha-en-usuarios`), creada desde `feature/front-2-2b-usuarios` y subida, sin código hasta la autorización del usuario | Usuario |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP, contrato de respuesta y colecciones.

# R-078 Cierre de la entrega front-02 del backend (la ficha de veterinario en el listado de usuarios) (2026-11-16)

Entrega de soporte del backend pedida por la F2 del frontend (pregunta 1 de su diseño, R-074), en
`feature/front-02-usuarios-veterinario` (desde `feature/front-2-2b-usuarios`, en el cierre de la 2.2b, `85ebf3a`), sin integrar a
`main`: se integra al cerrar la F2. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-front-02).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | `GET users` trae `veterinarian` (`id` y `code`, o `null` sin ficha o con la ficha eliminada): la clave pasó de `ShowUserResource` a `UserResource`, al final de sus claves, y `UserController::RELATIONS` suma `veterinarian` (sin N+1). El detalle, el alta, la edición y `me` conservan sus claves y su orden. Sin rutas, permisos ni migraciones (78, 135, 36); sin cambios en `error_code` ni códigos HTTP | Diseño (R-074) |
| `FictitiousDocumentsTest` | Fallaba en la rama desde la 2.1 por `'1234567'` (7 dígitos, rango de cédulas reales) como teléfono corto en `frontend/src/features/branches/form.test.ts`; corregido en `f0218e6` con `'9'.repeat(7)`, armado en el código, sin cambiar lo que prueba | Usuario (pedido) |
| Presupuesto por entrega | Las entregas del frontend corren también `php artisan test --filter=FictitiousDocumentsTest`, que revisa `frontend/src` y `frontend/e2e` ([`CLAUDE.md`](../../../CLAUDE.md) de la raíz, §2, `f0218e6`) | Usuario |
| Desvíos | Aceptados: el test sin N+1 hace un pedido antes de contar (la caché de permisos de Spatie); `countQueries` quedó en el trait `Tests\Concerns\InspectsQueries` y las tres copias de la deuda 21 siguen; además, el desvío 1 del [resultado](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-front-02) (`FictitiousDocumentsTest`, abajo) | Usuario: aceptados |
| Revisión | Sin hallazgos, en la misma sesión del cierre, sobre el WIP `bf98d8d` (diff con `85ebf3a`): la forma del diseño, sin N+1 (`with`), la ficha eliminada como `null` (`SoftDeletes`), `UserResource` usado solo en `UserController`, las claves del detalle y de `me` sin cambios, y los tests que fallarían con la regla rota (las 3 mutaciones de la implementación). `composer test:fast`: 1467 casos, 1451 pasan, 16 omitidos | Revisión |
| Validación | El usuario dio la entrega por validada (`composer test` en SQLite, Newman de usuarios y la revisión) y pidió el cierre. Desde `backend/`, `composer test` en serie sobre `f0218e6`: 1467 casos, 1451 pasan, 16 omitidos (solo MySQL), 14303 aserciones. Newman de la colección de usuarios (en la implementación, sobre `bf98d8d`; el código del backend no cambió después): 54 pedidos y 138 aserciones, sin fallas. Desde `frontend/`, sobre `f0218e6`: `npm test`, 996 tests en 49 archivos, en verde; `typecheck`, `lint` y `build` sin errores ni avisos | Usuario |
| Siguiente | La entrega 2.3 del frontend (veterinarios), en `feature/front-2-3-veterinarios`, creada desde `feature/front-02-usuarios-veterinario` (en el cierre de front-02) y subida, empezando por pasar `dialogError` a código común (R-077); sin código hasta la autorización del usuario | Usuario |

**Qué no cambió.** Rutas, permisos, migraciones, `error_code`, códigos HTTP y las respuestas del detalle, el alta y la edición del
usuario y de `me`.

# R-079 Cierre de la entrega 2.3 del frontend (veterinarios) (2026-11-16)

Cuarta entrega de pantallas de la F2 del frontend (R-074), en `feature/front-2-3-veterinarios` (desde
`feature/front-02-usuarios-veterinario`, en el cierre de front-02, `0ab4dc7`), sin integrar a `main`: se integra al cerrar la F2.
El detalle, en su [resultado](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-23).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | "Veterinarios" en el menú (los tres roles) y `/veterinarios` con búsqueda, estado (`?estado=inactivos\|todos`, traducido a `status=inactive` o `include_inactive=1`) y sucursal, sin la comisión ni las notas; el enlace al detalle y "Nuevo veterinario", solo para el administrador. `/veterinarios/$id` (la comisión "12,5 %", el usuario con su enlace; Editar, Inactivar o Activar y Eliminar), `/veterinarios/nuevo?usuario=` (los usuarios activos con el rol y sin ficha; la ficha eliminada vuelve con su código) y `/veterinarios/$id/editar` (sin `user_id`, `code` ni `status`). La matrícula en mayúsculas y recortada; la comisión con coma o punto, mandada como texto con punto. `VETERINARIAN_IN_USE` ofrece "Inactivar". En usuarios, el enlace a la ficha, "Crear la ficha de veterinario" y el enlace en la alerta de `USER_IS_VETERINARIAN`. Invalida `['veterinarians']`, `['users']` y `['me']` si la ficha es la propia. Sin paquetes nuevos y sin cambios en la API | Diseño (R-074) |
| Código común | `dialogError` pasó a `src/lib/api/dialog-error.ts` (`fcf6667`), con sus tests; sucursales, usuarios y veterinarios la usan, sin cambiar el comportamiento | Usuario (R-077) |
| Desvíos | Los 9, aceptados: entre ellos, el filtro de estado del diseño (`?estado=`) en lugar de "Incluir los inactivos", decidido al empezar la entrega; el estado se cambia solo con sus botones; el selector del alta pide 100 usuarios (`per_page` máximo); la ficha restaurada, con el mismo mensaje de la API que un alta; y el 7: la corrida final de la implementación dio 6 fallas en `lib/auth/permissions.test.ts` (el menú sin "Veterinarios"), corregidas y repetido solo ese archivo (12 de 12), con la cifra total para la revisión | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `fcf6667` y `72975a6` (diff con `0ab4dc7`): sin hallazgos que cambien el comportamiento. Antes de revisar, `npm test` completo: 1073 tests en 53 archivos, en verde (la cifra del desvío 7, ahora de una corrida). Corregido en `359b2d7` (solo tests): inactivar la ficha propia vuelve a pedir `me`; un 404 en una acción avisa y vuelve al listado; la comisión `1e1` se rechaza como decimal, como la API | Revisión |
| Mutaciones de la revisión | 3 de 3 detectadas: sin volver a pedir `me` en la ficha propia, la matrícula sin pasar a mayúsculas y `VETERINARIAN_IN_USE` sin "Inactivar" | Revisión |
| Validación | El usuario aceptó los desvíos, pidió la revisión y el cierre. Claude Code: 5 de 5 en Edge sin ventana contra `syncvet.test` (el usuario temporal, borrado por la API); Playwright, `e2e/veterinarios.spec.ts`, 1 de 1 (26 casos en total), con el recepcionista que ve el listado sin la comisión, sin enlaces ni "Nuevo veterinario". Desde `frontend/`, sobre `359b2d7`: `npm test`, 1076 tests en 53 archivos, en verde; `typecheck`, `lint` y `build` sin errores ni avisos. Desde `backend/`, `FictitiousDocumentsTest`, 3 de 3 | Usuario |
| Siguiente | La entrega 2.4 del frontend (servicios), en `feature/front-2-4-servicios`, creada desde `feature/front-2-3-veterinarios` (en este cierre) y subida; sin código hasta la autorización del usuario | Usuario |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP, contrato de respuesta y colecciones.

# R-080 Cierre de la entrega 2.4 del frontend (servicios) (2026-11-17)

Quinta entrega de pantallas de la F2 del frontend (R-074), en `feature/front-2-4-servicios` (desde `feature/front-2-3-veterinarios`,
en el cierre de la 2.3, `0743a72`), sin integrar a `main`: se integra al cerrar la F2. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-24).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | "Servicios" en el menú (los tres roles, después de "Especies") y `/servicios` con búsqueda, categoría y estado (`?estado=inactivos\|todos`, traducido a `status=inactive` o `include_inactive=1`); el precio con `formatGuaranies`, la duración "30 min" y el IVA "10 %". `/servicios/$id` para los tres roles; Editar, Inactivar o Activar y Eliminar, y "Nuevo servicio", solo para el administrador. El formulario: el nombre con `ServiceMessages::NAME_REGEX`; el precio como texto con `inputMode="numeric"`, los puntos de miles sacados y mandado como texto de solo dígitos (`"150000"`), nunca como número, con el mensaje de `WholeAmount`; la duración de 5 a 720, múltiplo de 5; el IVA (0, 5 o 10) como número. `SERVICE_NAME_NOT_EDITABLE` al campo nombre; `SERVICE_IN_USE` ofrece "Inactivar". Sin paquetes nuevos y sin cambios en la API | Diseño (R-074) |
| Proceso | El presupuesto por entrega del `CLAUDE.md` de la raíz suma una excepción: si `npm test` de la corrida final falla en un archivo, después de corregirlo se repite la suite completa (para que la cifra registrada sea de una corrida real) | Usuario |
| Desvíos | Los 10, aceptados: entre ellos, el filtro de estado del diseño (`?estado=`) y el estado fuera del formulario, decididos al empezar la entrega; los puntos solo si agrupan miles y los ceros de adelante fuera de la cuenta, como `WholeAmount::normalize`; Playwright sin el cambio de nombre de un servicio usado (el `DemoSeeder` no tiene servicios ni turnos; está en Vitest); y el 10: a 375 px el menú horizontal de `SessionLayout` se sale de la pantalla (ya pasaba antes de la 2.4) | Usuario |
| El menú en pantallas chicas | Se corrige en la 2.5, junto con el logo en la barra: el menú se pliega detrás de un botón, accesible con el teclado y con lector de pantalla, sin el JS de Tabler (anotado en el [diseño de la 2.5](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#25-configuración-de-la-clínica-y-logo)) | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `0686f94` (diff con `0743a72`): sin hallazgos que cambien el comportamiento. Corregido en `b0bab2b` (solo tests): el listado y el detalle sin acciones, probados también con el veterinario (2 tests) | Revisión |
| Mutaciones de la revisión | 3 de 3 detectadas: el nombre sin `(?!.*//)`, la duración sin el múltiplo de 5 y las acciones del detalle sin mirar el permiso | Revisión |
| Validación | El usuario aceptó los desvíos, pidió la revisión y el cierre. Claude Code: 4 de 5 en Edge sin ventana contra `syncvet.test` (el control 1, explicado en el desvío 10; el servicio temporal, borrado por la API); Playwright, `e2e/servicios.spec.ts`, 1 de 1 (27 casos en total): el alta con "150.000" mandado como `"150000"` y "Gs. 150.000" después de recargar. Corrida del cierre, el 2026-11-17, en otra PC, sobre `b0bab2b`, desde `frontend/`: `npm test`, 1188 tests en 56 archivos, en verde; `typecheck`, `lint` y `build` sin errores ni avisos. Desde `backend/`, `FictitiousDocumentsTest`, 3 de 3 | Usuario |
| Siguiente | La entrega 2.5 del frontend (configuración de la clínica y logo, con el menú plegable), en `feature/front-2-5-configuracion`, que se crea desde el commit de este cierre; sin código hasta la autorización del usuario | Usuario |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP, contrato de respuesta y colecciones.

# R-081 Cierre de la entrega 2.5 del frontend (configuración de la clínica y logo) (2026-11-17)

Sexta entrega de pantallas de la F2 del frontend (R-074), en `feature/front-2-5-configuracion` (desde `feature/front-2-4-servicios`,
en el cierre de la 2.4, `37ac3cf`), sin integrar a `main`: se integra al cerrar la F2. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-25).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | La tarjeta "Clínica" en `/configuracion` y `/configuracion/clinica` (`system-settings.update`): el nombre, la razón social, el correo, el teléfono, el sitio web y el pie de los documentos con las reglas y los mensajes de `UpdateSystemSettingsRequest`; el RUC solo para leer y nunca en el pedido; `PATCH system-settings` con el formulario completo y las claves vacías en `null`. El logo como la foto: vista previa, subir, reemplazar y quitar, 2 MB justos, el tipo por el contenido. El logo en la barra para los tres roles (`system-settings.show-logo`), con la misma clave de la caché: cambia sin recargar; sin logo o con un error, la marca SyncVet. El control de una imagen pasa a código común (`src/lib/files/image.ts`, con el límite como parámetro; la foto sigue con 5 MB). Sin paquetes nuevos y sin cambios en la API | Diseño (R-074) |
| El menú en pantallas chicas | Por debajo de 992 px, plegado detrás del botón "Menú" (`src/app/MainMenu.tsx`, con `useMediaQuery`), sin el JS de Tabler: `aria-expanded` y `aria-controls`, Escape lo cierra con el foco de vuelta en el botón, se cierra al navegar o al cambiar el ancho, y al plegarse con el foco en un ítem el foco pasa al botón | Usuario (desvío 10 de la 2.4, R-080); el cierre al navegar y el foco, de la revisión |
| Desvíos | Los 10, aceptados: entre ellos, la pantalla solo para el administrador (como el diseño; los otros roles ven el logo en la barra), decidido al empezar la entrega; el menú plegado por debajo de 992 px y no de 768 px; con `has_logo` y el 404 al verlo, el lugar vacío con "Quitar el logo"; la marca SyncVet mientras baja el logo; y el ejemplo de RUC de los tests, el de la demo ("99999901-0") | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `11dabf4` (diff con `37ac3cf`), corregido en `26e4339`: el menú plegado quedaba abierto al navegar sin elegir un ítem y al volver a achicar la pantalla, y con el foco en un ítem al plegarse quedaba en un elemento oculto (2 tests nuevos); el test de reemplazar el logo no detectaba que se viera el viejo (ahora retiene la descarga nueva). El test de alta de `BranchPages.test.tsx` que falló una vez (desvío 9) esperaba un texto que el formulario del alta también tiene: ahora espera la URL y el encabezado del detalle (5 corridas seguidas del archivo, en verde) | Revisión |
| Mutaciones de la revisión | 3: el sitio vacío mandado como `''` (detectada); reemplazar el logo con `invalidateQueries` (sobrevivió; detectada después de reforzar el test); el límite justo rechazado en `image.ts` (detectada, en el logo y en la foto) | Revisión |
| Validación | El usuario aceptó los desvíos, pidió la revisión y el cierre. Claude Code: 5 de 5 en Edge sin ventana contra `syncvet.test` (un logo ancho y uno alto en la barra sin deformarse, el menú a 375 y 768 px sin desplazamiento horizontal; la demo como estaba); Playwright, `e2e/configuracion-clinica.spec.ts`, 1 de 1 (28 casos en total), en la implementación y después de la corrección del menú. Corrida del cierre, el 2026-11-17, sobre el código de `26e4339`, desde `frontend/`: `npm test`, 1251 tests en 60 archivos, en verde; `typecheck`, `lint` y `build` sin errores ni avisos. Desde `backend/`, `FictitiousDocumentsTest`, 3 de 3 | Usuario |
| Siguiente | La entrega 2.6 del frontend (auditoría), en `feature/front-2-6-auditoria`, que se crea desde el commit de este cierre; sin código hasta la autorización del usuario. Después, el cierre de la F2 | Diseño (R-074) |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP, contrato de respuesta y colecciones.

# R-082 Cierre de la entrega 2.6 del frontend (auditoría) (2026-11-17)

Séptima y última entrega de pantallas de la F2 del frontend (R-074), en `feature/front-2-6-auditoria` (desde
`feature/front-2-5-configuracion`, en el cierre de la 2.5, `1af859b`), sin integrar a `main`: se integra al cerrar la F2. El detalle,
en su [resultado](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-26).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | La tarjeta "Auditoría" en `/configuracion`, `/configuracion/auditoria` (`audit-logs.index`) y `/configuracion/auditoria/$id` (`audit-logs.show`), solo para el administrador y de solo lectura. El listado: fecha y hora en Asunción, el usuario y el registro como enlaces que filtran, el evento y la IP; los filtros en la URL (`?desde=&hasta=&usuario=&evento=&entidad=&registro=&buscar=&pagina=`), validados con todas sus claves. El detalle: la fecha con segundos (`formatDateTimeSeconds`), los datos, el navegador y los cambios antes y después, con el campo con el nombre de la API y cada valor como texto | Diseño (R-074) |
| Fechas | En la URL, la hora de pared de Asunción sin zona; a la API, en UTC con `TZDate` (nunca la zona de la PC): la fecha sola como el día completo ("hasta", el comienzo del día siguiente menos un segundo, también en los días de 23 y 25 horas) y "hasta" con hora con los segundos en 59 (pregunta 10). Las fechas y el n.º de registro se aplican con "Filtrar", con las reglas y los mensajes de `IndexAuditLogRequest`, sin cambiar la URL ni pedir nada con un error | Diseño (R-074); la fecha sola como dos instantes en UTC y no tal cual, el usuario al empezar la entrega (desvíos 1 y 2) |
| Lo que no se muestra | `maskAuditValues` (pregunta 12), además de la API: `********` en una clave con `password` o `token` (cualquier nivel, sin distinguir mayúsculas) y `"(archivo)"` en una clave `*_path` o un texto `clinics/{id}/…`; `null` queda `null` | Diseño (R-074); el alcance de las claves, desvío 6 |
| `entidad` | El tipo del registro va en la URL como `entidad` y no como `tipo`, que ya es de `/clientes`: el router junta las búsquedas de todas las rutas en los enlaces sin `from` | Desvío 3, aceptado |
| Desvíos | Los 10, aceptados: entre ellos, las fechas en UTC como dos instantes; `entidad`; "Filtrar" para las fechas y el registro; "hasta" anterior a "desde" en la URL descartado; "Quitar los filtros" y los usuarios inactivos marcados; Playwright sin el caso del alta de un usuario (lo cubren el test con MSW y el control 3 en Edge) | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `120739f` (diff con `1af859b`), corregido en `9a17e41`: sin cambios de comportamiento. El texto de ejemplo de la búsqueda se cortaba a 1280 px (el campo deja 147 px): quedó "Nombre o email"; a 375 px, "Filtrar" quedaba alineado con la etiqueta del n.º de registro y no con el campo (el ajuste valía solo desde 768 px) | Revisión |
| Mutaciones | De la implementación, 4 de 4 ("hasta" sin :59; la zona de la PC en lugar de `TZDate`; `maskAuditValues` sin recorrer lo anidado; el detalle sin `maskAuditValues`); de la revisión, 3 de 3 (las claves sensibles distinguiendo mayúsculas; `registro` sin `entidad` aceptado en la URL; "hasta" con fecha sola como las 23:59:59 de ese día, que falla en el día de 25 horas) | Implementación y revisión |
| Validación | El usuario aceptó los desvíos, pidió la revisión y el cierre. Claude Code: 9 de 9 en Edge sin ventana contra `syncvet.test` en la implementación y 3 controles en la revisión, de solo lectura; Playwright, `e2e/auditoria.spec.ts`, 1 de 1 (29 casos en total). Corrida del cierre, sobre `9a17e41`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1331 tests en 64 archivos, en verde; `FictitiousDocumentsTest`, 3 de 3 | Usuario y Claude Code |
| Siguiente | El cierre de la F2 del frontend, con su criterio de cierre (Playwright completo, la suite del backend en MySQL y en serie, Newman de todas las colecciones) y, después, la integración a `main`; sin empezar hasta la autorización del usuario | Diseño (R-074) |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP, contrato de respuesta y colecciones.

# R-083 Cierre de la F2 del frontend y merge a `main` (2026-11-17)

Cierre de la F2 del frontend (datos maestros), junto con la entrega de soporte del backend (front-02), con las ocho entregas
cerradas (R-075 a R-082). El detalle, en el [cierre de la F2](../../03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#cierre-de-la-f2).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Entregas | 2.1 (R-075), 2.2a (R-076), 2.2b (R-077), front-02 (R-078), 2.3 (R-079), 2.4 (R-080), 2.5 (R-081) y 2.6 (R-082): COMPLETADO, cada una con su revisión, sus correcciones y su validación; las ramas de la cadena, en GitHub | Cierre |
| F2 | COMPLETADO: el criterio de cierre se cumple, punto por punto en el cierre de la F2 (entregas cerradas; Vitest, Playwright, `typecheck`, `lint` y `build` en verde; la suite del backend en SQLite y en MySQL, en serie, por front-02; Newman de todas las colecciones sin fallas) | Usuario |
| Regresión del backend | Desde `backend/`, en serie, sobre `bbe6e20`, sin cambios de código: SQLite, 1467 casos (16 omitidos, solo de MySQL), 14303 aserciones; MySQL, 1467 casos sin omitidos, 14389 aserciones. Frente a la F1, 4 casos más (front-02). 78 rutas, 135 permisos y 36 migraciones, sin cambios. `composer audit`, sin avisos | Cierre |
| Regresión del frontend | Desde `frontend/`: `npm test`, 1331 tests en 64 archivos; `typecheck`, `lint` y `build` sin errores ni avisos; `FictitiousDocumentsTest`, 3 de 3; `npm run test:e2e`, 26 de 29 en la primera corrida y 29 de 29 después de ajustar el test (desvío 1). `npm audit`: solo el aviso de `minimatch` de la deuda 19 (9 paquetes) | Cierre |
| Desvíos del cierre | 1: `e2e/permisos.spec.ts` esperaba el menú sin "Servicios" y "Veterinarios" (la 2.3 y la 2.4 solo corrieron su spec, como en el cierre de la F1); se suman, solo en el test. 2: dos intentos fallidos del script de Newman (`base_url` sin `/api`, con todos los pedidos en 404 de ruta, y la carpeta de salida relativa) antes de la vuelta registrada | Cierre |
| Postman | Newman, las 14 colecciones de `docs/05-pruebas/postman` (R-057), servidor en el puerto 8001: 487 pedidos y 1082 aserciones, sin fallas | Cierre |
| Demo | El token que dejó abierto la revisión de la 2.6 (id 604, `admin-a`), borrado antes de la regresión, y los 36 que abrió Newman (ids 605 a 651), revocados después; `admin-a`, `recepcion-a` y `vet-a`, sin cambios | Usuario (pedido) |
| Deudas | La F2 no abrió ni resolvió ninguna. Actualizadas: 19 (los mismos 9 avisos) y 21 (el trait `InspectsQueries` de front-02 ya tiene `countQueries`). Abiertas: 2, 3, 5, 6, 15, 17 y 21 (backend); 19 y 20 (frontend) | Cierre |
| Merge a `main` | Autorizado por el usuario el 2026-11-17: merge `--no-ff` desde `feature/front-2-6-auditoria`, que contiene `docs/front-f2-diseno` y las ramas de la 2.1 a la 2.5 y de front-02. `main` es ancestro de la rama, así que el árbol del merge es el de la rama ya probada. Las ramas se conservan. El hash del merge se informa al usuario y se anota en el handoff, en la rama de la F3 | Usuario |
| Siguiente | El análisis y el diseño de la F3 del frontend (agenda y turnos), sobre la API de la FASE 3, en `docs/front-f3-diseno` (desde `main` en el merge de la F2) | Usuario |

**Qué no cambió.** Código de la aplicación, rutas, permisos, `error_code`, contrato de respuesta y colecciones: el cierre documenta y
ajusta solo un test de Playwright.

# R-084 `e2e/permisos.spec.ts` en toda entrega que cambie el menú o los permisos de una ruta (2026-11-17)

En el cierre de la F2 (R-083, desvío 1), `e2e/permisos.spec.ts` falló porque esperaba el menú sin "Servicios" y "Veterinarios": la 2.3
y la 2.4 sumaron esos ítems, pero cada una corrió solo su propio spec, como manda el presupuesto por entrega. El error se vio recién en
la corrida completa del cierre.

| Tema | Decisión | Quién |
|---|---|---|
| Regla | Toda entrega del frontend que cambie el menú (`src/app/menu.ts`) o el permiso de una ruta (un `requirePermission`, o un permiso de `PERMISSIONS` que use una ruta) corre también `e2e/permisos.spec.ts`, además de su propio spec. No cuenta como corrida completa de Playwright: el resto sigue para el cierre de la fase | Usuario |
| Dónde | El [Presupuesto por entrega](../../../CLAUDE.md#presupuesto-por-entrega) del `CLAUDE.md` de la raíz y [`frontend/CLAUDE.md`](../../../frontend/CLAUDE.md) §8 (Velocidad) | Usuario |
| Primera aplicación | La F3 (agenda y turnos): sus entregas que suman "Agenda" y "Turnos" al menú lo anotan en sus casos de Playwright ([`f3-agenda.md`](../../03-plan-desarrollo/fases/frontend/f3-agenda.md)) | Diseño |

**Qué no cambió.** Código, tests, rutas, permisos y colecciones: solo cambia el proceso.

# R-085 Diseño de la F3 del frontend aprobado (2026-11-17)

El usuario aprobó el diseño de la F3 del frontend, agenda y turnos ([`f3-agenda.md`](../../03-plan-desarrollo/fases/frontend/f3-agenda.md)),
en `docs/front-f3-diseno`, con las 15 preguntas respondidas: todas con la recomendación (a), cuatro con precisiones.

| Tema | Decisión | Quién |
|---|---|---|
| Volumen de la semana (1) | La entrega de soporte front-03: `GET appointments` acepta `per_page` hasta 500 (solo ese listado). Si `total` supera lo traído, la pantalla avisa que faltan turnos; nunca corta en silencio | Usuario |
| Sucursal (2), veterinario (3) y semana (4) | La sucursal actual de la barra; el veterinario ve los suyos y los de las agendas sin veterinario, con "Ver toda la sucursal"; la semana, una agenda por vez con el selector | Usuario |
| Grilla (5, 15) | Cancelados ocultos con "Mostrar los cancelados" y ausentes atenuados; sin arrastrar en la F3 | Usuario |
| El turno (6, 7, 8, 10) | Sin alta rápida (la primera vez, con nombre y teléfono de contacto); los horarios libres como botones más "Otra hora"; el sobreturno solo después del 409; el alta y el detalle, en páginas | Usuario |
| Actualización (9) | Cada 60 s, en pausa con un formulario o un diálogo abierto y con la pestaña oculta | Usuario |
| Alcance (11, 12) | La cola del día en la F3, como última entrega (3.7); las agendas se administran desde el frontend (3.1a y 3.1b) | Usuario |
| Playwright (13) | Un ayudante de `e2e/support` crea por la API, en `syncvet_e2e`, las agendas, los servicios y los turnos que cada spec necesita, y los limpia; la demo no se toca | Usuario |
| Acciones (14) | Ocultas o deshabilitadas con el motivo, y además el 403 y el 422 de la API tratados en el diálogo | Usuario |
| Documentos (D1 a D5) | Manda el código. D1 (el cliente en el orden de bloqueo) y D5 (fichar, solo los turnos propios del veterinario, 403 `APPOINTMENT_NOT_YOURS`), como precisiones en el documento de la FASE 3 del backend; D2 (el veterinario o el servicio inactivo y el día pasado, 422 `VALIDATION_ERROR` en su campo), D3 (solo la cola usa la sucursal actual en la API) y D4 (la clave de la cola con sus filtros), en `frontend/CLAUDE.md` §5 y §6 | Usuario |
| Siguiente | La entrega 3.1a (agendas), en `feature/front-3-1a-agendas` desde `docs/front-f3-diseno` | Usuario |

**Qué no cambió.** Código, tests, rutas, permisos, `error_code` y colecciones: solo documentación.

# R-086 Cierre de la entrega 3.1a del frontend (agendas) (2026-11-18)

Primera entrega de pantallas de la F3 del frontend (R-085), en `feature/front-3-1a-agendas` (desde `docs/front-f3-diseno`, en
`df2588a`), sin integrar a `main`: se integra al cerrar la F3. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f3-agenda.md#entrega-31a).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | La tarjeta "Agendas" en `/configuracion` y `/configuracion/agendas` (`schedules.update`): el listado (búsqueda, sucursal e "Incluir las inactivas", en la URL), el detalle con el horario semanal de lunes a domingo, el alta (`schedules.store`) y la edición con el horario, inactivar o activar y la baja (`schedules.destroy`; con `SCHEDULE_IN_USE`, el mensaje y "Inactivar"), solo para el administrador. El horario se manda entero (`hours: []` lo vacía), con las reglas de `ScheduleMessages::validateHours` repetidas en el navegador. `PERMISSIONS` suma `schedules.update`, `.store` y `.destroy` | Diseño (R-085) |
| Playwright | El ayudante `e2e/support/api.ts` (sesión por la API en `syncvet_e2e` y limpieza de las agendas de un spec, pregunta 13) y `e2e/agendas.spec.ts`; `e2e/permisos.spec.ts` suma la tarjeta y el recepcionista y el veterinario en `/configuracion/agendas` (R-084) | Diseño (R-084, R-085) |
| Desvíos | Los 9, aceptados; ninguno cambia el comportamiento del diseño. Entre ellos: el error de un día del horario en el tramo que lo provoca (1); la casilla "Con color en la agenda" (2); los veterinarios y los servicios activos de un solo pedido, filtrados en el navegador por la sucursal, que suelta el veterinario que no trabaja en ella (3); "Copiar el lunes" deshabilitado con el lunes vacío (4); el test de la mutación 4 reforzado para mirar el cuerpo del `PATCH` (5); el foco de "Sumar un tramo" y "(actual)" con la lista cargada, corregidos en la implementación (6 y 7) | Usuario |
| Laragon | Durante los controles en Edge de la implementación, el PHP de Laragon dejó de responder (`syncvet.test` con 502, sin `php-cgi`): no se tocó Laragon y los controles siguieron con un `php artisan serve` propio en `:8020` sobre la base de la demo, detenido por su PID (desvío 8). El usuario reinició Laragon y la revisión repitió los controles contra `syncvet.test` (8 de 8) | Usuario y Claude Code |
| Revisión | Con el presupuesto por entrega, sobre `549f1c6` (diff con `df2588a`), corregido en `f0f463a`: sin cambios de comportamiento. El test de permisos de `SchedulePages.test.tsx` no recorría `/configuracion/agendas/$id/editar`: se sumó (el recepcionista y el veterinario). La captura del formulario a 375 px se rehízo sin "(actual)" | Revisión |
| Mutaciones | De la implementación, 4 de 4 (el horario vacío sin la clave `hours`; la superposición sin controlar; 23:55 rechazado; el veterinario sin soltar al cambiar de sucursal); de la revisión, 3 de 3 (tocarse contado como superposición; "Inactivar" ofrecido en una agenda ya inactiva; el color mandado sin la casilla) | Implementación y revisión |
| Validación | El usuario aceptó los desvíos, pidió la revisión y el cierre. Claude Code: 8 de 8 en Edge sin ventana en la implementación y 8 de 8 contra `syncvet.test` en la revisión; Playwright, `e2e/agendas.spec.ts` 1 de 1 y `e2e/permisos.spec.ts` 7 de 7. Corrida de la revisión, sobre `f0f463a`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1403 tests en 67 archivos, en verde (la primera vez, 1 falla intermitente en `ClientDetailPage.test.tsx`, ajena a la entrega; repetida la suite completa); `FictitiousDocumentsTest`, 3 de 3 | Usuario y Claude Code |
| Siguiente | La entrega 3.1b (bloqueos de una agenda), en `feature/front-3-1b-bloqueos`, creada desde el cierre de la 3.1a | Diseño (R-085) |

**Qué no cambió.** Backend, rutas y permisos de la API, `error_code`, códigos HTTP, contrato de respuesta, colecciones y paquetes.

# R-087 Cierre de la entrega 3.1b del frontend (bloqueos de una agenda) (2026-11-18)

Segunda entrega de pantallas de la F3 del frontend (R-085), en `feature/front-3-1b-bloqueos` (desde `913f1d3`, el cierre de la
3.1a), sin integrar a `main`: se integra al cerrar la F3. El detalle, en su
[resultado](../../03-plan-desarrollo/fases/frontend/f3-agenda.md#entrega-31b).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | La tarjeta "Bloqueos" en el detalle de la agenda (`schedule-blocks.index`, paginada): los próximos con el período en Asunción y el motivo; "Nuevo bloqueo" (`schedule-blocks.store`) con desde y hasta, "Todo el día" (hasta las 00:00 del día siguiente a la fecha hasta) y el motivo, mandados con `toApiDateTime` (`-03:00`) y con las reglas de `StoreScheduleBlockRequest` repetidas en el navegador (`features/schedules/blocks.ts`); "Eliminar" (`schedule-blocks.destroy`). `PERMISSIONS` suma las tres claves. El ayudante de Playwright `createScheduleFor` y un caso nuevo en `e2e/agendas.spec.ts` | Diseño (R-085) |
| Turnos adentro | Como la API (pregunta 22 del backend): el bloqueo se crea igual; antes de confirmar, el diálogo avisa, y con el 201 muestra los turnos de `affected_appointments` (el enlace a cada uno, en la 3.4). La grilla (3.2) y la lista (3.3) los marcan "Dentro de un bloqueo". La consulta previa de los que quedarían adentro, mejora del backend no urgente (deuda 25) | Usuario |
| Desvíos | Los 11, aceptados; ninguno cambia el comportamiento del diseño. Entre ellos: los afectados después del 201 (1); la validación del navegador como función pura, sin Zod (3); el 409 `CONFLICT` genérico y los 404 del alta y de la baja (5); los 422 de la API a la fecha y los del navegador a la hora o la fecha (7); "Todo el día" en el día del cambio de horario de 2023 desde la 01:00 (8) | Usuario |
| Revisión | Con el presupuesto por entrega, sobre `2c30241` (diff con `913f1d3`), corregido en `cbb4663`. Hallazgo 1 (menor): al eliminar el único bloqueo de una página que no es la primera, la tarjeta decía que no había bloqueos; ahora vuelve a la anterior y una página fuera de rango muestra "Esta página no existe." con "Ir a la página 1" (2 tests). Hallazgo 2, la causa del test frágil de la 3.1a (desvío 10): `DELETE /api/schedules/1` tardó 10,8 s en el backend de Playwright y el aviso llegó después del límite de 10 s; los avisos y la marca "Inactivada" de `agendas.spec.ts` esperan hasta 30 s (`SAVED`) | Revisión |
| Mutaciones | De la implementación, 4 de 4 (la fecha con la zona de la PC; "Todo el día" sin el día siguiente; los afectados sin mostrar; 366 días rechazado en el borde); de la revisión, 4 de 4 (el motivo contado con `.length`; "Nuevo bloqueo" sin su permiso; `ends_at` del 422 a "Fecha desde"; el hallazgo 1 sin volver a la página anterior) | Implementación y revisión |
| Validación | El usuario aceptó los desvíos, pidió la revisión y el cierre. Claude Code: 8 de 8 en Edge sin ventana contra `syncvet.test` en la implementación; Playwright, `e2e/agendas.spec.ts` 2 de 2 en 3 corridas seguidas (revisión) y `e2e/permisos.spec.ts` 7 de 7 (implementación). Corrida de la revisión, sobre el código de `cbb4663`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1439 tests en 69 archivos, en verde, a la primera; `FictitiousDocumentsTest`, 3 de 3 | Usuario y Claude Code |
| Siguiente | La entrega de soporte del backend front-03 (`per_page` hasta 500 en `GET appointments`), en `feature/front-03-turnos-por-pagina` desde la 3.1b; después, la 3.2 | Diseño (R-085) |

**Qué no cambió.** Backend (solo la fila 25 de la tabla de deuda de `backend/CLAUDE.md`), rutas y permisos de la API, `error_code`,
códigos HTTP, contrato de respuesta, el menú, el permiso de las rutas, colecciones y paquetes.

# R-088 Cierre de la entrega front-03 del backend (más turnos por página en el listado) (2026-11-18)

Entrega de soporte del backend pedida por la F3 del frontend (pregunta 1 de su diseño, R-085), en
`feature/front-03-turnos-por-pagina` (desde `c71e71d`, el cierre de la 3.1b), sin integrar a `main`: se integra al cerrar la F3. El
detalle, en su [resultado](../../03-plan-desarrollo/fases/frontend/f3-agenda.md#entrega-front-03).

| Tema | Decisión o resultado | Quién |
|---|---|---|
| Alcance | `GET appointments` acepta `per_page` de 1 a 500 (`IndexAppointmentRequest::MAX_PER_PAGE`), para que la semana de una sucursal entre en un pedido; fuera de rango, un decimal o un texto → 422 en `per_page` con un solo mensaje, "Pedí de 1 a 500 turnos por página." Los demás listados siguen hasta 100 (`ListFilters` sin cambios) y el valor por defecto sigue en 10. `meta.pagination.total` cuenta todos los del filtro. Sin N+1: el `with` del listado ya traía las relaciones del Resource (desde la 3.2a). Sin rutas, permisos, migraciones ni `error_code` nuevos (78, 135, 36) | Diseño (R-085) |
| Índices | EXPLAIN en MySQL 8.4 (una base temporal con 60.000 turnos, borrada después, y la demo): la semana usa `appointments_branch_id_starts_at_index` o, con `schedule_id`, `appointments_schedule_id_starts_at_ends_at_index`; no falta ningún índice, sin migración | Implementación |
| El pedido lento de la 3.1b | `DELETE /api/schedules/{id}` tarda de 0,1 a 0,4 s fuera de Playwright: los 10,8 s de la 3.1b son del entorno (`php artisan serve` de un solo proceso en Windows, con Xdebug cargado, y la PC cargada), no del backend; sin corrección en el backend. Los 30 s de `SAVED` de la 3.1b quedan | Implementación; el usuario, los 30 s |
| Playwright sin Xdebug | `XDEBUG_MODE: 'off'` en el `env` del `webServer` del backend (`frontend/playwright.config.ts`; con `--no-reload`, `php artisan serve` pasa el entorno a `php -S`). Medido con `e2e/agendas.spec.ts` y `e2e/permisos.spec.ts`, dos corridas de cada lado: 100 s antes y 99,5 s después en total; `agendas.spec.ts`, de 37,6 a 33,8 s; `permisos.spec.ts`, sin cambio. Regla en `frontend/CLAUDE.md` §8 | Usuario |
| Desvíos | Los 5, aceptados: el mensaje propio de `per_page` (uno solo para `integer`, `min` y `max`); sin cambios en `AppointmentController`; el EXPLAIN en una base temporal además de la demo; el pedido lento sin corregir (la causa es el entorno); las mediciones contra la demo | Usuario: aceptados |
| Revisión | Con el presupuesto por entrega, sobre `60b3615` (diff con `c71e71d`), en `4a05e9c`: sin otra vía para pasar más de 500 (también probados `600.5`, `-0.5`, `500.0`, `1e2`, `5e2` y `0500`, con un solo mensaje), `ListFilters` y los otros 10 listados sin cambios, el conteo de consultas que falla de verdad con el N+1 y los pedidos "22a" a "22c" que se pueden repetir. Hallazgo 1 (menor, corregido): una línea larga de `backend/CLAUDE.md` §6.10 | Revisión |
| Mutaciones | De la implementación, 3 de 3 (el tope en 501; `ListFilters` con 500 para todos; sin `veterinarian.user` ni `patient` en el `with`); de la revisión, 2 de 2 (`numeric` en lugar de `integer`; sin `client` en el `with`, de 9 a 208 consultas) | Implementación y revisión |
| Validación | El usuario aceptó los desvíos, decidió Playwright sin Xdebug y pidió la revisión y el cierre. Backend, sobre el código de `60b3615` (después, solo cambió su `CLAUDE.md`): `composer test` en serie, 1485 casos, 1469 pasan, 16 omitidos (solo MySQL), 14416 aserciones; Newman de turnos, 61 pedidos y 113 aserciones, sin fallas; en la revisión, los tests relacionados, 54 de 54. Frontend, sobre `4a05e9c`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1439 tests en 69 archivos, en verde; Playwright, `e2e/agendas.spec.ts` y `e2e/permisos.spec.ts`, 9 de 9 en cuatro corridas | Usuario y Claude Code |
| Siguiente | La entrega 3.2 del frontend (la agenda del día), en `feature/front-3-2-agenda-dia`, creada desde el cierre de front-03 y subida; sin código hasta la autorización del usuario | Diseño (R-085) |

**Qué no cambió.** Rutas, permisos, migraciones, `error_code`, códigos HTTP, el contrato de respuesta, los demás listados, el
frontend (salvo `playwright.config.ts`) y los paquetes.
