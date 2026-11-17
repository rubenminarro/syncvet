import { useState, type SyntheticEvent } from 'react';
import { Button, DateField, TextField, TimeField } from '../../components/ui';
import { splitAuditDate } from './dates';
import {
  validateAuditFilterForm,
  type AuditFilterErrors,
  type AuditFilterField,
  type AuditFilterValues,
} from './filters';

interface AuditDateFiltersProps {
  /** Lo que hay en la URL: el formulario arranca con eso (la página lo vuelve a montar con `key` si la URL cambia). */
  desde: string | undefined;
  hasta: string | undefined;
  registro: number | undefined;
  /** ¿Hay un tipo elegido? El registro lo exige. */
  hasType: boolean;
  onApply: (values: AuditFilterValues) => void;
}

const FIELD_ORDER: readonly AuditFilterField[] = [
  'fromDate',
  'fromTime',
  'toDate',
  'toTime',
  'record',
];

/**
 * "Desde" y "Hasta" (una fecha con una hora opcional, en la hora de Asunción) y el número del registro. Se aplican con
 * "Filtrar" (no a cada tecla: una fecha a medio elegir no es un filtro): las reglas de la API con sus mensajes, en el campo,
 * con el foco en el primero, y sin cambiar la URL ni pedir nada si hay un error.
 */
export function AuditDateFilters({
  desde,
  hasta,
  registro,
  hasType,
  onApply,
}: AuditDateFiltersProps) {
  const from = splitAuditDate(desde);
  const to = splitAuditDate(hasta);
  const [fromDate, setFromDate] = useState(from.date);
  const [fromTime, setFromTime] = useState(from.time);
  const [toDate, setToDate] = useState(to.date);
  const [toTime, setToTime] = useState(to.time);
  const [record, setRecord] = useState(registro === undefined ? '' : String(registro));
  const [errors, setErrors] = useState<AuditFilterErrors>({});

  const submit = (event: SyntheticEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = event.currentTarget;
    const field = (name: AuditFilterField) => {
      const element = form.elements.namedItem(name);

      return element instanceof HTMLInputElement ? element : null;
    };
    const result = validateAuditFilterForm(
      {
        fromDate,
        fromTime,
        toDate,
        toTime,
        record,
        fromBadInput: field('fromDate')?.validity.badInput === true,
        toBadInput: field('toDate')?.validity.badInput === true,
      },
      hasType,
    );
    setErrors(result.errors);

    if (result.values === null) {
      const first = FIELD_ORDER.find((name) => result.errors[name] !== undefined);
      field(first ?? 'fromDate')?.focus();

      return;
    }

    onApply(result.values);
  };

  return (
    <form className="row g-2 align-items-start audit-date-filters" noValidate onSubmit={submit}>
      <div className="col-6 col-md-3 col-lg-2">
        <DateField
          name="fromDate"
          label="Desde"
          value={fromDate}
          error={errors.fromDate}
          onChange={(event) => {
            setFromDate(event.target.value);
          }}
        />
      </div>
      <div className="col-6 col-md-3 col-lg-2">
        <TimeField
          name="fromTime"
          label="Hora desde"
          value={fromTime}
          error={errors.fromTime}
          onChange={(event) => {
            setFromTime(event.target.value);
          }}
        />
      </div>
      <div className="col-6 col-md-3 col-lg-2">
        <DateField
          name="toDate"
          label="Hasta"
          value={toDate}
          error={errors.toDate}
          onChange={(event) => {
            setToDate(event.target.value);
          }}
        />
      </div>
      <div className="col-6 col-md-3 col-lg-2">
        <TimeField
          name="toTime"
          label="Hora hasta"
          value={toTime}
          error={errors.toTime}
          onChange={(event) => {
            setToTime(event.target.value);
          }}
        />
      </div>
      <div className="col-6 col-md-3 col-lg-2">
        <TextField
          name="record"
          label="N.º de registro"
          inputMode="numeric"
          autoComplete="off"
          value={record}
          error={errors.record}
          onChange={(event) => {
            setRecord(event.target.value);
          }}
        />
      </div>
      <div className="col-6 col-md-3 col-lg-2 audit-filter-submit">
        <Button type="submit">Filtrar</Button>
      </div>
    </form>
  );
}
