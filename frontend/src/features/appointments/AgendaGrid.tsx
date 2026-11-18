import { IconLock } from '@tabler/icons-react';
import { memo, useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { AppointmentSummary } from './AgendaShared';
import { appointmentItemClass } from './agenda-shared';
import { minutesToClock } from './dates';
import {
  appointmentLabel,
  ROW_MINUTES,
  type DayGrid,
  type GridColumn,
  type GridItem,
} from './grid';

/** El alto de una fila de 15 minutos, en píxeles (una hora, 96 px). Va también en `styles.css` (`--agenda-row`). */
const ROW_PX = 24;

interface Position {
  col: number;
  row: number;
}

const cellKey = (row: number, col: number) => `c:${String(row)}:${String(col)}`;
const itemKey = (id: number) => `a:${String(id)}`;

/** ¿El tramo de la fila [inicio, inicio + 15) se superpone con alguno de los intervalos? */
function overlaps(intervals: readonly { start: number; end: number }[], start: number): boolean {
  return intervals.some((interval) => interval.start < start + ROW_MINUTES && interval.end > start);
}

/**
 * El orden del teclado: en cada columna, de arriba abajo, cada celda seguida de los turnos que empiezan en ella (por
 * carril). Flecha arriba y abajo recorren ese orden; a los costados, la celda de la misma fila de la columna vecina.
 */
function navigation(grid: DayGrid) {
  const sequences: string[][] = [];
  const positions = new Map<string, Position & { index: number }>();
  const starting = new Map<string, GridItem[]>();

  grid.columns.forEach((column, col) => {
    const byRow = startingItemsByRow(column, grid);
    const sequence: string[] = [];

    grid.rows.forEach((_, row) => {
      const cell = cellKey(row, col);
      positions.set(cell, { col, row, index: sequence.length });
      sequence.push(cell);

      const items = byRow.get(row) ?? [];
      starting.set(cell, items);

      for (const item of items) {
        const key = itemKey(item.appointment.id);
        positions.set(key, { col, row, index: sequence.length });
        sequence.push(key);
      }
    });

    sequences.push(sequence);
  });

  return { sequences, positions, starting };
}

/**
 * Los turnos de la columna por la fila en que empiezan (la fila sale directo del inicio: con cientos de turnos, buscarla
 * fila por fila trababa cada tecla), en el orden de sus carriles.
 */
function startingItemsByRow(column: GridColumn, grid: DayGrid): Map<number, GridItem[]> {
  const byRow = new Map<number, GridItem[]>();
  const lastRow = grid.rows.length - 1;

  for (const item of column.items) {
    const row = Math.min(Math.floor((item.start - grid.start) / ROW_MINUTES), lastRow);
    const list = byRow.get(row) ?? [];
    list.push(item);
    byRow.set(row, list);
  }

  for (const list of byRow.values()) {
    list.sort((a, b) => a.lane - b.lane || a.start - b.start);
  }

  return byRow;
}

interface AgendaGridProps {
  grid: DayGrid;
  /** "Agenda del martes 17 de noviembre de 2026". */
  label: string;
  /** Re Pág y Av Pág: el día (o la semana, 3.3) anterior y el siguiente. */
  onPrevious: () => void;
  onNext: () => void;
  onOpen: (item: GridItem, scheduleName: string) => void;
  /** Mientras llega otro día (la grilla sigue con el anterior). */
  busy: boolean;
}

/**
 * La grilla del día (diseño de la F3, "Accesibilidad"): el patrón *grid* de WAI-ARIA, con las agendas como encabezados de
 * columna y las horas como encabezados de fila, y **un solo punto de tabulación** (*roving tabindex*). Las flechas recorren
 * las celdas y los turnos; Inicio y Fin van al comienzo y al fin de la fila (con Ctrl, de la grilla); Re Pág y Av Pág
 * cambian de día (en la semana, de semana); Enter abre el turno (en la 3.2, el panel de solo lectura). Si el turno con el foco desaparece con la
 * actualización (otra PC lo movió o lo canceló), el foco va a su celda; la actualización nunca mueve el foco si no estaba
 * en la grilla.
 */
export function AgendaGrid({ grid, label, onPrevious, onNext, onOpen, busy }: AgendaGridProps) {
  const gridRef = useRef<HTMLDivElement>(null);
  // La clave con el punto de tabulación y dónde estaba al elegirla (para ir a su celda si el turno desaparece).
  const [focus, setFocus] = useState<(Position & { key: string }) | null>(null);
  const focusKey = focus?.key ?? null;
  const hadFocus = useRef(false);
  const { sequences, positions, starting } = useMemo(() => navigation(grid), [grid]);

  // La clave que tiene el punto de tabulación: la elegida, si sigue; si el turno desapareció, su celda; si no, la primera.
  let effectiveKey = cellKey(0, 0);

  if (focus !== null && positions.has(focus.key)) {
    effectiveKey = focus.key;
  } else if (focus !== null) {
    const row = Math.max(Math.min(focus.row, grid.rows.length - 1), 0);
    const col = Math.max(Math.min(focus.col, grid.columns.length - 1), 0);
    effectiveKey = cellKey(row, col);
  }

  function remember(key: string) {
    const position = positions.get(key);

    if (position !== undefined && key !== focusKey) {
      setFocus({ key, row: position.row, col: position.col });
    }
  }

  // El turno con el foco desapareció: el foco va a su celda, solo si estaba en la grilla.
  useEffect(() => {
    const active = document.activeElement;
    const inside = gridRef.current?.contains(active) === true;

    if ((inside || (hadFocus.current && active === document.body)) && focusKey !== effectiveKey) {
      gridRef.current?.querySelector<HTMLElement>(`[data-key="${effectiveKey}"]`)?.focus();
    }
  }, [effectiveKey, focusKey]);

  function moveTo(key: string | undefined) {
    if (key === undefined) {
      return;
    }

    remember(key);
    gridRef.current?.querySelector<HTMLElement>(`[data-key="${key}"]`)?.focus();
  }

  function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const current = positions.get(effectiveKey);

    if (current === undefined) {
      return;
    }

    const lastRow = grid.rows.length - 1;
    const lastCol = grid.columns.length - 1;
    const sequence = sequences[current.col] ?? [];
    let target: string | undefined;

    switch (event.key) {
      case 'ArrowDown':
        target = sequence[Math.min(current.index + 1, sequence.length - 1)];
        break;
      case 'ArrowUp':
        target = sequence[Math.max(current.index - 1, 0)];
        break;
      case 'ArrowRight':
        target = cellKey(current.row, Math.min(current.col + 1, lastCol));
        break;
      case 'ArrowLeft':
        target = cellKey(current.row, Math.max(current.col - 1, 0));
        break;
      case 'Home':
        target = event.ctrlKey ? cellKey(0, 0) : cellKey(current.row, 0);
        break;
      case 'End':
        target = event.ctrlKey ? cellKey(lastRow, lastCol) : cellKey(current.row, lastCol);
        break;
      case 'PageUp':
        event.preventDefault();
        onPrevious();

        return;
      case 'PageDown':
        event.preventDefault();
        onNext();

        return;
      default:
        return;
    }

    event.preventDefault();
    moveTo(target);
  }

  const columnCount = grid.columns.length;

  return (
    <div className="agenda-grid-scroll">
      <div
        ref={gridRef}
        role="grid"
        aria-label={label}
        aria-rowcount={grid.rows.length + 1}
        aria-colcount={columnCount + 1}
        aria-busy={busy}
        className={`agenda-grid${busy ? ' agenda-grid-busy' : ''}`}
        style={{ ['--agenda-columns' as string]: String(columnCount) }}
        onKeyDown={onKeyDown}
        onFocus={(event) => {
          hadFocus.current = true;
          const key = (event.target as HTMLElement).dataset.key;

          if (key !== undefined) {
            remember(key);
          }
        }}
        onBlur={(event) => {
          const next = event.relatedTarget;

          if (next instanceof Node && gridRef.current?.contains(next) === true) {
            return;
          }

          // Sin destino: el usuario salió (el elemento sigue en la página) o el elemento desapareció con la actualización.
          const target = event.target;
          window.setTimeout(() => {
            if (target.isConnected || next !== null) {
              hadFocus.current = false;
            }
          }, 0);
        }}
      >
        <div role="row" className="agenda-row agenda-head" aria-rowindex={1}>
          <div role="columnheader" className="agenda-corner" aria-colindex={1}>
            <span className="visually-hidden">Hora</span>
          </div>
          {grid.columns.map((column, col) => (
            <div
              key={column.key}
              role="columnheader"
              className="agenda-column-head"
              aria-colindex={col + 2}
              style={
                column.schedule.color === null
                  ? undefined
                  : { borderTopColor: column.schedule.color }
              }
            >
              <div className="fw-bold text-truncate agenda-column-name">{column.heading}</div>
              <div className="small text-secondary text-truncate">{column.subheading}</div>
            </div>
          ))}
        </div>
        {grid.rows.map((rowStart, row) => {
          const clock = minutesToClock(rowStart);

          return (
            <div key={rowStart} role="row" className="agenda-row" aria-rowindex={row + 2}>
              <div
                role="rowheader"
                className={`agenda-time${rowStart % 60 === 0 ? ' agenda-time-hour' : ''}`}
                aria-colindex={1}
              >
                <span className={rowStart % 60 === 0 ? undefined : 'visually-hidden'}>{clock}</span>
              </div>
              {grid.columns.map((column, col) => {
                const key = cellKey(row, col);
                const closed = overlaps(column.closed, rowStart);
                const block = column.blocks.find(
                  (item) => item.start < rowStart + ROW_MINUTES && item.end > rowStart,
                );
                const blockStartsHere =
                  block !== undefined && (block.start >= rowStart || rowStart === grid.start);
                const states = [
                  block === undefined ? '' : `bloqueado${block.reason ? ` (${block.reason})` : ''}`,
                  closed && block === undefined ? 'fuera del horario' : '',
                ].filter((text) => text !== '');

                return (
                  <div
                    key={column.key}
                    role="gridcell"
                    data-key={key}
                    tabIndex={effectiveKey === key ? 0 : -1}
                    aria-colindex={col + 2}
                    aria-label={[clock, column.cellLabel, ...states].join(', ')}
                    className={`agenda-cell${closed ? ' agenda-closed' : ''}${block === undefined ? '' : ' agenda-blocked'}`}
                  >
                    {blockStartsHere ? (
                      <span className="agenda-block-label" aria-hidden="true">
                        <IconLock size={14} className="me-1" />
                        {block.reason ?? 'Bloqueado'}
                      </span>
                    ) : null}
                    {(starting.get(key) ?? []).map((item) => (
                      <AgendaItem
                        key={item.appointment.id}
                        item={item}
                        rowStart={rowStart}
                        color={column.schedule.color}
                        scheduleName={column.schedule.name}
                        context={column.itemContext}
                        focusable={effectiveKey === itemKey(item.appointment.id)}
                        onOpen={onOpen}
                      />
                    ))}
                  </div>
                );
              })}
            </div>
          );
        })}
      </div>
    </div>
  );
}

/** En `memo`: una tecla vuelve a dibujar solo los turnos cuyo punto de tabulación cambió (con 300 turnos, todos trababan). */
const AgendaItem = memo(function AgendaItem({
  item,
  rowStart,
  color,
  scheduleName,
  context,
  focusable,
  onOpen,
}: {
  item: GridItem;
  rowStart: number;
  color: string | null;
  scheduleName: string;
  /** El final del nombre accesible: la agenda y, en la semana, el día. */
  context: string;
  focusable: boolean;
  onOpen: (item: GridItem, scheduleName: string) => void;
}) {
  const { appointment } = item;
  const status = appointment.status.value;
  const width = 100 / item.lanes;

  return (
    <button
      type="button"
      data-key={itemKey(appointment.id)}
      data-status={status}
      tabIndex={focusable ? 0 : -1}
      className={appointmentItemClass(appointment, item.inBlock)}
      aria-label={appointmentLabel(item, context)}
      style={{
        top: `${String(((item.start - rowStart) / ROW_MINUTES) * ROW_PX)}px`,
        height: `${String(Math.max(((item.end - item.start) / ROW_MINUTES) * ROW_PX - 2, ROW_PX - 2))}px`,
        left: `calc(${String(width * item.lane)}% + 2px)`,
        width: `calc(${String(width)}% - 4px)`,
        ...(color === null ? {} : { borderLeftColor: color }),
      }}
      onClick={() => {
        onOpen(item, scheduleName);
      }}
    >
      <AppointmentSummary appointment={appointment} inBlock={item.inBlock} />
    </button>
  );
});
