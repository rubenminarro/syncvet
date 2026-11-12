import { createFileRoute } from '@tanstack/react-router';
import { ForbiddenPage } from '../../app/ForbiddenPage';

export const Route = createFileRoute('/_sesion/sin-permiso')({
  component: ForbiddenPage,
});
