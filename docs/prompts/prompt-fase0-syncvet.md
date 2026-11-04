# SYNCVET — FASE 0: ANÁLISIS FUNCIONAL Y PLANIFICACIÓN

> **Material histórico** ([registro R-004](../decisions/registro.md)). Es el prompt del trabajo de documentación previo al paquete
> base, no el de la FASE 0 actual ([`fase-00-puesta-en-marcha.md`](../fases/fase-00-puesta-en-marcha.md)). Donde contradiga a
> [`CLAUDE.md`](../../CLAUDE.md) o a la decisión D1, mandan estos.

## Contexto

SyncVet es un nuevo software profesional de gestión integral para veterinarias, clínicas y hospitales veterinarios.

- Modalidad principal: SaaS multi-tenant.
- Modalidad secundaria: instalación independiente para clientes que no quieran usar el SaaS, sin duplicar el núcleo del producto.
- Mercado inicial: Paraguay. Interfaz en español (voseo). Moneda inicial: guaraníes (PYG, sin decimales).
- Normativa a considerar: facturación electrónica SIFEN (DNIT) y Ley 7593/2025 de Protección de Datos Personales (vigencia plena en noviembre de 2027).
- Expansión futura a otros países y multi-moneda: prever en el diseño, no desarrollar todavía.

## Idioma

- **Código en inglés:** nombres de clases, métodos, variables, tablas, columnas, rutas de API, archivos y carpetas, comentarios en el código, `error_code` y enums.
- **Mensajes de commit en español**, describiendo el porqué (igual que en SyncTaller).
- **Conversación en español:** tus respuestas, preguntas y resúmenes conmigo.
- **Documentación en español** (contenido de `docs/`), con nombres de archivo en inglés.
- **Textos de la interfaz en español** (voseo), siempre mediante archivos de traducción, nunca escritos directamente en el código.
- Glosario de dominio: mantené en `docs/glossary.md` la equivalencia de términos (por ejemplo, propietario = `owner`, mascota/paciente = `patient`, internación = `hospitalization`, caja = `cash_register`) y usalo siempre igual.

## Referencias

Están en `docs/references/`: un resumen y comparativa de funciones de PetSys, Asys Next, VetPlus (Datasoft) y Velary, y las imágenes de sus anuncios. Leé todo el contenido de esa carpeta.

Sitio de referencia principal: https://www.petsys.com.py/#funciones

Usalas para identificar funcionalidades y buenas prácticas. No copies su interfaz ni su implementación: el objetivo es un producto propio, igual o superior.

## Proyecto de referencia técnica: SyncTaller

SyncTaller es un sistema mío para talleres mecánicos, en desarrollo avanzado, con el mismo stack. Sirve como **guía de forma de trabajo y de arquitectura**, no de dominio.

- Backend: https://github.com/rubenminarro/synctaller-backend (local: `../synctaller-backend`)
- Frontend: https://github.com/rubenminarro/synctaller-frontend (local: `../synctaller-frontend`)

Leé sus `CLAUDE.md`, `docs/README.md`, `docs/project/` (sobre todo `principios.md` y `architecture.md`), `docs/modules/infraestructura-saas.md`, `docs/spike-t0/informe.md`, `.claude/skills/` y, en el frontend, `src/components/ui`, `src/lib` y la estructura de `src/features/`.

**Qué tomar como guía** (en cada ADR, decí qué se adopta tal cual, qué se adapta y por qué):

- Metodología: inspeccionar antes de cambiar, cambio mínimo, decisiones de negocio consultadas, tests antes de dar algo por terminado.
- Git: nunca trabajar en `main`, una rama por tarea, commit y push solo cuando lo pido, el merge lo hago yo.
- Arquitectura backend: Controller (`HasMiddleware`) → Form Request → Service → Model → API Resource → `ApiResponse`; contrato de respuesta JSON único; excepciones de dominio con `error_code`; enums para estados; dinero con BCMath; documentos emitidos inmutables; códigos internos por serie; auditoría.
- Permisos `<recurso>.<acción>` con Spatie y un seeder único de roles y permisos.
- Frontend: Tabler oficial (`@tabler/core` + `@tabler/icons-react`), TanStack Query, Axios centralizado, componentes compartidos en `src/components/ui`, un módulo por carpeta en `src/features/`.
- Documentación: una fuente de verdad por tema, `handoff.md` como punto de continuidad, decisiones registradas, Skills `retomar`, `cerrar-entrega` y `validar-docs`.
- Multitenancy: la evaluación ya hecha de `spatie/laravel-multitenancy` con una base por taller (spike T0, decisiones TD1–TD11).
- Entorno Windows / Laragon (Nginx, no Apache) y sus advertencias.

**Qué NO tomar:**

- Reglas de negocio del taller (recepción de vehículos, OT, aseguradoras, siniestros, repartos): el dominio veterinario se analiza desde cero.
- Deudas técnicas y decisiones que SyncTaller tomó por su historia (por ejemplo, el renombre desde "Presupuestador" o tokens sin vencimiento) sin evaluar si aplican.

**Decisión pendiente a documentar:** si SyncVet y SyncTaller comparten un núcleo común (autenticación, permisos, tenancy, auditoría, facturación SIFEN) o son productos independientes que solo comparten convenciones.

## Reglas de esta etapa

1. NO programar, NO crear migraciones, NO instalar dependencias, NO modificar archivos de configuración.
2. Solo podés crear y editar archivos Markdown dentro de `docs/`, más el borrador de `CLAUDE.md` en la raíz (Paso 5).
3. No asumas reglas de negocio sin validar. Si necesitás un supuesto para avanzar, marcalo como **SUPUESTO — validar** y agregalo a `docs/pending-decisions.md`.
4. No tomes decisiones de arquitectura definitivas. Cada alternativa se documenta como ADR con estado "Propuesto" y queda pendiente de mi aprobación.
5. Mantené separadas las áreas Clínica, Operativa y Financiera.
6. Priorizá la modularidad, la mantenibilidad, las pruebas automatizadas y la simplicidad.
7. No cambies el roadmap aprobado sin justificarlo y pedir aprobación.
8. **Trabajá un paso por vez.** Al terminar cada paso, mostrame un resumen corto con las dudas que surgieron y esperá mi aprobación antes de seguir.

## Funcionalidades a estudiar

1. **Propietarios y mascotas:** propietarios, varias mascotas por propietario, especies y razas, identificación (chip, tatuaje), antecedentes, fotografías, responsables autorizados.
2. **Agenda y recepción:** agenda por profesional y por servicio, disponibilidad, reservas, confirmaciones, cancelaciones, recepción, cola de atención, urgencias sin turno.
3. **Gestión clínica:** historia clínica, anamnesis, consultas, diagnósticos, tratamientos, recetas, vacunación, desparasitación, control de peso, evoluciones, certificados.
4. **Gestión avanzada:** internaciones, signos vitales, medicación programada, cirugías, procedimientos, laboratorio, estudios, consentimientos informados.
5. **Servicios complementarios:** baño y peluquería.
6. **Administración:** servicios y precios, presupuestos, facturación (SIFEN), cobros, caja diaria por usuario, gastos, cuentas corrientes, honorarios profesionales.
7. **Inventario:** productos, medicamentos, proveedores, compras, depósitos, stock, lotes, vencimientos, movimientos, consumos clínicos.
8. **Comunicación:** WhatsApp, email, recordatorios, notificaciones, seguimiento de tratamientos, portal de propietarios (incluye seguimiento de internación).
9. **Gestión empresarial:** empresas, sucursales, usuarios, roles, permisos, reportes, auditoría, indicadores.
10. **SaaS:** plataforma central, tenants, suscripciones, planes, dominios (por ejemplo, subdominio por veterinaria), configuración por veterinaria, funciones habilitadas por plan, aislamiento de datos, instalación independiente.

## Tecnologías de referencia (a validar, no aprobadas)

- Backend: PHP 8.3+, Laravel 13, Laravel Sanctum, Spatie Laravel Permission, API REST documentada.
- Arquitectura backend: Controller → Form Request → Service → Model/Eloquent → Base de datos. La lógica de negocio vive en Services.
- Frontend: React 19 + TypeScript, Tabler UI, responsive (prioridad: escritorio y tablet).
- Base de datos: MySQL con migraciones de Laravel.

Puntos a evaluar especialmente:

- Tabler: el paquete `tabler-react` está archivado; SyncTaller usa `@tabler/core` sobre Bootstrap 5 y **prohíbe Tailwind**. La propuesta de stack habla de poder adoptar Tailwind más adelante: señalá esa contradicción y proponé una postura para que yo decida.
- MySQL no tiene Row Level Security: el aislamiento entre tenants depende de la aplicación o de usar una base por tenant (como evaluó SyncTaller).

## Trabajo solicitado (un paso por vez)

### Paso 1 — Análisis funcional → `docs/01-functional-analysis.md`
- Organizá todas las funcionalidades por módulo.
- Clasificá cada una como Imprescindible, Importante, Avanzada u Opcional, en una tabla: módulo · funcionalidad · prioridad · referencia que la tiene · comentario.
- Proponé funcionalidades adicionales con valor competitivo y explicá por qué.

### Paso 2 — Análisis de negocio → `docs/02-business-analysis.md`
- Tipos de establecimiento (consultorio, clínica, hospital, cadena con sucursales, peluquería).
- Tipos de usuario, roles y responsabilidades (como mínimo: recepción, veterinario, administración, dueño, propietario de la mascota, administrador de la plataforma SaaS).
- Flujos operativos y clínicos principales, como diagramas Mermaid.
- Reglas de negocio iniciales, cada una marcada como Validada o **SUPUESTO — validar**.
- Dependencias entre módulos, como diagrama.

### Paso 3 — Propuesta de arquitectura → `docs/adr/`
Un ADR por decisión, con: contexto, alternativas, ventajas, desventajas, costo operativo, recomendación y estado "Propuesto". Como mínimo:
- `001-stack.md`: Laravel + React frente a al menos una alternativa.
- `002-multitenancy.md`: base compartida con `tenant_id` frente a base por tenant frente a un esquema híbrido, contemplando la instalación independiente, las migraciones, los backups y la escalabilidad.
- `003-branches.md`: cómo se modelan empresa, sucursal y depósito.
- `004-frontend.md`: organización del frontend, Tabler y una capa de componentes propia.
- `005-infrastructure-security.md`: despliegue, backups, auditoría y cumplimiento de la Ley 7593/2025.
- `006-sifen-invoicing.md`: integración propia frente a un proveedor homologado.

### Paso 4 — Roadmap → `docs/04-roadmap.md`
Fases desde el MVP en adelante. Cada fase con: objetivos, funcionalidades, dependencias, documentación, entregables, pruebas y criterios de aceptación medibles.

### Paso 5 — Estructura documental → `docs/README.md` + `CLAUDE.md`
- Índice de toda la documentación y convenciones para mantenerla.
- Un borrador de `CLAUDE.md` en la raíz, de no más de 150 líneas, con el contexto, las reglas y los enlaces a `docs/` que Claude Code debe leer en cada sesión.
- `docs/pending-decisions.md` actualizado: decisión · opciones · quién decide · bloquea a qué fase.

## Para empezar

Leé `docs/references/` y los documentos de SyncTaller indicados. Mostrame un plan breve de cómo vas a encarar el Paso 1 y esperá mi aprobación.
