// status: pending (yellow) | success (green) | failed (red) | info (blue) — PRD §11
const STATUS = {
  pending: 'bg-warning text-surface',
  success: 'bg-success text-surface',
  failed: 'bg-danger text-surface',
  info: 'bg-info text-surface',
};

export function Badge({ status = 'info', children }) {
  return (
    <span className={`inline-block px-3 py-[0.2rem] rounded-full text-[0.8rem] font-bold ${STATUS[status] || STATUS.info}`}>
      {children}
    </span>
  );
}
