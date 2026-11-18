import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ChevronLeft, X } from 'lucide-react';
import { get as getData } from '../lib/data.js';
import { EXTRA_CATEGORIES, MAIN_CATEGORIES, discountPercent, effectivePrice } from '../lib/catalog.js';
import { ProductCard } from '../components/ProductCard.jsx';
import { DesktopSearchBar } from '../components/DesktopSearchBar.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { Shell } from '../components/Layout.jsx';
import { btnClasses } from '../components/Button.jsx';

const SORTS = [
  { value: 'unggulan', label: 'Unggulan' },
  { value: 'price_asc', label: 'Harga terendah' },
  { value: 'price_desc', label: 'Harga tertinggi' },
];

const BATCH = 15;
const ALL_CATEGORIES = [...MAIN_CATEGORIES.map((c) => c.slug), ...EXTRA_CATEGORIES];

function SidebarFilters({
  category,
  brand,
  brandOptions,
  onCategory,
  onBrand,
  onReset,
  hasActive,
}) {
  const chip = (active) =>
    `rounded-full border px-3 py-[0.35rem] text-[0.75rem] font-semibold cursor-pointer transition uppercase ${
      active ? 'bg-ink text-surface border-transparent' : 'bg-surface text-secondary border-border hover:bg-subtle'
    }`;
  return (
    <div className="flex flex-col gap-6">
      <div>
        <h3 className="text-[0.75rem] font-bold uppercase tracking-[0.08em] text-secondary">Kategori</h3>
        <div className="flex flex-wrap gap-2 mt-3">
          <button className={chip(!category)} onClick={() => onCategory('')} aria-pressed={!category}>
            Semua
          </button>
          {ALL_CATEGORIES.map((c) => (
            <button
              key={c}
              className={chip(category === c)}
              onClick={() => onCategory(category === c ? '' : c)}
              aria-pressed={category === c}
            >
              {c}
            </button>
          ))}
        </div>
      </div>
      <div>
        <h3 className="text-[0.75rem] font-bold uppercase tracking-[0.08em] text-secondary">Produsen</h3>
        <div className="flex flex-wrap gap-2 mt-3">
          <button className={chip(!brand)} onClick={() => onBrand('')} aria-pressed={!brand}>
            Semua
          </button>
          {brandOptions.map((b) => (
            <button
              key={b}
              className={chip(brand === b)}
              onClick={() => onBrand(brand === b ? '' : b)}
              aria-pressed={brand === b}
            >
              {b}
            </button>
          ))}
        </div>
      </div>
      {hasActive && (
        <button
          className="inline-flex items-center justify-center gap-1 rounded-full border border-border bg-surface text-secondary px-4 py-2 text-[0.8rem] font-semibold cursor-pointer hover:bg-subtle"
          onClick={onReset}
        >
          <X className="w-4 h-4" /> Hapus Semua Filter
        </button>
      )}
    </div>
  );
}

function KatalogInner() {
  const [searchParams, setSearchParams] = useSearchParams();
  const category = searchParams.get('category') || '';
  const brand = searchParams.get('brand') || '';
  const q = searchParams.get('q') || '';
  const sort = searchParams.get('sort') || 'unggulan';

  const [products, setProducts] = useState(null);
  const [brandBase, setBrandBase] = useState([]);
  const [error, setError] = useState(null);
  const [input, setInput] = useState(q);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [limit, setLimit] = useState(BATCH);
  const sentinelRef = useRef(null);

  useEffect(() => {
    setInput(q);
  }, [q]);

  const patchParams = (patch) => {
    const next = new URLSearchParams(searchParams);
    for (const [k, v] of Object.entries(patch)) {
      if (v) next.set(k, v);
      else next.delete(k);
    }
    setSearchParams(next);
  };

  // Debounce pencarian -> URL (?q=)
  useEffect(() => {
    if (input === q) return;
    const t = setTimeout(() => {
      patchParams({ q: input.trim() });
    }, 400);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [input, q]);

  // Brand options: unfiltered fetch so the list stays stable across filters.
  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const res = await getData('/products');
        if (!alive) return;
        setBrandBase(res.data || []);
      } catch {
        // abaikan: daftar merek fallback ke hasil filter di bawah
      }
    })();
    return () => {
      alive = false;
    };
  }, []);

  useEffect(() => {
    let alive = true;
    setError(null);
    (async () => {
      try {
        const query = {};
        if (category) query.category = category;
        if (brand) query.brand = brand;
        if (q) query.q = q;
        if (sort === 'price_asc' || sort === 'price_desc') query.sort = sort;
        const res = await getData('/products', { query });
        if (!alive) return;
        const list = [...(res.data || [])];
        if (sort === 'price_asc') list.sort((a, b) => effectivePrice(a) - effectivePrice(b));
        else if (sort === 'price_desc') list.sort((a, b) => effectivePrice(b) - effectivePrice(a));
        else list.sort((a, b) => discountPercent(b) - discountPercent(a));
        setProducts(list);
      } catch (e) {
        if (alive) {
          setError(e);
          setProducts([]);
        }
      }
    })();
    return () => {
      alive = false;
    };
  }, [category, brand, q, sort]);

  // Reset jumlah tampil tiap filter berubah.
  useEffect(() => {
    setLimit(BATCH);
  }, [category, brand, q, sort]);

  // Infinite scroll: tambah batch saat sentinel masuk area display.
  useEffect(() => {
    const el = sentinelRef.current;
    if (!el || products === null) return;
    if (limit >= products.length) return;
    const obs = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) setLimit((l) => Math.min(l + BATCH, products.length));
      },
      { rootMargin: '600px' },
    );
    obs.observe(el);
    return () => obs.disconnect();
  }, [products, limit]);

  const brandOptions = useMemo(() => {
    const names = [...brandBase, ...(products || [])].map((p) => p.brand).filter(Boolean);
    return [...new Set(names)].sort();
  }, [brandBase, products]);

  const loading = products === null;
  const total = products?.length || 0;
  const visible = (products || []).slice(0, limit);
  const hasActive = Boolean(category || brand || q);

  const categoryLabel = category
    ? (MAIN_CATEGORIES.find((c) => c.slug === category)?.label || category).toUpperCase()
    : 'SEMUA KOMPONEN';

  const resetAll = () => {
    setSearchParams({});
  };

  const sidebarProps = {
    category,
    brand,
    brandOptions,
    onCategory: (v) => patchParams({ category: v }),
    onBrand: (v) => patchParams({ brand: v }),
    onReset: resetAll,
    hasActive,
  };

  return (
    <div>
      {/* Sub-header: kembali | judul | jumlah | urutkan — lengket di bawah navbar */}
      <div className="bg-surface border-b border-border sticky top-[59px] max-md:top-[48px] z-40">
        <div className="flex items-center gap-4 px-6 py-1 max-md:px-4 max-md:flex-wrap max-md:gap-2">
          <Link
            to="/builder"
            aria-label="Kembali ke Builder"
            title="Kembali ke Builder"
            className="inline-flex items-center justify-center bg-subtle text-secondary border border-border rounded-full w-7 h-7 no-underline shrink-0"
          >
            <ChevronLeft className="w-4 h-4" />
          </Link>
          <h1 className="text-[0.8rem] font-extrabold tracking-[0.06em] text-ink m-0 leading-none">{categoryLabel}</h1>
          <DesktopSearchBar
            value={input}
            onChange={setInput}
            onSubmit={(v) => patchParams({ q: v.trim() })}
            onFilterClick={() => setMobileOpen((v) => !v)}
            filterClassName="md:hidden"
            className="md:ml-auto"
          />
          <div className="flex items-center gap-3 max-md:gap-2 max-md:w-full">
            <span className="bg-subtle text-primary rounded-full px-3 py-[0.2rem] text-[0.8rem] font-bold whitespace-nowrap leading-tight">
              {loading ? '…' : `${total} produk`}
            </span>
            <label className="inline-flex items-center gap-2 bg-surface border border-border rounded-full px-3 py-0.5 text-[0.8rem] text-secondary whitespace-nowrap">
              Urutkan:
              <select
                value={SORTS.some((s) => s.value === sort) ? sort : 'unggulan'}
                onChange={(e) => patchParams({ sort: e.target.value === 'unggulan' ? '' : e.target.value })}
                aria-label="Urutkan produk"
                className="border-0 outline-none bg-transparent font-bold text-ink cursor-pointer"
              >
                {SORTS.map((s) => (
                  <option key={s.value} value={s.value}>
                    {s.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </div>
      </div>

      <div className="flex items-stretch">
        {/* Sidebar filter */}
        <aside className="hidden md:block w-[260px] shrink-0 bg-surface border-r border-border p-6">
          <SidebarFilters {...sidebarProps} />
        </aside>

        {/* Grid produk (card tidak diubah) */}
        <section className="flex-1 min-w-0 p-6 max-md:p-4 max-md:pb-20" aria-live="polite">
          {mobileOpen && (
            <div className="md:hidden bg-surface border border-border rounded-[20px] p-5 mb-4">
              <SidebarFilters {...sidebarProps} />
            </div>
          )}
          {error ? (
            <ErrorState message={error.message} onRetry={() => window.location.reload()} />
          ) : loading ? (
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 max-md:gap-3">
              {Array.from({ length: 10 }).map((_, i) => (
                <Skeleton key={i} height="280px" />
              ))}
            </div>
          ) : total === 0 ? (
            <EmptyState title="Tidak ada produk yang cocok">
              <p className="text-[0.85rem]">Coba ubah kata kunci atau filter yang aktif.</p>
              <button className={btnClasses('secondary', 'sm')} onClick={resetAll}>
                Reset filter
              </button>
            </EmptyState>
          ) : (
            <>
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 max-md:gap-3">
                {visible.map((p) => {
                  const id = p._id || p.id;
                  // content-visibility: kartu di luar area display tidak dirender browser.
                  return (
                    <div key={id} className="[content-visibility:auto] [contain-intrinsic-size:320px]">
                      <ProductCard product={p} />
                    </div>
                  );
                })}
              </div>
              {limit < total && <div ref={sentinelRef} aria-hidden="true" className="h-4" />}
            </>
          )}
        </section>
      </div>
    </div>
  );
}

export default function Katalog() {
  return (
    <Shell hideFooter>
      <KatalogInner />
    </Shell>
  );
}
