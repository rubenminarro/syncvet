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

# R-011 Segunda revisión de la entrega 0.4 de la FASE 0 (2026-11-05)

Correcciones de la segunda revisión, antes del cierre de la entrega. Completa R-010.

| Tema | Decisión | Quién |
|---|---|---|
| Have I Been Pwned en desarrollo | El `php.ini` de Laragon apuntaba `curl.cainfo` a un `cacert.pem` inexistente (`D:\Projects\Laragon-installer\…`): toda consulta fallaba con cURL error 77 y la regla de contraseñas aceptaba cualquiera, también `Password123!`. `curl.cainfo` y `openssl.cafile` pasan a `C:\laragon\etc\ssl\cacert.pem`. Es configuración de la máquina, fuera del repositorio; [`deployment.md`](../development/deployment.md) pide comprobarlo en producción | Usuario |
| Aviso de Have I Been Pwned | El aviso del log lleva el código de error de cURL (`curl_error`) o el estado HTTP (`status`), además de la clase de la excepción. Nunca el mensaje: termina con la URL y el prefijo del hash | Usuario |
| Aviso en la consola | Si la contraseña escrita se aceptó sin consultar Have I Been Pwned, `clinic:create` (con interacción) y el `DemoSeeder` lo avisan en la consola (`UserPassword::leakCheckSkipped`). La contraseña generada de `clinic:create` no se consulta: no hay aviso | Usuario |
| Documentos de ejemplo | Todos ficticios: los RUC de clínicas y de clientes empresa son `999999xx` con su dígito verificador (Granja San Jorge S.R.L. pasa a `99999903-6`) y las cédulas de los clientes persona, `99xxxxxx` (`99100001`, `99100002`, `99200001`, `99200002`). El test del `DemoSeeder` exige esos prefijos | Usuario |
| Log de los tests | `phpunit.xml` fija `LOG_CHANNEL=null`: los tests no escriben en `storage/logs/laravel.log`. Los que revisan lo registrado escuchan `MessageLogged` o usan `Log::spy()`; el que necesite leer un log, configura su propio canal | Usuario |

# R-012 Entrega 0.4 de la FASE 0: alta de clínicas y datos de demostración (2026-11-05)

Cierre de la entrega 0.4: `php artisan clinic:create` y el `DemoSeeder`, con la regla de contraseñas (R-009) y las correcciones de
sus dos revisiones (R-010 y R-011). Commits `35d5089`, `d135459` y `e7047b2`, en `feature/fase-00-04-clinic-create`. El detalle está
en el [resultado de la entrega](../fases/fase-00-puesta-en-marcha.md#entrega-04).

| Tema | Decisión | Quién |
|---|---|---|
| Validación | Suite en verde sobre `e7047b2` en SQLite (222 casos, 3208 aserciones, 1 omitido) y en MySQL 8.4.3 (`syncvet_test_mysql`, 3222 aserciones). Validación manual del usuario sobre la base `syncvet`: `DEMO_PASSWORD`, `migrate:fresh --seed`, `DemoSeeder` sin avisos, `clinic:create` interactivo y la prueba con Postman | Usuario, el 2026-11-05 |
| Prueba con Postman de la 0.3 | Hecha, con alcance: login con un usuario de cada clínica, `me`, 404 al pedir un paciente de la otra clínica, 429 al sexto intento fallido, logout y 401 después. Las demás filas de la sección de la FASE 0 de la [guía](../testing/guia-postman.md) no se recorrieron a mano: las respaldan los tests de `tests/Feature/Auth` y `tests/Feature/Tenancy` | Usuario |
| Bases de desarrollo anteriores a R-011 | El `DemoSeeder` falla sobre ellas (busca los clientes por su documento, que cambió, y choca con el email único). Se documenta en el resultado y en la guía (recrear la base); no se abre una deuda | Usuario |
| Instalación | [`setup.md`](../development/setup.md) explica cómo apuntar `curl.cainfo` y `openssl.cafile` del `php.ini` de Laragon al `cacert.pem` que existe, y cómo comprobarlo | Usuario |
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
[resultado de la entrega](../fases/fase-00-puesta-en-marcha.md#entrega-05) y en el
[cierre de la fase](../fases/fase-00-puesta-en-marcha.md#cierre-de-la-fase-0).

| Tema | Decisión | Quién |
|---|---|---|
| Validación | Suite en verde sobre `f87dbdd` en SQLite (242 casos, 3910 aserciones, 2 omitidos que son solo de MySQL) y en MySQL 8.4.3 (`syncvet_test_mysql`, 3926 aserciones); el usuario la corrió en su máquina | Usuario, el 2026-11-05 |
| FASE 0 | COMPLETADO: las cinco entregas cerradas y el criterio de cierre de la fase cumplido, punto por punto en el cierre de la fase. Sin merge a `main` | Usuario |
| Permiso de cada ruta | Regla de `CLAUDE.md` §3: el permiso de cada ruta es su nombre (`recurso.acción`; en las anidadas, los dos últimos tramos), cada ruta tiene un nombre propio, y las excepciones van con su motivo. La exige `ClinicRolesTest` desde la 0.5 | Usuario |
| Suite en MySQL | Regla de `CLAUDE.md` §3: la suite completa corre en SQLite durante el trabajo, y en MySQL (`syncvet_test_mysql`) en la revisión, antes de cada cierre y siempre que se toquen migraciones, búsquedas de texto, concurrencia o autenticación. Reemplaza "probar cada migración nueva también en MySQL" | Usuario |
| Deuda 7 | Resuelta en la 0.5, antes de la FASE 1 prevista: cédulas ficticias en los tests y `FictitiousDocumentsTest` sobre toda secuencia de 7 a 9 dígitos | Usuario |
| Deudas abiertas | 1, 2, 3, 5, 6 y 8 ([`deuda-tecnica.md`](../roadmap/deuda-tecnica.md)) | Implementación |
| Siguiente | FASE 1, clientes y pacientes: empieza por el análisis y el diseño (pasos 1 y 2), con autorización del usuario | Usuario |
| `CLAUDE.md` | §1 con el estado de la FASE 0 y los tests; §3 con las dos reglas nuevas (además de la de datos de ejemplo de R-013); §7 sin la deuda 7 y con la 8 | Implementación |

**Qué no cambió.** Contrato de respuesta, `error_code`, códigos HTTP y rutas existentes; los 119 permisos y los tres roles; las
reglas de negocio de clientes y pacientes.

# R-015 Respuestas de negocio y alcance de la FASE 1 (2026-11-05)

Respuestas del usuario a las preguntas de negocio de la FASE 1 (las tres del alcance inicial y las cinco que agregó el análisis), y
el alcance que suma a la fase. El diseño que las aplica está en [`fase-01-clientes-y-pacientes.md`](../fases/fase-01-clientes-y-pacientes.md),
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

El usuario aprueba el diseño de la FASE 1 ([`fase-01-clientes-y-pacientes.md`](../fases/fase-01-clientes-y-pacientes.md)) con cinco
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
[resultado de la entrega](../fases/fase-01-clientes-y-pacientes.md#entrega-11).

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
[resultado de la entrega](../fases/fase-01-clientes-y-pacientes.md#entrega-12).

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
detalle está en el [resultado de la entrega](../fases/fase-01-clientes-y-pacientes.md#entrega-13).

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
