import { Search, SlidersHorizontal, X } from 'lucide-react';

// Reusable pill search bar (dipakai di katalog & home).
// Controlled: parent memegang `value`. Enter / tombol X / ikon filter
// diteruskan ke parent lewat callback.
export function SearchBar({
  value = '',
  onChange,
  onSubmit,
  onFilterClick,
  filterClassName = '',
  recent = [],
  onRecentSelect,
  placeholder = 'Search for products, brands...',
  label = 'Cari produk',
  size = 'md',
  className = '',
}) {
  const pad = size === 'sm' ? 'px-3 py-0.5' : 'px-4 py-2';
  const icon = size === 'sm' ? 'w-4 h-4' : 'w-5 h-5';
  return (
    <>
    <div className={`flex items-center gap-2 bg-surface border border-border rounded-full ${pad} text-muted ${className}`}>
      <Search className={`${icon} shrink-0`} />
      <input
        value={value}
        onChange={(e) => onChange?.(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter') onSubmit?.(value);
        }}
        placeholder={placeholder}
        aria-label={label}
        className="flex-1 min-w-0 border-0 outline-none bg-transparent text-[0.9rem] text-ink placeholder:text-placeholder"
      />
      {value && (
        <button
          type="button"
          aria-label="Bersihkan pencarian"
          onClick={() => {
            onChange?.('');
            onSubmit?.('');
          }}
          className="inline-flex shrink-0 bg-transparent border-0 p-0 text-muted cursor-pointer"
        >
          <X className="w-4 h-4" />
        </button>
      )}
      {onFilterClick && (
        <button
          type="button"
          aria-label="Filter"
          onClick={onFilterClick}
          className={`inline-flex shrink-0 bg-transparent border-0 p-0 text-muted cursor-pointer ${filterClassName}`}
        >
          <SlidersHorizontal className={icon} />
        </button>
      )}
    </div>
      {recent.length > 0 && (
        <div className="flex gap-[0.4rem] flex-wrap mt-[0.6rem]">
          {recent.map((r) => (
            <button
              key={r}
              type="button"
              onClick={() => onRecentSelect?.(r)}
              className="bg-subtle border-0 rounded-full px-[0.8rem] py-[0.35rem] text-[0.8rem] text-secondary"
            >
              {r}
            </button>
          ))}
        </div>
      )}
    </>
  );
}
