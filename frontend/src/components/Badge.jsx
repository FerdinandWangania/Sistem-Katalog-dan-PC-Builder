import './ui.css';

// status: pending (yellow) | success (green) | failed (red) | info (blue) — PRD §11
export function Badge({ status = 'info', children }) {
  return <span className={`bw-badge bw-badge-${status}`}>{children}</span>;
}
