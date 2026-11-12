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
| Entorno | Node 26 LTS en todas las PC, con `frontend/.nvmrc` y `engines`; npm 11 | Node 24 LTS (más viejo; 26 ya es LTS); pnpm (una herramienta más que instalar en cada PC) |
| Build | Vite 8 con `@vitejs/plugin-react` | Next.js (servidor y SSR que una aplicación interna detrás de un login no necesita) |
| Lenguaje | TypeScript 6 estricto | TypeScript 7 (el compilador nuevo en Go, todavía sin el ecosistema de plugins asentado) |
| Rutas | TanStack Router, URL en castellano | React Router (menos tipado en los parámetros y la búsqueda) |
| Datos del servidor | TanStack Query, reintentos solo ante fallas de red o 5xx | SWR (menos control de mutaciones e invalidación); estado propio con `useEffect` |
| Cliente HTTP | Propio sobre `fetch` | Axios (una dependencia menos; `fetch` alcanza para el contrato de la API) |
| Formularios | React Hook Form + Zod; el 422 con `setError` | Formik + Yup (más lento y menos mantenido); formularios a mano |
| Fechas | date-fns 4 + `@date-fns/tz` (`TZDate`, `America/Asuncion`) | Day.js y Luxon (otra forma de manejar zonas); solo `Intl` (no alcanza para calcular en una zona fija) |
| Interfaz | Tabler (`@tabler/core` y `@tabler/icons-react`) con componentes propios, sin el JS de Tabler | El JS de Tabler y Bootstrap (manipula el DOM por fuera de React); `tabler-react` (archivado); Tailwind (prohibido junto a Tabler) |
| Tipos de la API | Escritos a mano | Scramble para generar OpenAPI y los tipos (descartado por ahora) |
| Tests | Vitest + Testing Library + MSW + jsdom; Playwright contra `syncvet_e2e` con el `DemoSeeder` | Jest (configuración aparte de Vite); happy-dom (menos fiel); Cypress (más pesado, sin varias pestañas) |
| Calidad | ESLint + Prettier | Biome (todavía sin las reglas de hooks de React que da ESLint) |

**Qué no cambió.** Código, rutas, permisos, `error_code`, contrato de respuesta y colecciones: solo documentación.
