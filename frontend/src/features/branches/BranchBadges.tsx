/** Las marcas "Por defecto" e "Inactivada" (o "Inactivado", el depósito; "Inactiva" parece el imperativo para el test de voseo, como en especies) de una sucursal o un depósito. */
export function BranchBadges({
  item,
  feminine,
}: {
  item: { is_default: boolean; is_active: boolean };
  /** La sucursal: "Inactivada"; el depósito: "Inactivado". */
  feminine: boolean;
}) {
  return (
    <span className="d-inline-flex gap-1 flex-wrap">
      {item.is_default ? (
        <span className="badge bg-blue-lt branch-default">Por defecto</span>
      ) : null}
      {item.is_active ? null : (
        <span className="badge bg-secondary-lt branch-inactive">
          {feminine ? 'Inactivada' : 'Inactivado'}
        </span>
      )}
    </span>
  );
}
