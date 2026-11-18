import { btnClasses } from './Button.jsx';

export function EmptyState({ title = 'Belum ada data', children }) {
  return (
    <div className="text-center px-4 py-12 text-secondary">
      <h3>{title}</h3>
      {children && <div>{children}</div>}
    </div>
  );
}

export function ErrorState({ message = 'Terjadi kesalahan.', onRetry }) {
  return (
    <div className="text-center px-4 py-12 text-danger">
      <p>{message}</p>
      {onRetry && (
        <button className={btnClasses('secondary', 'sm')} onClick={onRetry}>
          Coba lagi
        </button>
      )}
    </div>
  );
}

export function Skeleton({ width = '100%', height = '1rem' }) {
  return (
    <div
      className="rounded-xl min-h-4 animate-[bw-shimmer_1.2s_infinite] bg-[linear-gradient(90deg,var(--color-subtle)_25%,var(--color-border)_50%,var(--color-subtle)_75%)] bg-[length:200%_100%]"
      style={{ width, height }}
    />
  );
}
