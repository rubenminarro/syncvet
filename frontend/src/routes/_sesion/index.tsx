import { createFileRoute } from '@tanstack/react-router';
import { HomePage } from '../../app/HomePage';

export const Route = createFileRoute('/_sesion/')({
  component: HomePage,
});
