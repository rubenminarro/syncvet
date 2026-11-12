import { createFileRoute } from '@tanstack/react-router';
import { PublicLayout } from '../app/PublicLayout';

// Layout sin ruta propia (el "_" no forma parte de la URL): las páginas públicas.
export const Route = createFileRoute('/_publico')({
  component: PublicLayout,
});
