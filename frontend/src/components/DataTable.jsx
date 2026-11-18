import { ChevronsLeft, ChevronLeft, ChevronRight, ChevronsRight } from 'lucide-react';

// columns: [{ key, label, render?: (row) => node }]
// pagination: { page (1-based), pageSize, total, onPage } — omit for no pagination
// builderCards: mobile turns the table into per-slot cards (PRD NFR-005)
export function DataTable({ columns = [], rows = [], pagination, builderCards = false }) {
  return (
    <div>
      <div className="overflow-x-auto rounded-2xl border border-border">
        <table
          className={`w-full border-collapse bg-surface text-[0.9rem] ${
            builderCards
              ? 'max-md:[&>thead]:hidden max-md:[&_tbody]:block max-md:[&_tr]:block max-md:[&_tr]:w-full max-md:[&_tr]:rounded-2xl max-md:[&_tr]:border max-md:[&_tr]:border-border max-md:[&_tr]:bg-surface max-md:[&_tr]:mb-3 max-md:[&_td]:block max-md:[&_td]:w-full max-md:[&_td]:border-0'
              : ''
          }`}
        >
          <thead>
            <tr>
              {columns.map((c) => (
                <th
                  key={c.key}
                  className="bg-ink text-surface text-left px-4 py-3 text-[0.75rem] uppercase tracking-[0.05em] whitespace-nowrap"
                >
                  {c.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, i) => (
              <tr key={row._id || row.id || i} className="even:[&>td]:bg-subtle">
                {columns.map((c) => (
                  <td key={c.key} className="px-4 py-3 border-t border-border text-ink">
                    {c.render ? c.render(row) : row[c.key]}
                  </td>
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

// First/prev/numbered/next/last with ellipsis, max 5 slots
export function Pagination({ page = 1, pageSize = 10, total = 0, onPage }) {
  const pages = Math.max(1, Math.ceil(total / Math.max(1, pageSize)));
  if (pages <= 1 || !onPage) return null;
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
      className="min-w-[2.2rem] h-[2.2rem] px-2 border border-border rounded-lg bg-surface text-ink font-semibold cursor-pointer inline-flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed hover:bg-subtle"
      disabled={disabled}
      onClick={() => onPage(target)}
      aria-label={aria}
    >
      <Icon className="w-4 h-4" />
    </button>
  );
  return (
    <div className="flex gap-[0.35rem] items-center justify-center mt-3">
      {btn(ChevronsLeft, 1, page <= 1, 'Halaman pertama')}
      {btn(ChevronLeft, page - 1, page <= 1, 'Sebelumnya')}
      {items.map((n, i) =>
        n === '…' ? (
          <span key={`gap${i}`} className="text-muted px-1">
            …
          </span>
        ) : (
          <button
            key={n}
            className={`min-w-[2.2rem] h-[2.2rem] px-2 border rounded-lg font-semibold cursor-pointer inline-flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed ${
              n === page ? 'bg-ink text-surface border-ink' : 'border-border bg-surface text-ink hover:bg-subtle'
            }`}
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
