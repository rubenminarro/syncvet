/** La marca "Inactivado" de un usuario ("Inactivo" parece el imperativo para el test de voseo, como en sucursales). */
export function UserInactiveBadge({ user }: { user: { is_active: boolean } }) {
  return user.is_active ? null : (
    <span className="badge bg-secondary-lt user-inactive">Inactivado</span>
  );
}
