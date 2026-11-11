# Prompt: primera sesión (instalar el paquete base)

Una sola vez, antes del análisis de la FASE 0, en la carpeta del repositorio de SyncVet. Reemplazar `<ruta del zip>`.

```text
Vamos a instalar el paquete base de SyncVet en este repositorio. Antes de cada paso que modifique algo, muéstrame qué vas a hacer.

1. Recupera los archivos borrados con git restore . y confírmame que volvieron (docs/prompts/prompt-fase0-syncvet.md, docs/references/...).
2. Crea la rama feature/fase-00-paquete-base desde la rama actual.
3. Descomprime <ruta del zip> en una carpeta temporal y copia el contenido de syncvet-backend-base/ a la raíz del repositorio (CLAUDE.md, README.md, app/, bootstrap/, config/, database/, docs/, routes/, .claude/). No pises ni borres los archivos que ya estaban.
4. A partir de ahora la fuente de verdad es docs/ y CLAUDE.md, no tus notas de sesiones anteriores. Antes de olvidarlas: escribe en docs/decisions/registro.md una sección "0.3 Trabajo previo (Bloque 0.1)" con lo que tus notas digan de las decisiones DP-0.1-04 y DP-0.1-05 y del archivo docs/00-vision/oportunidades-producto.md que se perdió. Si algo no lo sabes con certeza, márcalo PENDIENTE DE VERIFICACIÓN.
5. Muéstrame git status y el mensaje de commit propuesto. No hagas commit hasta que te lo pida.

Después tengo que reiniciar Claude Code para que carguen las skills /retomar, /cerrar-entrega y /validar-docs.
```

Todavía no hay proyecto Laravel: lo crea la FASE 0, entrega 0.1.
