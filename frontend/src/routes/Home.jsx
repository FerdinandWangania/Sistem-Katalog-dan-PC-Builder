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
import './Home.css';

function Hero({ promos }) {
  if (!promos?.length) {
    return (
      <div className="bw-hero-fallback">
        <h1>Rakit PC Impianmu</h1>
        <p>Katalog komponen + PC Builder dengan cek kompatibilitas otomatis.</p>
        <Link to="/builder" className="bw-btn bw-btn-primary">
          Mulai Merakit
        </Link>
      </div>
    );
  }
  const promo = promos[0];
  const href = promoLink(promo);
  const inner = promo.banner_image ? (
    <img src={promo.banner_image} alt={promo.title || 'Promo'} />
  ) : (
    <div className="bw-hero-fallback">
      <h1>{promo.title || 'Promo Spesial'}</h1>
      <p>{promo.discount_percentage ? `Diskon hingga ${promo.discount_percentage}%` : 'Penawaran terbatas.'}</p>
    </div>
  );
  return isExternal(href) ? (
    <a className="bw-hero" href={href} target="_blank" rel="noreferrer">
      {inner}
    </a>
  ) : (
    <Link className="bw-hero" to={href}>
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
    <div className="bw-m-search">
      <div className="bw-m-searchbar">
        <Search className="w-5 h-5" />
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') submit();
          }}
          placeholder="Search for products, brands..."
          aria-label="Cari produk"
        />
        <Link to="/katalog" aria-label="Filter katalog">
          <SlidersHorizontal className="w-5 h-5" />
        </Link>
      </div>
      {recent.length > 0 && (
        <div className="bw-m-recent">
          {recent.map((r) => (
            <button key={r} onClick={() => submit(r)}>
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
    <div className="bw-m-block">
      <div className="bw-m-row">
        {products.slice(0, 8).map((p) => {
          const id = p._id || p.id;
          return (
            <ProductCard
              key={id}
              product={p}
              onAdd={onAdd}
              wishlisted={wishlist.includes(id)}
              onWish={onWish}
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
    <nav className="bw-m-bottomnav">
      {items.map((it) => (
        <Link key={it.to} to={it.to} className={pathname === it.to ? 'active' : ''}>
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
    <div className="bw-home">
      {/* Mobile-first blocks (hidden on desktop) */}
      <MobileSearch />
      {error ? (
        <ErrorState message={error.message} onRetry={() => window.location.reload()} />
      ) : loading ? (
        <Skeleton height="280px" />
      ) : (
        <Hero promos={promos} />
      )}

      <section>
        <h2 className="bw-section-title bw-centered">REKOMENDASI PC RAKITAN</h2>
        {packages === null ? (
          <Skeleton height="120px" />
        ) : packages.length === 0 ? (
          <EmptyState title="Belum ada paket rakitan" />
        ) : (
          <div className="bw-grid-packages">
            {packages.slice(0, 3).map((pkg) => {
              const img = pkg.items?.[0]?.product?.image;
              return (
                <div key={pkg._id || pkg.id} className="bw-package-card">
                  {img ? (
                    <img src={img} alt={pkg.name} loading="lazy" />
                  ) : (
                    <div className="bw-product-img-fallback">{pkg.tag || 'Paket'}</div>
                  )}
                  <div className="bw-product-body">
                    <Link to={`/paket/${pkg._id || pkg.id}`} className="bw-product-name">
                      {pkg.name}
                    </Link>
                    <strong>{formatIDR(pkg.total_price)}</strong>
                    <ul className="bw-package-specs">
                      {packageSpecs(pkg).map((s) => (
                        <li key={s}>
                          <Check className="w-4 h-4 text-success" />
                          {s}
                        </li>
                      ))}
                    </ul>
                    <button className="bw-btn bw-btn-primary bw-btn-block" onClick={() => addPackage(pkg)}>
                      <Plus className="w-4 h-4" /> TAMBAH KE KERANJANG
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      <section>
        <div className="bw-section-head">
          <h2 className="bw-section-title">PRODUK POPULER</h2>
          <Link to="/katalog" className="bw-see-all">
            LIHAT SEMUA PRODUK <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
        {!loading && !error && (
          <MobileFeatured products={popular} wishlist={wishlist} onWish={toggleWish} onAdd={addProduct} />
        )}
        <div className="bw-desktop-only">
        {loading ? (
          <Skeleton height="200px" />
        ) : popular.length === 0 ? (
          <EmptyState title="Belum ada produk di kategori ini" />
        ) : (
          <div className="bw-grid-products">
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
