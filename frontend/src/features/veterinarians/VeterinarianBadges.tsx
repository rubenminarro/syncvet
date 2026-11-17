import type { Veterinarian } from './types';

/**
 * El estado de la ficha (la etiqueta de la API: "Activo" o "Inactivo") y, si su usuario está inactivo, "Usuario inactivo"
 * (las fases 3 y 4 no lo asignan).
 */
export function VeterinarianBadges({
  veterinarian,
}: {
  veterinarian: Pick<Veterinarian, 'status' | 'user'>;
}) {
  return (
    <span className="d-inline-flex gap-1 flex-wrap">
      <span
        className={`badge ${veterinarian.status.value === 'active' ? 'bg-green-lt' : 'bg-secondary-lt'} veterinarian-status`}
      >
        {veterinarian.status.label}
      </span>
      {veterinarian.user.is_active ? null : (
        <span className="badge bg-orange-lt veterinarian-user-inactive">Usuario inactivo</span>
      )}
    </span>
  );
}
