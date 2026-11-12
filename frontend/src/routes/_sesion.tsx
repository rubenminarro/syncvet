import { createFileRoute } from '@tanstack/react-router';
import { SessionLayout } from '../app/SessionLayout';

// Layout con sesión (el "_" no forma parte de la URL). En la F0.2 su beforeLoad exige una sesión válida.
export const Route = createFileRoute('/_sesion')({
  component: SessionLayout,
});
