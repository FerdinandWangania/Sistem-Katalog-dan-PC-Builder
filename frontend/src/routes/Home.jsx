import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import {
  ArrowRight,
  Check,
  Cpu,
  House,
  LayoutGrid,
  Plus,
  Search,
  SlidersHorizontal,
  User,
} from 'lucide-react';
import { api, ownerParams } from '../lib/api.js';
import { get as getData } from '../lib/data.js';
import { discountPercent, formatIDR, isExternal, promoLink } from '../lib/catalog.js';
import { ProductCard } from '../components/ProductCard.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { Shell } from '../components/Layout.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import { btnClasses } from '../components/Button.jsx';

function Hero({ promos }) {
  if (!promos?.length) {
    return (
      <div className="bg-ink text-surface px-8 py-12 rounded-[28px] text-center max-md:px-5 max-md:py-8 max-md:rounded-[20px]">
        <h1>Rakit PC Impianmu</h1>
        <p className="text-subtle">Katalog komponen + PC Builder dengan cek kompatibilitas otomatis.</p>
        <Link to="/builder" className={btnClasses('primary')}>
          Mulai Merakit
        </Link>
      </div>
    );
  }
  const promo = promos[0];
  const href = promoLink(promo);
  const inner = promo.banner_image ? (
    <img src={promo.banner_image} alt={promo.title || 'Promo'} className="w-full block aspect-[3/1] object-cover max-md:aspect-[16/10]" />
  ) : (
    <div className="bg-ink text-surface px-8 py-12 rounded-[28px] text-center max-md:px-5 max-md:py-8 max-md:rounded-[20px]">
      <h1>{promo.title || 'Promo Spesial'}</h1>
      <p className="text-subtle">{promo.discount_percentage ? `Diskon hingga ${promo.discount_percentage}%` : 'Penawaran terbatas.'}</p>
    </div>
  );
  return isExternal(href) ? (
    <a className="block rounded-[28px] overflow-hidden no-underline" href={href} target="_blank" rel="noreferrer">
      {inner}
    </a>
  ) : (
    <Link className="block rounded-[28px] overflow-hidden no-underline" to={href}>
      {inner}
    </Link>
  );
}

function packageSpecs(pkg) {
  const items = pkg.items || [];
  return items
    .slice(0, 3)
    .map((it) => it.product?.name || it.product_id)
    .filter(Boolean);
}

// ---------- Mobile-only blocks (reference: mobile-first homepage structure) ----------

const RECENT_KEY = 'bw_recent_search';
const WISH_KEY = 'bw_wishlist';

function loadList(key) {
  try {
    return JSON.parse(localStorage.getItem(key) || '[]');
  } catch {
    return [];
  }
}

function MobileSearch() {
  const navigate = useNavigate();
  const [q, setQ] = useState('');
  const [recent, setRecent] = useState(loadList(RECENT_KEY));
  const submit = (value) => {
    const v = (value ?? q).trim();
    if (!v) return;
    const next = [v, ...recent.filter((r) => r !== v)].slice(0, 5);
    setRecent(next);
    try {
      localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch {
      // abaikan bila storage tidak tersedia
    }
    navigate(`/katalog?q=${encodeURIComponent(v)}`);
  };
  return (
    <div className="hidden max-md:block max-md:mb-5">
      <div className="flex items-center gap-[0.6rem] bg-surface border border-border rounded-full px-4 py-[0.6rem] text-muted">
        <Search className="w-5 h-5" />
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') submit();
          }}
          placeholder="Search for products, brands..."
          aria-label="Cari produk"
          className="flex-1 border-0 outline-none bg-transparent text-[0.9rem] text-ink"
        />
        <Link to="/katalog" aria-label="Filter katalog" className="text-muted inline-flex">
          <SlidersHorizontal className="w-5 h-5" />
        </Link>
      </div>
      {recent.length > 0 && (
        <div className="flex gap-[0.4rem] flex-wrap mt-[0.6rem]">
          {recent.map((r) => (
            <button
              key={r}
              onClick={() => submit(r)}
              className="bg-subtle border-0 rounded-full px-[0.8rem] py-[0.35rem] text-[0.8rem] text-secondary"
            >
              {r}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function MobileFeatured({ products, wishlist, onWish, onAdd }) {
  if (!products?.length) return null;
  // No own heading: reuses the PRODUK POPULER heading + chips above (one section).
  return (
      <div className="hidden max-md:block">
        <div className="grid grid-cols-2 gap-3 mt-4">
        {products.slice(0, 8).map((p) => {
          const id = p._id || p.id;
          return (
            <ProductCard
              key={id}
              product={p}
              onAdd={onAdd}
              wishlisted={wishlist.includes(id)}
              onWish={onWish}
              minimal
            />
          );
        })}
      </div>
    </div>
  );
}

function MobileBottomNav() {
  const { pathname } = useLocation();
  // Semua menu navbar pindah ke bawah, kecuali keranjang (tetap di atas).
  const items = [
    { to: '/', label: 'Home', icon: House },
    { to: '/katalog', label: 'Katalog', icon: LayoutGrid },
    { to: '/builder', label: 'Builder', icon: Cpu },
    { to: '/login', label: 'Account', icon: User },
  ];
  return (
    <nav className="hidden max-md:flex fixed left-0 right-0 bottom-0 z-[60] bg-surface border-t border-border px-4 pt-[0.6rem] pb-[calc(0.6rem+env(safe-area-inset-bottom))]">
      {items.map((it) => (
        <Link
          key={it.to}
          to={it.to}
          className={`flex-1 flex flex-col items-center gap-[0.15rem] no-underline text-[0.7rem] font-semibold ${
            pathname === it.to ? 'text-primary' : 'text-muted'
          }`}
        >
          <it.icon className="w-5 h-5" />
          {it.label}
        </Link>
      ))}
    </nav>
  );
}

function HomeInner() {
  const toast = useToast();
  const [promos, setPromos] = useState(null);
  const [products, setProducts] = useState(null);
  const [packages, setPackages] = useState(null);
  const [error, setError] = useState(null);
  const [wishlist, setWishlist] = useState(loadList(WISH_KEY));

  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const [promoRes, packRes, prodRes] = await Promise.all([
          getData('/promotions'),
          getData('/packages'),
          getData('/products'),
        ]);
        if (!alive) return;
        setPromos(promoRes.data || []);
        setPackages(packRes.data || []);
        setProducts(prodRes.data || []);
      } catch (e) {
        if (alive) setError(e);
      }
    })();
    return () => {
      alive = false;
    };
  }, []);

  const addProduct = async (product) => {
    try {
      await api.post('/cart/items', {
        body: { product_id: product._id || product.id, qty: 1, source_type: 'catalog', ...ownerParams() },
      });
      toast.success(`${product.name} masuk keranjang.`);
    } catch (e) {
      toast.error(e.message || 'Gagal menambah ke keranjang.');
    }
  };

  const addPackage = async (pkg) => {
    try {
      await api.post(`/packages/${pkg._id || pkg.id}/add-to-cart`, { body: { ...ownerParams() } });
      toast.success(`${pkg.name} masuk keranjang.`);
    } catch (e) {
      toast.error(e.message || 'Gagal menambah paket.');
    }
  };

  const toggleWish = (id) => {
    const next = wishlist.includes(id) ? wishlist.filter((w) => w !== id) : [...wishlist, id];
    setWishlist(next);
    try {
      localStorage.setItem(WISH_KEY, JSON.stringify(next));
    } catch {
      // abaikan bila storage tidak tersedia
    }
  };

  const loading = promos === null || products === null || packages === null;
  const popular = [...(products || [])]
    .sort((a, b) => discountPercent(b) - discountPercent(a))
    .slice(0, 8);

  return (
    <div className="max-w-[1200px] mx-auto p-6 max-md:p-4 max-md:pb-20">
      {/* Mobile-first blocks (hidden on desktop) */}
      <MobileSearch />
      {error ? (
        <ErrorState message={error.message} onRetry={() => window.location.reload()} />
      ) : loading ? (
        <Skeleton height="280px" />
      ) : (
        <Hero promos={promos} />
      )}

      <section className="my-10 max-md:my-7">
        <h2 className="relative pb-[0.4rem] text-ink text-center after:content-[''] after:absolute after:left-1/2 after:-translate-x-1/2 after:bottom-0 after:w-12 after:h-[3px] after:bg-primary after:rounded-sm">
          REKOMENDASI PC RAKITAN
        </h2>
        {packages === null ? (
          <Skeleton height="120px" />
        ) : packages.length === 0 ? (
          <EmptyState title="Belum ada paket rakitan" />
        ) : (
          <div className="grid grid-cols-3 gap-4 max-w-[1000px] mx-auto mt-5 max-[900px]:flex max-[900px]:gap-3 max-[900px]:overflow-x-auto max-[900px]:pb-2 max-[900px]:max-w-none max-[900px]:-mx-4 max-[900px]:px-4">
            {packages.slice(0, 3).map((pkg) => {
              const img = pkg.items?.[0]?.product?.image;
              return (
                <div
                  key={pkg._id || pkg.id}
                  className="bg-surface border border-border rounded-[20px] overflow-hidden flex flex-col max-[900px]:flex-[0_0_16rem]"
                >
                  {img ? (
                    <img src={img} alt={pkg.name} loading="lazy" className="w-full aspect-[4/3] object-cover block bg-subtle" />
                  ) : (
                    <div className="w-full aspect-[4/3] bg-subtle flex items-center justify-center text-muted font-bold">
                      {pkg.tag || 'Paket'}
                    </div>
                  )}
                  <div className="px-4 pt-[0.9rem] pb-[1.1rem] flex flex-col gap-[0.3rem] flex-1">
                    <Link to={`/paket/${pkg._id || pkg.id}`} className="font-bold no-underline text-ink">
                      {pkg.name}
                    </Link>
                    <strong>{formatIDR(pkg.total_price)}</strong>
                    <ul className="list-none my-2 p-0 text-[0.8rem] text-secondary">
                      {packageSpecs(pkg).map((s) => (
                        <li key={s} className="py-[0.1rem] flex items-center gap-[0.35rem]">
                          <Check className="w-4 h-4 text-success" />
                          {s}
                        </li>
                      ))}
                    </ul>
                    <button className={btnClasses('primary', null, 'w-full mt-auto text-[0.8rem] px-4 py-[0.6rem]')} onClick={() => addPackage(pkg)}>
                      <Plus className="w-4 h-4" /> TAMBAH KE KERANJANG
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      <section className="my-10 max-md:my-7">
        <div className="flex justify-between items-baseline max-md:flex-wrap max-md:gap-2">
          <h2 className="relative pb-[0.4rem] text-ink after:content-[''] after:absolute after:left-0 after:bottom-0 after:w-12 after:h-[3px] after:bg-primary after:rounded-sm">
            PRODUK POPULER
          </h2>
          <Link to="/katalog" className="text-primary font-bold no-underline inline-flex items-center gap-[0.3rem] max-md:hidden">
            LIHAT SEMUA PRODUK <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
        {!loading && !error && (
          <MobileFeatured products={popular} wishlist={wishlist} onWish={toggleWish} onAdd={addProduct} />
        )}
        <div className="max-md:hidden">
        {loading ? (
          <Skeleton height="200px" />
        ) : popular.length === 0 ? (
          <EmptyState title="Belum ada produk di kategori ini" />
        ) : (
          <div className="grid grid-cols-4 gap-4 mt-5 max-[900px]:grid-cols-2 max-md:gap-3">
            {popular.map((p) => (
              <ProductCard key={p._id || p.id} product={p} onAdd={addProduct} />
            ))}
          </div>
        )}
        </div>
      </section>

      <MobileBottomNav />
    </div>
  );
}

export default function Home() {
  return (
    <Shell>
      <ToastProvider>
        <HomeInner />
      </ToastProvider>
    </Shell>
  );
}
