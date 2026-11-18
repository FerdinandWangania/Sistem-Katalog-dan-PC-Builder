import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { ArrowLeft, ChevronLeft, Minus, Plus, Star, Zap } from 'lucide-react';
import { get as getData } from '../lib/data.js';
import { addToCart } from '../lib/cart.js';
import { discountPercent, effectivePrice, formatIDR } from '../lib/catalog.js';
import { ProductCard, specLabel } from '../components/ProductCard.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { Shell } from '../components/Layout.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import { btnClasses } from '../components/Button.jsx';

// Blurb generik per kategori (fakta umum, bukan klaim produk).
const CATEGORY_BLURB = {
  CPU: 'Prosesor (CPU) adalah otak komputer yang mengeksekusi instruksi dan menentukan kecepatan pemrosesan sistem.',
  Motherboard: 'Motherboard adalah papan utama yang menghubungkan seluruh komponen komputer agar bekerja sebagai satu sistem.',
  RAM: 'RAM (memori) menyimpan data kerja sementara agar aplikasi berjalan responsif saat multitasking.',
  GPU: 'Kartu grafis (GPU) mengolah tampilan visual, gaming, dan beban kerja kreatif seperti rendering dan editing video.',
  SSD: 'SSD adalah media penyimpanan cepat berbasis flash untuk booting dan loading yang jauh lebih responsif.',
  PSU: 'Power Supply (PSU) menyalurkan daya listrik yang stabil ke seluruh komponen komputer.',
  Case: 'Casing melindungi komponen sekaligus mengatur aliran udara dan tampilan rakitan PC.',
};

function fullSpecs(product) {
  return Object.entries(product?.specs || {}).map(([k, v]) => ({ label: specLabel(k), value: String(v) }));
}

function powerOf(product) {
  const s = product?.specs || {};
  const raw = s.tdp ?? s.wattage ?? s.wattage_w ?? s.power;
  if (raw === undefined || raw === null || raw === '') return null;
  const text = String(raw);
  return /w$/i.test(text) || /^\d+$/.test(text) ? `${text.replace(/w$/i, '')}W` : text;
}

function DetailInner() {
  const toast = useToast();
  const navigate = useNavigate();
  const { id } = useParams();
  const [searchParams] = useSearchParams();

  // Dua mode masuk: katalog (default) vs builder (?mode=builder&slot=CPU&return=/builder).
  const isBuilder = searchParams.get('mode') === 'builder';
  const slot = searchParams.get('slot') || '';
  const returnTo = searchParams.get('return') || '/builder';

  const [product, setProduct] = useState(null);
  const [error, setError] = useState(null);
  const [activeImg, setActiveImg] = useState(0);
  const [qty, setQty] = useState(1);
  const [tab, setTab] = useState('deskripsi');
  const [related, setRelated] = useState(null);
  const [variantIdx, setVariantIdx] = useState(0);
  const stripRef = useRef(null);

  useEffect(() => {
    let alive = true;
    setError(null);
    setProduct(null);
    setActiveImg(0);
    setQty(1);
    setTab('deskripsi');
    setRelated(null);
    setVariantIdx(0);
    (async () => {
      try {
        const res = await getData(`/products/${id}`);
        if (alive) setProduct(res.data || null);
      } catch (e) {
        if (alive) {
          setError(e);
          setProduct(null);
        }
      }
    })();
    return () => {
      alive = false;
    };
  }, [id]);

  // Produk terkait: kategori sama, kecuali produk ini.
  useEffect(() => {
    if (!product?.category) return;
    let alive = true;
    (async () => {
      try {
        const res = await getData('/products', { query: { category: product.category } });
        if (!alive) return;
        const mine = product._id || product.id;
        setRelated((res.data || []).filter((p) => (p._id || p.id) !== mine).slice(0, 4));
      } catch {
        if (alive) setRelated([]);
      }
    })();
    return () => {
      alive = false;
    };
  }, [product]);

  const loading = product === null && !error;
  const pct = discountPercent(product);
  const stock = product?.stock ?? 0;
  const outOfStock = stock <= 0;
  const maxQty = Math.max(1, stock);
  const images = Array.isArray(product?.images) && product.images.length > 0 ? product.images : product?.image ? [product.image] : [];
  const specs = fullSpecs(product);
  const power = powerOf(product);
  const blurb = CATEGORY_BLURB[product?.category] || '';
  const descSpec = specs
    .slice(0, 3)
    .map((s) => `${s.label} ${s.value}`)
    .join(', ');

  // Varian eksplisit dari data produk (product.variants).
  // Tanpa data varian -> seksi dikosongkan (tidak ditampilkan sama sekali).
  const variantOptions = (Array.isArray(product?.variants) ? product.variants : [])
    .map((v) => (typeof v === 'string' ? { label: v } : v))
    .filter((v) => v && v.label);

  const chooseVariant = (i) => {
    setVariantIdx(i);
    const v = variantOptions[i];
    const currentId = product._id || product.id;
    if (v?.product_id && v.product_id !== currentId) {
      const qs = searchParams.toString();
      navigate(`/produk/${v.product_id}${qs ? `?${qs}` : ''}`);
    }
  };

  const goBack = () => {
    if (window.history.length > 1) navigate(-1);
    else navigate(isBuilder ? returnTo : '/katalog');
  };

  const chooseComponent = () => {
    const [base, qs] = returnTo.split('?');
    const params = new URLSearchParams(qs || '');
    if (slot) params.set('slot', slot);
    params.set('product', id);
    navigate(`${base}?${params.toString()}`);
  };

  const add = async () => {
    try {
      await addToCart({ product_id: product._id || product.id, qty, source_type: 'catalog' });
      toast.success(`${product.name} ×${qty} masuk keranjang.`);
    } catch (e) {
      toast.error(e.message || 'Gagal menambah ke keranjang.');
    }
  };

  return (
    <div className="max-w-[1200px] mx-auto p-6 max-md:p-4 max-md:pb-20">
      {/* Bar atas: kembali (kiri) + breadcrumb (kanan) */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <button
          onClick={goBack}
          className="inline-flex items-center gap-1 bg-transparent border-0 p-0 text-primary text-[0.85rem] font-semibold cursor-pointer"
        >
          <ChevronLeft className="w-4 h-4" /> Kembali ke {isBuilder ? 'Builder' : 'Katalog'}
        </button>
        <nav aria-label="breadcrumb" className="flex items-center gap-2 flex-wrap text-[0.82rem]">
          <Link to="/" className="text-secondary no-underline">
            Beranda
          </Link>
          <span className="text-muted">/</span>
          <Link to="/katalog" className="text-secondary no-underline">
            Katalog
          </Link>
          <span className="text-muted">/</span>
          {product?.category ? (
            <>
              <Link to={`/katalog?category=${encodeURIComponent(product.category)}`} className="text-secondary no-underline">
                {product.category}
              </Link>
              <span className="text-muted">/</span>
            </>
          ) : null}
          <span aria-current="page" className="text-ink font-bold">
            {product?.name || 'Detail Produk'}
          </span>
        </nav>
      </div>

      {error ? (
        error?.status === 404 ? (
          <EmptyState title="Produk tidak ditemukan">
            <p className="text-[0.85rem]">Produk yang kamu cari tidak ada atau sudah dihapus.</p>
            <div className="flex gap-2 justify-center mt-2">
              <Link to="/katalog" className={btnClasses('primary', 'sm')}>
                <ArrowLeft className="w-4 h-4" /> Kembali ke Katalog
              </Link>
              <Link to="/" className={btnClasses('secondary', 'sm')}>
                Home
              </Link>
            </div>
          </EmptyState>
        ) : (
          <ErrorState message={error.message} onRetry={() => window.location.reload()} />
        )
      ) : loading ? (
        <div className="grid grid-cols-2 gap-8 mt-6 max-md:grid-cols-1">
          <Skeleton height="380px" />
          <div className="flex flex-col gap-3">
            <Skeleton height="1.5rem" />
            <Skeleton height="2.5rem" />
            <Skeleton height="120px" />
          </div>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-10 mt-6 max-md:grid-cols-1 max-md:gap-5">
            {/* Galeri */}
            <div className="bg-surface border border-border rounded-[24px] p-8 max-md:p-5 h-fit">
              <div className="relative">
                {images.length > 0 ? (
                  <img
                    src={images[activeImg]}
                    alt={product.name}
                    className="w-full aspect-square object-cover block rounded-2xl bg-subtle"
                  />
                ) : (
                  <div className="w-full aspect-square bg-subtle rounded-2xl flex items-center justify-center text-muted font-bold">
                    {product.category}
                  </div>
                )}
              </div>
              {images.length > 1 && (
                <div className="flex items-center gap-2 mt-4">
                  <button
                    aria-label="Geser thumbnail ke kiri"
                    onClick={() => stripRef.current?.scrollBy({ left: -160, behavior: 'smooth' })}
                    className="inline-flex shrink-0 items-center justify-center w-8 h-8 rounded-full bg-surface border border-border text-secondary cursor-pointer"
                  >
                    <ChevronLeft className="w-4 h-4" />
                  </button>
                  <div ref={stripRef} className="flex gap-2 overflow-x-auto flex-1 py-1">
                    {images.map((src, i) => (
                      <button
                        key={src + i}
                        onClick={() => setActiveImg(i)}
                        aria-label={`Lihat gambar ${i + 1}`}
                        aria-pressed={activeImg === i}
                        className={`border rounded-xl overflow-hidden w-20 aspect-square p-0 shrink-0 cursor-pointer ${
                          activeImg === i ? 'border-primary' : 'border-border'
                        }`}
                      >
                        <img src={src} alt="" className="w-full h-full object-cover block" />
                      </button>
                    ))}
                  </div>
                  <button
                    aria-label="Geser thumbnail ke kanan"
                    onClick={() => stripRef.current?.scrollBy({ left: 160, behavior: 'smooth' })}
                    className="inline-flex shrink-0 items-center justify-center w-8 h-8 rounded-full bg-ink text-surface border-0 cursor-pointer"
                  >
                    <ChevronRight className="w-4 h-4" />
                  </button>
                </div>
              )}
            </div>

            {/* Info */}
            <div className="flex flex-col gap-3">
              <h1 className="m-0 text-[1.9rem] leading-tight max-md:text-[1.4rem]">{product.name}</h1>

              <div className="flex items-center gap-2 flex-wrap text-[0.85rem]">
                <div className="flex gap-[0.15rem]" role="img" aria-label="Belum ada rating">
                  {[0, 1, 2, 3, 4].map((i) => (
                    <Star key={i} className="w-4 h-4 text-warning fill-current" />
                  ))}
                </div>
                <span className="text-muted">Belum ada ulasan</span>
                <span className="text-border">|</span>
                <span className="text-primary font-semibold cursor-not-allowed" title="Fitur tulis ulasan belum tersedia">
                  Tambah ulasan Anda
                </span>
              </div>

              <div className="mt-1">
                {pct > 0 && (
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="line-through text-muted text-[0.95rem]">{formatIDR(product.price)}</span>
                    <span className="bg-danger text-surface text-[0.75rem] font-extrabold px-2 py-[0.15rem] rounded-full">
                      -{pct}%
                    </span>
                  </div>
                )}
                <strong className="text-danger text-[1.7rem] leading-tight">{formatIDR(effectivePrice(product))}</strong>
              </div>

              <div className="flex items-center gap-2 flex-wrap text-[0.85rem]">
                <span className={`inline-flex items-center gap-[0.35rem] font-bold ${outOfStock ? 'text-danger' : 'text-success'}`}>
                  <span className={`w-2 h-2 rounded-full ${outOfStock ? 'bg-danger' : 'bg-success'}`} />
                  {outOfStock ? 'Stok habis' : `Total stok: ${stock} unit`}
                </span>
                {power && (
                  <span className="inline-flex items-center gap-1 bg-subtle text-primary font-bold px-3 py-[0.2rem] rounded-full">
                    <Zap className="w-3.5 h-3.5" /> Daya: {power}
                  </span>
                )}
                {!outOfStock && stock < 5 && <span className="text-warning font-semibold">Stok terbatas</span>}
              </div>

              {variantOptions.length > 0 && (
                <div>
                  <p className="m-0 text-[0.95rem] font-extrabold text-ink">
                    Pilih varian: <span className="text-secondary font-semibold">{variantOptions[variantIdx]?.label}</span>
                  </p>
                  <div className="flex flex-wrap gap-2 mt-2">
                    {variantOptions.map((v, i) => {
                      const mine = i === variantIdx;
                      const thumb = v.image || product.image;
                      return (
                        <button
                          key={`${v.label}-${i}`}
                          onClick={() => chooseVariant(i)}
                          aria-pressed={mine}
                          title={v.label}
                          className={`inline-flex items-center gap-2 rounded-2xl border px-3 py-2 text-[0.85rem] font-semibold ${
                            mine
                              ? 'border-success text-success-ink bg-success-bg cursor-default'
                              : 'border-border text-secondary bg-surface cursor-pointer hover:bg-subtle'
                          }`}
                        >
                          {thumb ? (
                            <img src={thumb} alt="" className="w-7 h-7 rounded-lg object-cover block" />
                          ) : (
                            <span className="w-7 h-7 rounded-lg bg-subtle inline-flex items-center justify-center text-[0.7rem] font-bold text-muted">
                              {(product.brand || '?')[0]}
                            </span>
                          )}
                          <span className="max-w-32 truncate">{v.label}</span>
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}

              {isBuilder ? (
                <div className="flex flex-col gap-2 mt-1">
                  <button className={btnClasses('primary', null, 'w-full py-3 text-[0.85rem] tracking-[0.08em]')} onClick={chooseComponent} disabled={outOfStock}>
                    {outOfStock ? 'STOK HABIS' : slot ? `PILIH UNTUK ${slot.toUpperCase()}` : 'PILIH KOMPONEN'}
                  </button>
                  <button className={btnClasses('secondary', null, 'w-full')} onClick={goBack}>
                    <ArrowLeft className="w-4 h-4" /> Kembali{slot ? ` ke slot ${slot}` : ''}
                  </button>
                </div>
              ) : (
                <div className="flex gap-3 mt-1 max-md:flex-col">
                  <div className="inline-flex items-center gap-3 bg-surface border border-border rounded-full px-3 py-2 shrink-0 self-start max-md:self-stretch max-md:justify-between">
                    <button
                      aria-label="Kurangi jumlah"
                      disabled={qty <= 1 || outOfStock}
                      onClick={() => setQty((v) => Math.max(1, v - 1))}
                      className="inline-flex bg-transparent border-0 p-1 text-ink cursor-pointer disabled:opacity-30 disabled:cursor-default"
                    >
                      <Minus className="w-4 h-4" />
                    </button>
                    <span aria-live="polite" className="min-w-8 text-center font-bold">
                      {String(qty).padStart(2, '0')}
                    </span>
                    <button
                      aria-label="Tambah jumlah"
                      disabled={qty >= maxQty || outOfStock}
                      onClick={() => setQty((v) => Math.min(maxQty, v + 1))}
                      className="inline-flex bg-transparent border-0 p-1 text-ink cursor-pointer disabled:opacity-30 disabled:cursor-default"
                    >
                      <Plus className="w-4 h-4" />
                    </button>
                  </div>
                  <button className={btnClasses('primary', null, 'flex-1 py-3 text-[0.85rem] tracking-[0.08em]')} onClick={add} disabled={outOfStock}>
                    {outOfStock ? 'STOK HABIS' : 'TAMBAH KE KERANJANG'}
                  </button>
                </div>
              )}
            </div>
          </div>

          {/* Tab Deskripsi / Ulasan */}
          <div className="bg-surface border border-border rounded-[28px] px-8 py-6 mt-10 max-md:mt-7 max-md:px-5">
            <div className="flex justify-center gap-10 border-b border-border" role="tablist" aria-label="Info produk">
              <button
                role="tab"
                aria-selected={tab === 'deskripsi'}
                onClick={() => setTab('deskripsi')}
                className={`bg-transparent border-0 border-b-[3px] pb-3 px-2 text-[0.85rem] font-extrabold tracking-[0.06em] cursor-pointer -mb-px ${
                  tab === 'deskripsi' ? 'text-danger border-danger' : 'text-secondary border-transparent'
                }`}
              >
                DESKRIPSI
              </button>
              <button
                role="tab"
                aria-selected={tab === 'spesifikasi'}
                onClick={() => setTab('spesifikasi')}
                className={`bg-transparent border-0 border-b-[3px] pb-3 px-2 text-[0.85rem] font-extrabold tracking-[0.06em] cursor-pointer -mb-px ${
                  tab === 'spesifikasi' ? 'text-danger border-danger' : 'text-secondary border-transparent'
                }`}
              >
                SPESIFIKASI
              </button>
              <button
                role="tab"
                aria-selected={tab === 'ulasan'}
                onClick={() => setTab('ulasan')}
                className={`bg-transparent border-0 border-b-[3px] pb-3 px-2 text-[0.85rem] font-extrabold tracking-[0.06em] cursor-pointer -mb-px ${
                  tab === 'ulasan' ? 'text-danger border-danger' : 'text-secondary border-transparent'
                }`}
              >
                ULASAN (0)
              </button>
            </div>

            {tab === 'deskripsi' ? (
              <div className="pt-6 flex flex-col gap-4 text-secondary text-[0.92rem] leading-relaxed">
                <p className="m-0">
                  {product.name} adalah komponen {product.category} dari {product.brand}
                  {descSpec ? ` dengan spesifikasi utama meliputi ${descSpec}` : ''}. Harga yang tertera adalah{' '}
                  {formatIDR(effectivePrice(product))} per unit.
                </p>
                {blurb && <p className="m-0">{blurb}</p>}
              </div>
            ) : tab === 'spesifikasi' ? (
              <div className="pt-6">
                {specs.length === 0 ? (
                  <p className="text-secondary text-[0.9rem] m-0">Spesifikasi belum tersedia untuk produk ini.</p>
                ) : (
                  <dl className="m-0 border border-border rounded-2xl px-5 py-2">
                    {specs.map((s) => (
                      <div key={s.label} className="flex justify-between gap-4 py-2 border-b border-border last:border-0">
                        <dt className="text-secondary">{s.label}</dt>
                        <dd className="m-0 font-semibold text-right text-ink">{s.value}</dd>
                      </div>
                    ))}
                  </dl>
                )}
              </div>
            ) : (
              <div className="pt-6 text-center">
                <div className="flex gap-1 justify-center" role="img" aria-label="Belum ada rating">
                  {[0, 1, 2, 3, 4].map((i) => (
                    <Star key={i} className="w-5 h-5 text-muted" />
                  ))}
                </div>
                <p className="text-secondary text-[0.9rem]">Belum ada ulasan untuk produk ini.</p>
                <button className={btnClasses('secondary', 'sm')} disabled title="Fitur tulis ulasan belum tersedia">
                  Tulis Ulasan
                </button>
              </div>
            )}
          </div>

          {/* Produk terkait (shared card, tanpa tombol keranjang) */}
          {related !== null && related.length > 0 && (
            <section className="mt-10 max-md:mt-7">
              <h2 className="relative pb-[0.4rem] text-ink after:content-[''] after:absolute after:left-0 after:bottom-0 after:w-12 after:h-[3px] after:bg-primary after:rounded-sm">
                PRODUK TERKAIT
              </h2>
              <div className="grid grid-cols-4 gap-4 mt-5 max-[900px]:grid-cols-2 max-md:gap-3">
                {related.map((p) => (
                  <ProductCard key={p._id || p.id} product={p} />
                ))}
              </div>
            </section>
          )}
          {related === null && (
            <section className="mt-10 max-md:mt-7">
              <h2 className="relative pb-[0.4rem] text-ink after:content-[''] after:absolute after:left-0 after:bottom-0 after:w-12 after:h-[3px] after:bg-primary after:rounded-sm">
                PRODUK TERKAIT
              </h2>
              <div className="grid grid-cols-4 gap-4 mt-5 max-[900px]:grid-cols-2 max-md:gap-3">
                {Array.from({ length: 4 }).map((_, i) => (
                  <Skeleton key={i} height="300px" />
                ))}
              </div>
            </section>
          )}
        </>
      )}
    </div>
  );
}

export default function DetailProduk() {
  return (
    <Shell>
      <ToastProvider>
        <DetailInner />
      </ToastProvider>
    </Shell>
  );
}
