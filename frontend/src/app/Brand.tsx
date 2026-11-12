import { IconPaw } from '@tabler/icons-react';

export function Brand() {
  return (
    <span className="navbar-brand navbar-brand-autodark d-inline-flex align-items-center gap-2">
      <IconPaw size={28} stroke={1.75} aria-hidden="true" />
      <span className="fw-bold">SyncVet</span>
    </span>
  );
}
