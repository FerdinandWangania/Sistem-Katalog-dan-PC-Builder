import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Box,
  Check,
  ChevronDown,
  ChevronLeft,
  CircleAlert,
  CreditCard,
  Package,
  Pencil,
  Plus,
  QrCode,
  Store,
  Trash2,
  Truck,
  Wallet,
} from 'lucide-react';
import { getCart } from '../lib/cart.js';
import { checkoutOrder } from '../lib/orders.js';
import { createAddress, deleteAddress, formatAddress, listAddresses, updateAddress } from '../lib/addresses.js';
import { effectivePrice, formatIDR } from '../lib/catalog.js';
import { Shell } from '../components/Layout.jsx';
import { Modal } from '../components/Modal.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import { btnClasses } from '../components/Button.jsx';

const QRIS_LIMIT = 10000000;
const SHIPPING_FLAT = 25000;
const CC_FEE_RATE = 0.02;

const METHODS = [
  { key: 'qris', label: 'QRIS', sub: 'QR', provider: 'midtrans', Icon: QrCode },
  { key: 'cc', label: 'Credit Card', sub: 'CC', provider: 'xendit', Icon: CreditCard },
  { key: 'va_bca', label: 'VA BCA', sub: 'VA', provider: 'bca_va', Icon: Wallet },
  { key: 'va_mandiri', label: 'VA Mandiri', sub: 'VA', provider: 'mandiri_va', Icon: Wallet },
];

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
  const [addresses, setAddresses] = useState([]);
  const [addrsAreMock, setAddrsAreMock] = useState(false);
  const [selectedAddrId, setSelectedAddrId] = useState(null);
  const [addrModal, setAddrModal] = useState(false);
  const [addrForm, setAddrForm] = useState(null); // null | {id?} — mode tambah/edit di modal
  const [addrFields, setAddrFields] = useState({ label: '', recipient: '', phone: '', address: '', city: '', is_default: false });
  const [fieldErrors, setFieldErrors] = useState({});
  const [addrBusy, setAddrBusy] = useState(false);
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
    refreshAddresses();
  }, []);

  const refreshAddresses = async () => {
    try {
      const { list, isMock } = await listAddresses();
      setAddresses(list);
      setAddrsAreMock(isMock);
      setSelectedAddrId((prev) => {
        if (prev && list.some((a) => (a._id || a.id) === prev)) return prev;
        const def = list.find((a) => a.is_default) || list[0];
        return def ? def._id || def.id : null;
      });
    } catch (e) {
      toast.error(e.message || 'Gagal memuat daftar alamat.');
    }
  };

  const selectedAddress = addresses.find((a) => (a._id || a.id) === selectedAddrId) || null;

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

  const patchAddrField = (k, v) => {
    setAddrFields((a) => ({ ...a, [k]: v }));
    setFieldErrors((e) => ({ ...e, [k]: null }));
  };

  const validateAddrForm = () => {
    const errs = {};
    const v = addrFields;
    if (!String(v.label || '').trim()) errs.label = 'Label wajib diisi (cth. Rumah/Kantor).';
    if (!String(v.recipient || '').trim()) errs.recipient = 'Nama penerima wajib diisi.';
    if (!String(v.phone || '').trim()) errs.phone = 'No. telepon wajib diisi.';
    else if (!/^[0-9+()\-.\s]{9,16}$/.test(String(v.phone).trim())) errs.phone = 'No. telepon tidak valid (9–16 digit).';
    if (!String(v.address || '').trim()) errs.address = 'Alamat lengkap wajib diisi.';
    if (!String(v.city || '').trim()) errs.city = 'Kota wajib diisi.';
    setFieldErrors(errs);
    return { ok: Object.keys(errs).length === 0, errs };
  };

  const openAddrForm = (existing = null) => {
    setFieldErrors({});
    setAddrFields(
      existing
        ? { label: existing.label || '', recipient: existing.recipient || '', phone: existing.phone || '', address: existing.address || '', city: existing.city || '', is_default: Boolean(existing.is_default) }
        : { label: '', recipient: '', phone: '', address: '', city: '', is_default: addresses.length === 0 },
    );
    setAddrForm(existing ? { id: existing._id || existing.id } : {});
  };

  const saveAddrForm = async () => {
    const { ok, errs } = validateAddrForm();
    if (!ok) {
      toast.error(errs._global || 'Lengkapi alamat dulu.');
      return;
    }
    setAddrBusy(true);
    try {
      const payload = { ...addrFields, label: addrFields.label.trim(), recipient: addrFields.recipient.trim(), phone: addrFields.phone.trim(), address: addrFields.address.trim(), city: addrFields.city.trim() };
      let savedId = null;
      if (addrForm?.id) {
        const res = await updateAddress(addrForm.id, payload);
        savedId = res.data?._id || res.data?.id || addrForm.id;
        toast.success('Alamat berhasil diubah.');
      } else {
        const res = await createAddress(payload);
        savedId = res.data?._id || res.data?.id;
        toast.success('Alamat berhasil ditambahkan.');
      }
      setAddrForm(null);
      await refreshAddresses();
      if (savedId) setSelectedAddrId(savedId);
    } catch (e) {
      if (e.fields) setFieldErrors(e.fields);
      toast.error(e.payload?.message || e.message || 'Gagal menyimpan alamat.');
    } finally {
      setAddrBusy(false);
    }
  };

  const removeAddress = async (id) => {
    setAddrBusy(true);
    try {
      await deleteAddress(id);
      toast.success('Alamat dihapus.');
      await refreshAddresses();
    } catch (e) {
      toast.error(e.message || 'Gagal menghapus alamat.');
    } finally {
      setAddrBusy(false);
    }
  };

  const submit = async () => {
    if (busy) return; // kunci anti double-order
    if (method === 'qris' && qrisBlocked) {
      toast.error(`QRIS melebihi batas maksimal (${formatIDR(QRIS_LIMIT)}). Pilih metode lain.`);
      return;
    }
    if (!selectedAddress) {
      setAddrModal(true);
      toast.error('Pilih atau tambah alamat pengiriman dulu.');
      return;
    }
    setBusy(true);
    setBuildErrors([]);
    try {
      // Backend P2: kirim address_id bila alamat berasal dari server;
      // mode mock/legacy kirim string (address_id mock tak dikenal backend).
      const body = {
        shipping_fee: ongkir + fee,
        gateway_provider: methodCfg.provider,
        payment_method: method === 'qris' ? 'qris' : method === 'cc' ? 'credit_card' : 'bank_transfer',
      };
      if (addrsAreMock) body.shipping_address = formatAddress(selectedAddress);
      else body.address_id = selectedAddress._id || selectedAddress.id;
      // Simulasi: backend tak punya field fee khusus, jadi fee CC digabung ke shipping_fee.
      const res = await checkoutOrder(body);
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

            {/* Alamat Pengiriman (display ala mockup + modal Daftar Alamat) */}
            <section className="bg-surface border border-border rounded-3xl px-5 py-4">
              <div className="flex items-center justify-between gap-2">
                <h2 className="m-0 text-[1rem] font-extrabold">Alamat Pengiriman</h2>
                <button className={btnClasses('secondary', 'sm')} onClick={() => { setAddrForm(null); setAddrModal(true); }}>
                  Ubah
                </button>
              </div>
              {selectedAddress ? (
                <div className="mt-3 pt-3 border-t border-border">
                  <p className="m-0 text-[0.9rem] font-bold">
                    {selectedAddress.label} <span className="text-muted font-normal">|</span>{' '}
                    <span className="text-secondary font-semibold">+{String(selectedAddress.phone || '').replace(/^\+/, '')}</span>{' '}
                    {selectedAddress.is_default && (
                      <span className="ml-1 bg-primary/10 text-primary rounded-full px-2 py-0.5 text-[0.7rem] font-extrabold">Alamat Utama</span>
                    )}
                  </p>
                  <p className="m-0 mt-1 text-[0.85rem] text-secondary">
                    {selectedAddress.address}, {selectedAddress.city}
                  </p>
                  <p className="m-0 mt-0.5 text-[0.75rem] text-muted">Penerima: {selectedAddress.recipient}</p>
                </div>
              ) : (
                <div className="mt-3 pt-3 border-t border-border flex items-center gap-2 flex-wrap">
                  <p className="m-0 text-[0.85rem] text-secondary flex-1 min-w-[180px]">Belum ada alamat. Tambah dulu sebelum checkout.</p>
                  <button className={btnClasses('primary', 'sm')} onClick={() => { setAddrForm(null); openAddrForm(); setAddrModal(true); }}>
                    <Plus className="w-4 h-4" /> Tambah Alamat
                  </button>
                </div>
              )}
            </section>

            <Modal open={addrModal} title={addrForm ? (addrForm.id ? 'Ubah Alamat' : 'Tambah Alamat') : 'Daftar Alamat'} onClose={() => { setAddrModal(false); setAddrForm(null); }}>
              {addrForm ? (
                <div className="flex flex-col gap-3">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label className="block text-[0.78rem] font-bold text-secondary">
                      Label <span className="font-normal text-muted">(Rumah/Kantor)</span>
                      <input value={addrFields.label} onChange={(e) => patchAddrField('label', e.target.value)} placeholder="Rumah" className={`${inputCls(fieldErrors.label)} mt-1`} />
                      {fieldErrors.label && <span className="text-danger font-semibold">{fieldErrors.label}</span>}
                    </label>
                    <label className="block text-[0.78rem] font-bold text-secondary">
                      Nama penerima
                      <input value={addrFields.recipient} onChange={(e) => patchAddrField('recipient', e.target.value)} placeholder="Nama lengkap" className={`${inputCls(fieldErrors.recipient)} mt-1`} />
                      {fieldErrors.recipient && <span className="text-danger font-semibold">{fieldErrors.recipient}</span>}
                    </label>
                    <label className="block text-[0.78rem] font-bold text-secondary sm:col-span-2">
                      No. telepon
                      <input value={addrFields.phone} onChange={(e) => patchAddrField('phone', e.target.value)} placeholder="08xxxxxxxxxx" inputMode="tel" className={`${inputCls(fieldErrors.phone)} mt-1`} />
                      {fieldErrors.phone && <span className="text-danger font-semibold">{fieldErrors.phone}</span>}
                    </label>
                    <label className="block text-[0.78rem] font-bold text-secondary sm:col-span-2">
                      Alamat lengkap
                      <textarea value={addrFields.address} onChange={(e) => patchAddrField('address', e.target.value)} placeholder="Jalan, nomor rumah, patokan" rows={2} className={`${inputCls(fieldErrors.address)} mt-1`} />
                      {fieldErrors.address && <span className="text-danger font-semibold">{fieldErrors.address}</span>}
                    </label>
                    <label className="block text-[0.78rem] font-bold text-secondary sm:col-span-2">
                      Kota
                      <input value={addrFields.city} onChange={(e) => patchAddrField('city', e.target.value)} placeholder="Kota / kabupaten" className={`${inputCls(fieldErrors.city)} mt-1`} />
                      {fieldErrors.city && <span className="text-danger font-semibold">{fieldErrors.city}</span>}
                    </label>
                    <label className="sm:col-span-2 inline-flex items-center gap-2 text-[0.82rem] font-semibold cursor-pointer">
                      <input type="checkbox" checked={Boolean(addrFields.is_default)} onChange={(e) => patchAddrField('is_default', e.target.checked)} className="w-4 h-4 accent-primary" />
                      Jadikan alamat utama
                    </label>
                  </div>
                  <div className="flex gap-2 justify-end">
                    <button className={btnClasses('secondary', 'sm')} disabled={addrBusy} onClick={() => setAddrForm(null)}>
                      Kembali
                    </button>
                    <button className={btnClasses('primary', 'sm')} disabled={addrBusy} onClick={saveAddrForm}>
                      <Check className="w-4 h-4" /> {addrBusy ? 'Menyimpan…' : 'Simpan Alamat'}
                    </button>
                  </div>
                </div>
              ) : (
                <div className="flex flex-col gap-3">
                  {fieldErrors._global && <p className="m-0 text-danger text-[0.82rem] font-semibold">{fieldErrors._global}</p>}
                  {addresses.length === 0 && (
                    <p className="m-0 text-[0.85rem] text-secondary">Belum ada alamat tersimpan.</p>
                  )}
                  {addresses.map((a) => {
                    const aid = a._id || a.id;
                    const picked = aid === selectedAddrId;
                    return (
                      <div key={aid} className={`border rounded-2xl px-4 py-3 ${picked ? 'border-primary bg-primary/5' : 'border-border'}`}>
                        <div className="flex items-center gap-2">
                          <input
                            type="radio"
                            name="checkout-address"
                            checked={picked}
                            onChange={() => setSelectedAddrId(aid)}
                            aria-label={`Pilih alamat ${a.label}`}
                            className="w-4 h-4 accent-primary shrink-0 cursor-pointer"
                          />
                          {a.is_default && (
                            <span className="bg-primary text-surface rounded-full px-2 py-0.5 text-[0.65rem] font-extrabold shrink-0">UTAMA</span>
                          )}
                          <strong className="text-[0.88rem] truncate">
                            {a.label} | +{String(a.phone || '').replace(/^\+/, '')}
                          </strong>
                          <span className="ml-auto flex gap-1.5 shrink-0">
                            <button
                              onClick={() => openAddrForm(a)}
                              disabled={addrBusy}
                              aria-label={`Ubah alamat ${a.label}`}
                              title="Ubah"
                              className="inline-flex items-center gap-1 rounded-full border border-primary/40 text-primary bg-transparent px-2.5 py-1 text-[0.75rem] font-bold cursor-pointer hover:bg-primary/10"
                            >
                              <Pencil className="w-3.5 h-3.5" /> Edit
                            </button>
                            <button
                              onClick={() => removeAddress(aid)}
                              disabled={addrBusy}
                              aria-label={`Hapus alamat ${a.label}`}
                              title="Hapus"
                              className="inline-flex items-center justify-center w-7 h-7 rounded-full border border-danger/40 text-danger bg-transparent cursor-pointer hover:bg-danger/10"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          </span>
                        </div>
                        <p className="m-0 mt-1 text-[0.82rem] text-secondary">
                          {a.address}, {a.city} <span className="text-muted">· {a.recipient}</span>
                        </p>
                      </div>
                    );
                  })}
                  <button
                    className={btnClasses('cta', 'sm', 'justify-center')}
                    disabled={addrBusy || addresses.length >= 10}
                    title={addresses.length >= 10 ? 'Maksimal 10 alamat' : 'Tambah alamat baru'}
                    onClick={() => openAddrForm()}
                  >
                    <Plus className="w-4 h-4" /> Tambah Alamat
                  </button>
                  {addresses.length >= 10 && <p className="m-0 text-[0.75rem] text-muted">Maksimal 10 alamat per akun.</p>}
                </div>
              )}
            </Modal>

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
