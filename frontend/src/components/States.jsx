import './ui.css';

export function EmptyState({ title = 'Belum ada data', children }) {
  return (
    <div className="bw-empty">
      <h3>{title}</h3>
      {children && <div>{children}</div>}
    </div>
  );
}

export function ErrorState({ message = 'Terjadi kesalahan.', onRetry }) {
  return (
    <div className="bw-error">
      <p>{message}</p>
      {onRetry && (
        <button className="bw-btn bw-btn-secondary bw-btn-sm" onClick={onRetry}>
          Coba lagi
        </button>
      )}
    </div>
  );
}

export function Skeleton({ width = '100%', height = '1rem' }) {
  return <div className="bw-skeleton" style={{ width, height }} />;
}
