# Prompt: Análisis de lo hecho

Al empezar cada fase. Solo lectura. En la FASE 0, después de [`0-primera-sesion.md`](0-primera-sesion.md). Reemplazar `NN` por el número de fase y `X` por la entrega.

```text
Ejecuta /retomar. Después, antes de empezar la FASE NN, analiza lo hecho hasta ahora. No modifiques nada.

1. Lee CLAUDE.md, docs/03-plan-desarrollo/handoff.md, docs/03-plan-desarrollo/README.md, docs/03-plan-desarrollo/modulos.md, docs/03-plan-desarrollo/deuda-tecnica.md y docs/03-plan-desarrollo/fases/backend/fase-NN-*.md.
2. Corre la suite completa (php artisan test) y anota tests y aserciones en verde, y cuáles fallan.
3. Ejecuta /validar-docs y compara lo documentado con el código: rutas, permisos del seeder, migraciones, enums.
4. Verifica que la fase anterior cumpla su criterio de cierre (está en su archivo de fase) y que su sección del registro de decisiones exista.

En la FASE 00 todavía no hay proyecto Laravel ni tests: en lugar de los pasos 2 y 4, verifica que el paquete base esté completo (migraciones, app/, docs/, .claude/skills) y que el registro R-001 exista.

Entrégame un informe con: checkpoint validado, estado de la suite, discrepancias, deuda que afecta a la FASE NN, decisiones pendientes para la FASE NN y si se puede empezar. No propongas código todavía.
```
