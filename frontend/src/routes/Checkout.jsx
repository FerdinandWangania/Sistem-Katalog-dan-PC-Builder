import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Box,
  Check,
  ChevronDown,
  ChevronLeft,
  CircleAlert,
  CreditCard,
  MapPin,
  Package,
  Pencil,
  QrCode,
  Store,
  Truck,
  Wallet,
} from 'lucide-react';
import { getCart } from '../lib/cart.js';
import { checkoutOrder } from '../lib/orders.js';
import { effectivePrice, formatIDR } from '../lib/catalog.js';
import { Shell } from '../components/Layout.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import { btnClasses } from '../components/Button.jsx';

const ADDRESS_KEY = 'bw_address';
const QRIS_LIMIT = 10000000;
const SHIPPING_FLAT = 25000;
const CC_FEE_RATE = 0.02;

const METHODS = [
  { key: 'qris', label: 'QRIS', sub: 'QR', provider: 'midtrans', Icon: QrCode },
  { key: 'cc', label: 'Credit Card', sub: 'CC', provider: 'xendit', Icon: CreditCard },
  { key: 'va_bca', label: 'VA BCA', sub: 'VA', provider: 'bca_va', Icon: Wallet },
  { key: 'va_mandiri', label: 'VA Mandiri', sub: 'VA', provider: 'mandiri_va', Icon: Wallet },
];

function loadAddress() {
  try {
    const raw = JSON.parse(localStorage.getItem(ADDRESS_KEY) || '{}');
    return {
      name: raw.name || '',
      phone: raw.phone || '',
      street: raw.street || '',
      city: raw.city || '',
      zip: raw.zip || '',
    };
  } catch {
    return { name: '', phone: '', street: '', city: '', zip: '' };
  }
}

function thumbOf(p, product) {
  const img = product?.image || p?.image;
  const label = product?.category || 'Produk';
  if (img) return <img src={img} alt="" className="w-16 h-16 rounded-xl object-cover bg-subtle shrink-0" />;
  return (
    <span className="w-16 h-16 rounded-xl bg-subtle inline-flex items-center justify-center text-[0.6rem] font-bold text-muted shrink-0">
      {label}
    </span>
  );
}

function CheckoutInner() {
  const toast = useToast();
  const navigate = useNavigate();
  const [cart, setCart] = useState(null);
  const [error, setError] = useState(null);
  const [address, setAddress] = useState(loadAddress);
  const [editingAddress, setEditingAddress] = useState(() => {
    const a = loadAddress();
    return !(a.name && a.phone && a.street && a.city);
  });
  const [fieldErrors, setFieldErrors] = useState({});
  const [shipping, setShipping] = useState('kurir'); // kurir | pickup
  const [method, setMethod] = useState('va_bca');
  const [showAllMethods, setShowAllMethods] = useState(false);
  const [busy, setBusy] = useState(false);
  const [buildErrors, setBuildErrors] = useState([]);

  useEffect(() => {
    (async () => {
      try {
        const res = await getCart();
        setCart(res.data);
      } catch (e) {
        setError(e);
      }
    })();
  }, []);

  const catalogItems = cart?.catalog_items || [];
  const builds = [...(cart?.builder_builds || []), ...(cart?.package_builds || [])];
  const itemCount = (cart?.item_count ?? catalogItems.reduce((s, i) => s + (Number(i.qty) || 0), 0) + builds.reduce((s, b) => s + (b.components || []).length, 0));

  const subtotal = cart?.total_price ?? 0;
  const ongkir = shipping === 'pickup' ? 0 : SHIPPING_FLAT;
  const fee = method === 'cc' ? Math.round((subtotal + ongkir) * CC_FEE_RATE) : 0;
  const total = subtotal + ongkir + fee;
  const qrisBlocked = total > QRIS_LIMIT;
  const methodCfg = METHODS.find((m) => m.key === method) || METHODS[2];
  const visibleMethods = showAllMethods ? METHODS : METHODS.slice(0, 3);

  const patchAddress = (k, v) => {
    setAddress((a) => ({ ...a, [k]: v }));
    setFieldErrors((e) => ({ ...e, [k]: null }));
  };

  const validateAddress = () => {
    const errs = {};
    if (!address.name.trim()) errs.name = 'Nama penerima wajib diisi.';
    if (!address.phone.trim()) errs.phone = 'No. telepon wajib diisi.';
    else if (!/^[0-9+()\-.\\s]{9,16}$/.test(address.phone.trim())) errs.phone = 'No. telepon tidak valid (9–16 digit).';
    if (!address.street.trim()) errs.street = 'Alamat lengkap wajib diisi.';
    if (!address.city.trim()) errs.city = 'Kota wajib diisi.';
    if (address.zip && !/^[0-9]{5}$/.test(address.zip.trim())) errs.zip = 'Kode pos harus 5 digit.';
    setFieldErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const submit = async () => {
    if (busy) return; // kunci anti double-order
    if (method === 'qris' && qrisBlocked) {
      toast.error(`QRIS melebihi batas maksimal (${formatIDR(QRIS_LIMIT)}). Pilih metode lain.`);
      return;
    }
    if (!validateAddress()) {
      setEditingAddress(true);
      toast.error('Lengkapi alamat pengiriman dulu.');
      return;
    }
    setBusy(true);
    setBuildErrors([]);
    try {
      try {
        localStorage.setItem(ADDRESS_KEY, JSON.stringify(address));
      } catch {
        // abaikan
      }
      const shippingAddress = `${address.name.trim()} | ${address.phone.trim()} | ${address.street.trim()}, ${address.city.trim()}${address.zip ? ` ${address.zip.trim()}` : ''}`;
      // Simulasi: backend tak punya field fee, jadi fee CC digabung ke shipping_fee.
      const res = await checkoutOrder({
        shipping_address: shippingAddress,
        shipping_fee: ongkir + fee,
        gateway_provider: methodCfg.provider,
      });
      const orderId = res.data?.order?._id;
      toast.success('Checkout berhasil! Lanjut ke pembayaran.');
      navigate(orderId ? `/pesanan/${orderId}` : '/pesanan');
    } catch (e) {
      const builds422 = e.payload?.builds || e.fields?.builds;
      if (e.status === 422 && Array.isArray(builds422)) {
        setBuildErrors(builds422);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        toast.error(e.payload?.message || e.message || 'Checkout gagal.');
      }
    } finally {
      setBusy(false);
    }
  };

  const inputCls = (bad) =>
    `w-full bg-subtle border rounded-xl px-3 py-2 text-[0.85rem] text-ink outline-none focus:border-primary ${bad ? 'border-danger' : 'border-border'}`;

  return (
    <div className="max-w-[1200px] mx-auto px-5 py-6 max-md:px-4 max-md:pb-6">
      <div className="hidden md:flex items-center gap-2 text-[0.82rem] mb-3">
        <Link to="/keranjang" className="inline-flex items-center gap-1 text-secondary no-underline font-semibold">
          <ChevronLeft className="w-4 h-4" /> Keranjang
        </Link>
        <span className="text-muted">/</span>
        <span className="text-ink font-bold">Checkout</span>
      </div>

      {/* Mobile: tombol back bubble, sticky di bawah navbar */}
      <div className="md:hidden sticky top-[48px] z-30 -mx-4 px-4 py-2 bg-bg/85 backdrop-blur-sm mb-1">
        <div className="flex items-center gap-2.5">
          <Link
            to="/keranjang"
            aria-label="Kembali ke keranjang"
            className="inline-flex items-center justify-center w-10 h-10 rounded-full bg-surface border border-border shadow-md text-ink no-underline shrink-0 active:scale-95"
          >
            <ChevronLeft className="w-5 h-5" />
          </Link>
          <span className="font-extrabold text-ink text-[0.95rem]">Checkout</span>
        </div>
      </div>

      {error ? (
        <ErrorState message={error.message} onRetry={() => window.location.reload()} />
      ) : cart === null ? (
        <div className="flex flex-col gap-3">
          <Skeleton height="120px" />
          <Skeleton height="200px" />
        </div>
      ) : itemCount === 0 ? (
        <EmptyState title="Tidak ada yang di-checkout">
          <p className="text-[0.85rem]">Keranjangmu kosong. Tambah komponen dulu sebelum checkout.</p>
          <div className="flex gap-2 justify-center mt-2">
            <Link to="/keranjang" className={btnClasses('primary', 'sm')}>
              Kembali ke Keranjang
            </Link>
          </div>
        </EmptyState>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">
          <div className="flex flex-col gap-5 min-w-0">
            {buildErrors.length > 0 && (
              <div className="bg-danger/10 border border-danger/30 rounded-2xl px-4 py-3" role="alert">
                <p className="m-0 mb-1.5 flex items-center gap-2 text-danger font-extrabold text-[0.85rem]">
                  <CircleAlert className="w-4 h-4" /> Rakitan tidak lagi kompatibel — order tidak dibuat
                </p>
                <ul className="m-0 pl-5 text-[0.83rem] text-danger flex flex-col gap-1">
                  {buildErrors.map((b) => (
                    <li key={b.build_id}>
                      <strong>{b.build_name || b.build_id}</strong>
                      <ul className="pl-4">
                        {(b.errors || []).map((m, i) => (
                          <li key={i}>{m}</li>
                        ))}
                      </ul>
                    </li>
                  ))}
                </ul>
                <Link to="/keranjang" className={`${btnClasses('secondary', 'sm')} mt-2`}>
                  Perbaiki di Keranjang
                </Link>
              </div>
            )}

            {/* Alamat */}
            <section className="bg-surface border border-border rounded-3xl px-5 py-4">
              <div className="flex items-center justify-between gap-2">
                <h2 className="m-0 text-[1rem] font-extrabold inline-flex items-center gap-2">
                  <MapPin className="w-4 h-4" /> Alamat Pengiriman
                </h2>
                {!editingAddress && (
                  <button className={btnClasses('secondary', 'sm')} onClick={() => setEditingAddress(true)}>
                    <Pencil className="w-3.5 h-3.5" /> Ubah
                  </button>
                )}
              </div>
              {editingAddress ? (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                  <label className="block text-[0.78rem] font-bold text-secondary">
                    Nama penerima
                    <input value={address.name} onChange={(e) => patchAddress('name', e.target.value)} placeholder="Nama lengkap" className={`${inputCls(fieldErrors.name)} mt-1`} />
                    {fieldErrors.name && <span className="text-danger font-semibold">{fieldErrors.name}</span>}
                  </label>
                  <label className="block text-[0.78rem] font-bold text-secondary">
                    No. telepon
                    <input value={address.phone} onChange={(e) => patchAddress('phone', e.target.value)} placeholder="08xxxxxxxxxx" inputMode="tel" className={`${inputCls(fieldErrors.phone)} mt-1`} />
                    {fieldErrors.phone && <span className="text-danger font-semibold">{fieldErrors.phone}</span>}
                  </label>
                  <label className="block text-[0.78rem] font-bold text-secondary sm:col-span-2">
                    Alamat lengkap
                    <textarea value={address.street} onChange={(e) => patchAddress('street', e.target.value)} placeholder="Jalan, nomor rumah, patokan" rows={2} className={`${inputCls(fieldErrors.street)} mt-1`} />
                    {fieldErrors.street && <span className="text-danger font-semibold">{fieldErrors.street}</span>}
                  </label>
                  <label className="block text-[0.78rem] font-bold text-secondary">
                    Kota
                    <input value={address.city} onChange={(e) => patchAddress('city', e.target.value)} placeholder="Kota / kabupaten" className={`${inputCls(fieldErrors.city)} mt-1`} />
                    {fieldErrors.city && <span className="text-danger font-semibold">{fieldErrors.city}</span>}
                  </label>
                  <label className="block text-[0.78rem] font-bold text-secondary">
                    Kode pos <span className="font-normal text-muted">(opsional)</span>
                    <input value={address.zip} onChange={(e) => patchAddress('zip', e.target.value)} placeholder="12345" inputMode="numeric" className={`${inputCls(fieldErrors.zip)} mt-1`} />
                    {fieldErrors.zip && <span className="text-danger font-semibold">{fieldErrors.zip}</span>}
                  </label>
                  <div className="sm:col-span-2">
                    <button
                      className={btnClasses('primary', 'sm')}
                      onClick={() => {
                        if (validateAddress()) {
                          try {
                            localStorage.setItem(ADDRESS_KEY, JSON.stringify(address));
                          } catch {
                            // abaikan
                          }
                          setEditingAddress(false);
                        }
                      }}
                    >
                      <Check className="w-4 h-4" /> Simpan Alamat
                    </button>
                  </div>
                </div>
              ) : (
                <div className="mt-3 pt-3 border-t border-border">
                  <p className="m-0 text-[0.9rem] font-bold">
                    {address.name} <span className="text-muted font-normal">|</span> <span className="text-secondary font-semibold">{address.phone}</span>
                  </p>
                  <p className="m-0 mt-1 text-[0.85rem] text-secondary">
                    {address.street}, {address.city}
                    {address.zip ? ` ${address.zip}` : ''}
                  </p>
                </div>
              )}
            </section>

            {/* Detail pesanan */}
            <section className="bg-surface border border-border rounded-3xl px-5 py-4">
              <h2 className="m-0 text-[1rem] font-extrabold inline-flex items-center gap-2">
                <Package className="w-4 h-4" /> Detail Pesanan
              </h2>
              <ul className="m-0 mt-2 p-0 list-none">
                {catalogItems.map((item) => {
                  const p = item.product || {};
                  return (
                    <li key={item._id} className="flex items-center gap-3 py-2.5 border-t border-border">
                      {thumbOf(item, p)}
                      <span className="flex-1 min-w-0">
                        <span className="block font-bold text-[0.9rem] truncate">{p.name || 'Produk'}</span>
                        <span className="block text-success font-extrabold">{formatIDR(item.price_snapshot ?? effectivePrice(p))}</span>
                        <span className="block text-[0.75rem] text-muted">Jumlah: {item.qty || 1}</span>
                      </span>
                    </li>
                  );
                })}
                {builds.map((b) => (
                  <li key={b.build_id} className="flex items-center gap-3 py-2.5 border-t border-border">
                    <span className="w-16 h-16 rounded-xl bg-subtle inline-flex items-center justify-center shrink-0">
                      <Box className="w-6 h-6 text-secondary" />
                    </span>
                    <span className="flex-1 min-w-0">
                      <span className="block font-bold text-[0.9rem] truncate">{b.build_name || 'Rakitan PC'}</span>
                      <span className="block text-success font-extrabold">{formatIDR(b.total_price ?? 0)}</span>
                      <span className="block text-[0.75rem] text-muted">Rakitan · {(b.components || []).length} komponen</span>
                    </span>
                  </li>
                ))}
              </ul>
            </section>

            {/* Pengiriman */}
            <section className="bg-surface border border-border rounded-3xl px-5 py-4">
              <h2 className="m-0 text-[1rem] font-extrabold">Pilih Pengiriman</h2>
              <p className="m-0 mt-1 text-[0.8rem] text-secondary">Estimasi tiba 2–3 hari untuk pengiriman kurir.</p>
              <div className="flex flex-col gap-2 mt-3" role="radiogroup" aria-label="Pilih pengiriman">
                <label className={`flex items-center gap-3 border rounded-2xl px-4 py-3 cursor-pointer ${shipping === 'kurir' ? 'border-primary bg-primary/5' : 'border-border'}`}>
                  <input type="radio" name="shipping" checked={shipping === 'kurir'} onChange={() => setShipping('kurir')} className="w-4 h-4 accent-primary" />
                  <Truck className="w-5 h-5 text-secondary shrink-0" />
                  <span className="flex-1">
                    <strong className="block text-[0.88rem]">Kurir — Reguler</strong>
                    <span className="block text-[0.75rem] text-muted">Estimasi tiba 2–3 hari</span>
                  </span>
                  <strong className="text-[0.88rem]">{formatIDR(SHIPPING_FLAT)}</strong>
                </label>
                <label className={`flex items-center gap-3 border rounded-2xl px-4 py-3 cursor-pointer ${shipping === 'pickup' ? 'border-primary bg-primary/5' : 'border-border'}`}>
                  <input type="radio" name="shipping" checked={shipping === 'pickup'} onChange={() => setShipping('pickup')} className="w-4 h-4 accent-primary" />
                  <Store className="w-5 h-5 text-secondary shrink-0" />
                  <span className="flex-1">
                    <strong className="block text-[0.88rem]">[TOKO] - BuildWise</strong>
                    <span className="block text-[0.75rem] text-muted">Ambil di toko, hari ini</span>
                  </span>
                  <strong className="text-[0.88rem]">{formatIDR(0)}</strong>
                </label>
              </div>
              <p className="m-0 mt-3 text-[0.78rem] text-success bg-success/10 border border-success/30 rounded-xl px-3 py-2">
                *biaya pengiriman sudah termasuk asuransi
              </p>
            </section>
          </div>

          {/* Ringkasan */}
          <aside className="bg-surface border border-border rounded-3xl px-5 py-4 lg:sticky lg:top-[76px]">
            <h2 className="m-0 text-[1rem] font-extrabold">Ringkasan Pembelian</h2>
            <div className="flex justify-between text-[0.85rem] mt-3 pt-3 border-t border-border">
              <span className="text-secondary">Item</span>
              <strong>{itemCount}</strong>
            </div>
            <div className="flex justify-between text-[0.85rem] mt-2">
              <span className="text-secondary">Subtotal</span>
              <strong>{formatIDR(subtotal)}</strong>
            </div>
            <div className="flex justify-between text-[0.85rem] mt-2">
              <span className="text-secondary">Ongkos Kirim</span>
              <strong>{formatIDR(ongkir)}</strong>
            </div>
            {fee > 0 && (
              <div className="flex justify-between text-[0.85rem] mt-2">
                <span className="text-secondary">Biaya layanan CC (2%)</span>
                <strong>{formatIDR(fee)}</strong>
              </div>
            )}
            <div className="flex justify-between text-[0.85rem] mt-2">
              <span className="text-secondary">Total Tagihan</span>
              <strong>{formatIDR(total)}</strong>
            </div>

            <h3 className="m-0 mt-4 pt-3 border-t border-border text-[0.9rem] font-extrabold">Metode Pembayaran</h3>
            <div className="flex flex-col gap-2 mt-2" role="radiogroup" aria-label="Metode pembayaran">
              {visibleMethods.map(({ key, label, sub, Icon }) => (
                <label key={key} className={`flex items-center gap-3 border rounded-2xl px-4 py-2.5 cursor-pointer ${method === key ? 'border-primary bg-primary/5' : 'border-border'}`}>
                  <input type="radio" name="paymethod" checked={method === key} onChange={() => setMethod(key)} className="w-4 h-4 accent-primary" />
                  <Icon className="w-5 h-5 text-secondary shrink-0" />
                  <span className="flex-1">
                    <strong className="block text-[0.85rem]">{label}</strong>
                    <span className="block text-[0.72rem] text-muted">{sub}</span>
                    {key === 'qris' && qrisBlocked && (
                      <span className="block text-[0.72rem] font-semibold text-danger bg-danger/10 rounded-lg px-2 py-1 mt-1">
                        Melebihi batas maksimal ({formatIDR(QRIS_LIMIT)})
                      </span>
                    )}
                  </span>
                </label>
              ))}
            </div>
            {!showAllMethods ? (
              <button onClick={() => setShowAllMethods(true)} className="w-full bg-transparent border-0 text-primary text-[0.82rem] font-bold cursor-pointer mt-2 inline-flex items-center justify-center gap-1">
                Lihat Semua <ChevronDown className="w-3.5 h-3.5" />
              </button>
            ) : null}

            <button
              className={btnClasses('cta', null, 'w-full mt-4 justify-center max-lg:hidden')}
              disabled={busy || (method === 'qris' && qrisBlocked)}
              title={method === 'qris' && qrisBlocked ? `QRIS melebihi batas maksimal (${formatIDR(QRIS_LIMIT)})` : 'Buat pesanan dan lanjut ke pembayaran'}
              onClick={submit}
            >
              {busy ? 'Memproses…' : 'Bayar Sekarang'}
            </button>
          </aside>
        </div>
      )}

      {/* Mobile: bar Total + Bayar statis di paling bawah (tidak sticky) */}
      {!error && cart !== null && itemCount > 0 && (
        <div className="md:hidden -mx-4 px-4 mt-4 pb-[max(0.9rem,env(safe-area-inset-bottom))]">
          <div className="flex items-center gap-3 bg-surface border border-border rounded-2xl px-4 py-3 shadow-[0_-8px_24px_rgba(23,20,39,0.12)]">
            <div className="min-w-0">
              <p className="m-0 text-[0.68rem] font-bold tracking-[0.06em] text-muted">TOTAL</p>
              <strong className="block truncate text-[0.95rem]">{formatIDR(total)}</strong>
            </div>
            <button
              className={btnClasses('cta', 'sm', 'flex-1 justify-center')}
              disabled={busy || (method === 'qris' && qrisBlocked)}
              title={method === 'qris' && qrisBlocked ? `QRIS melebihi batas maksimal (${formatIDR(QRIS_LIMIT)})` : 'Buat pesanan dan lanjut ke pembayaran'}
              onClick={submit}
            >
              {busy ? 'Memproses…' : 'Bayar Sekarang'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default function Checkout() {
  return (
    <Shell hideBottomNav>
      <ToastProvider>
        <CheckoutInner />
      </ToastProvider>
    </Shell>
  );
}
