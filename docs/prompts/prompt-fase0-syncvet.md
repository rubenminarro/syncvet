# SYNCVET — FASE 0: ANÁLISIS FUNCIONAL Y PLANIFICACIÓN

## Contexto

SyncVet es un nuevo software profesional de gestión integral para veterinarias, clínicas y hospitales veterinarios.

- Modalidad principal: SaaS multi-tenant.
- Modalidad secundaria: instalación independiente para clientes que no quieran usar el SaaS, sin duplicar el núcleo del producto.
- Mercado inicial: Paraguay. Interfaz en español (voseo). Moneda inicial: guaraníes (PYG, sin decimales).
- Normativa a considerar: facturación electrónica SIFEN (DNIT) y Ley 7593/2025 de Protección de Datos Personales (vigencia plena en noviembre de 2027).
- Expansión futura a otros países y multi-moneda: prever en el diseño, no desarrollar todavía.

## Referencias

Están en `docs/referencias/`:

- `competencia.md`: resumen y comparativa de funciones de PetSys, Asys Next, VetPlus (Datasoft) y Velary.
- `anuncios/`: imágenes de los anuncios de esos sistemas.
- Sitio de referencia principal: https://www.petsys.com.py/#funciones

Usalas para identificar funcionalidades y buenas prácticas. No copies su interfaz ni su implementación: el objetivo es un producto propio, igual o superior.

## Reglas de esta etapa

1. NO programar, NO crear migraciones, NO instalar dependencias, NO modificar archivos de configuración.
2. Solo podés crear y editar archivos Markdown dentro de `docs/`.
3. No asumas reglas de negocio sin validar. Si necesitás un supuesto para avanzar, marcalo como **SUPUESTO — validar** y agregalo a `docs/decisiones-pendientes.md`.
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

- El paquete oficial `tabler-react` está archivado y Tabler se basa en Bootstrap 5. Evaluá cómo usarlo con React y cómo aislarlo para poder migrar a Tailwind más adelante.
- MySQL no tiene Row Level Security: el aislamiento entre tenants depende de la aplicación.

## Trabajo solicitado (un paso por vez)

### Paso 1 — Análisis funcional → `docs/01-analisis-funcional.md`
- Organizá todas las funcionalidades por módulo.
- Clasificá cada una como Imprescindible, Importante, Avanzada u Opcional, en una tabla: módulo · funcionalidad · prioridad · referencia que la tiene · comentario.
- Proponé funcionalidades adicionales con valor competitivo y explicá por qué.

### Paso 2 — Análisis de negocio → `docs/02-analisis-negocio.md`
- Tipos de establecimiento (consultorio, clínica, hospital, cadena con sucursales, peluquería).
- Tipos de usuario, roles y responsabilidades (como mínimo: recepción, veterinario, administración, dueño, propietario de la mascota, administrador de la plataforma SaaS).
- Flujos operativos y clínicos principales, como diagramas Mermaid.
- Reglas de negocio iniciales, cada una marcada como Validada o **SUPUESTO — validar**.
- Dependencias entre módulos, como diagrama.

### Paso 3 — Propuesta de arquitectura → `docs/adr/`
Un ADR por decisión, con: contexto, alternativas, ventajas, desventajas, costo operativo, recomendación y estado "Propuesto". Como mínimo:
- `001-stack.md`: Laravel + React frente a al menos una alternativa.
- `002-multitenancy.md`: base compartida con `tenant_id` frente a base por tenant frente a un esquema híbrido, contemplando la instalación independiente, las migraciones, los backups y la escalabilidad.
- `003-sucursales.md`: cómo se modelan empresa, sucursal y depósito.
- `004-frontend.md`: organización del frontend, Tabler y una capa de componentes propia.
- `005-infraestructura-seguridad.md`: despliegue, backups, auditoría y cumplimiento de la Ley 7593/2025.
- `006-facturacion-sifen.md`: integración propia frente a un proveedor homologado.

### Paso 4 — Roadmap → `docs/04-roadmap.md`
Fases desde el MVP en adelante. Cada fase con: objetivos, funcionalidades, dependencias, documentación, entregables, pruebas y criterios de aceptación medibles.

### Paso 5 — Estructura documental → `docs/README.md` + `CLAUDE.md`
- Índice de toda la documentación y convenciones para mantenerla.
- Un borrador de `CLAUDE.md` en la raíz, de no más de 150 líneas, con el contexto, las reglas y los enlaces a `docs/` que Claude Code debe leer en cada sesión.
- `docs/decisiones-pendientes.md` actualizado: decisión · opciones · quién decide · bloquea a qué fase.

## Para empezar

Leé `docs/referencias/`. Mostrame un plan breve de cómo vas a encarar el Paso 1 y esperá mi aprobación.
