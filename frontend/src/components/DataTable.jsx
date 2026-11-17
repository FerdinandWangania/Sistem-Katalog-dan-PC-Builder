import { ChevronsLeft, ChevronLeft, ChevronRight, ChevronsRight } from 'lucide-react';
import './ui.css';

// columns: [{ key, label, render?: (row) => node }]
// pagination: { page (1-based), pageSize, total, onPage } — omit for no pagination
export function DataTable({ columns = [], rows = [], pagination, builderCards = false }) {
  return (
    <div>
      <div className="bw-table-wrap">
        <table className={`bw-table${builderCards ? ' bw-table-builder' : ''}`}>
          <thead>
            <tr>
              {columns.map((c) => (
                <th key={c.key}>{c.label}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, i) => (
              <tr key={row._id || row.id || i}>
                {columns.map((c) => (
                  <td key={c.key}>{c.render ? c.render(row) : row[c.key]}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {pagination && <Pagination {...pagination} />}
    </div>
  );
}

// Numbered pagination with ellipsis (first/prev/numbers/next/last), like the design reference
export function Pagination({ page = 1, pageSize = 10, total = 0, onPage }) {
  const pages = Math.max(1, Math.ceil(total / Math.max(1, pageSize)));
  if (pages <= 1 || !onPage) return null;
  // Compact window, max 5 slots: 1 … current … last
  let nums;
  if (pages <= 5) {
    nums = Array.from({ length: pages }, (_, i) => i + 1);
  } else if (page <= 2) {
    nums = [1, 2, 3, pages];
  } else if (page >= pages - 1) {
    nums = [1, pages - 2, pages - 1, pages];
  } else {
    nums = [1, page, pages];
  }
  const list = [...new Set(nums)].filter((n) => n >= 1 && n <= pages).sort((a, b) => a - b);
  const items = [];
  list.forEach((n, i) => {
    if (i > 0 && n - list[i - 1] > 1) items.push('…');
    items.push(n);
  });
  const btn = (Icon, target, disabled, aria) => (
    <button
      key={aria}
      className="bw-page-btn"
      disabled={disabled}
      onClick={() => onPage(target)}
      aria-label={aria}
    >
      <Icon className="w-4 h-4" />
    </button>
  );
  return (
    <div className="bw-pagination">
      {btn(ChevronsLeft, 1, page <= 1, 'Halaman pertama')}
      {btn(ChevronLeft, page - 1, page <= 1, 'Sebelumnya')}
      {items.map((n, i) =>
        n === '…' ? (
          <span key={`gap${i}`} className="bw-page-gap">
            …
          </span>
        ) : (
          <button
            key={n}
            className={`bw-page-btn${n === page ? ' active' : ''}`}
            onClick={() => onPage(n)}
            aria-current={n === page ? 'page' : undefined}
          >
            {n}
          </button>
        ),
      )}
      {btn(ChevronRight, page + 1, page >= pages, 'Berikutnya')}
      {btn(ChevronsRight, pages, page >= pages, 'Halaman terakhir')}
    </div>
  );
}
