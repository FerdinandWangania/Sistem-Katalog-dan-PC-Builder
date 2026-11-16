import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, ownerParams } from '../lib/api.js';
import { get as getData } from '../lib/data.js';
import { discountPercent, formatIDR, isExternal, promoLink } from '../lib/catalog.js';
import { ProductCard } from '../components/ProductCard.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { Shell } from '../components/Layout.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import './Home.css';

// Tabs on PRODUK POPULER (labels follow the design; values are real category slugs).
const TABS = [
  { label: 'Semua Produk', value: '' },
  { label: 'CPU', value: 'CPU' },
  { label: 'MOBO', value: 'Motherboard' },
  { label: 'GPU', value: 'GPU' },
  { label: 'RAM', value: 'RAM' },
  { label: 'SSD', value: 'SSD' },
  { label: 'PSU', value: 'PSU' },
  { label: 'CASE', value: 'Case' },
  { label: 'COOL', value: 'Case Fan' },
];

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

function HomeInner() {
  const toast = useToast();
  const [promos, setPromos] = useState(null);
  const [products, setProducts] = useState(null);
  const [packages, setPackages] = useState(null);
  const [tab, setTab] = useState('');
  const [error, setError] = useState(null);

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

  const loading = promos === null || products === null || packages === null;
  const popular = [...(products || [])]
    .filter((p) => !tab || p.category === tab)
    .sort((a, b) => discountPercent(b) - discountPercent(a))
    .slice(0, 8);

  return (
    <div className="bw-home">
      {error ? (
        <ErrorState message={error.message} onRetry={() => window.location.reload()} />
      ) : loading ? (
        <Skeleton height="280px" />
      ) : (
        <Hero promos={promos} />
      )}

      <section>
        <div className="bw-section-head">
          <h2 className="bw-section-title">PRODUK POPULER</h2>
          <Link to="/katalog">LIHAT SEMUA PRODUK ➔</Link>
        </div>
        <div className="bw-chips">
          {TABS.map((t) => (
            <button key={t.label} className={`bw-chip${tab === t.value ? ' active' : ''}`} onClick={() => setTab(t.value)}>
              {t.label}
            </button>
          ))}
        </div>
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
      </section>

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
                        <li key={s}>✓ {s}</li>
                      ))}
                    </ul>
                    <button className="bw-btn bw-btn-primary bw-btn-block" onClick={() => addPackage(pkg)}>
                      + TAMBAH KE KERANJANG
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>
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
