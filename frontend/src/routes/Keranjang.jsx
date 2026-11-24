import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Box,
  ChevronDown,
  ChevronLeft,
  Cpu,
  CircuitBoard,
  Disc3,
  Droplet,
  Fan,
  Gpu,
  HardDrive,
  Keyboard,
  MemoryStick,
  Minus,
  Monitor,
  Mouse,
  Network,
  Package,
  Pencil,
  Plug,
  Plus,
  ShoppingCart,
  Speaker,
  Square,
  Tag,
  Trash2,
  Video,
  Database,
} from 'lucide-react';
import { get as getData } from '../lib/data.js';
import { api } from '../lib/api.js';
import { getCart, removeCartItem, setCartItemQty } from '../lib/cart.js';
import { effectivePrice, formatIDR } from '../lib/catalog.js';
import { Shell } from '../components/Layout.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import { btnClasses } from '../components/Button.jsx';

const CATEGORY_ICONS = {
  CPU: Cpu,
  Motherboard: CircuitBoard,
  RAM: MemoryStick,
  SSD: HardDrive,
  Harddisk: Database,
  GPU: Gpu,
  VGA: Gpu,
  PSU: Plug,
  Case: Box,
  'Case Fan': Fan,
  'Thermal Paste': Droplet,
  'Operating System': Disc3,
  'Sound Card': Speaker,
  'Network Card': Network,
  'Capture Card': Video,
  Mouse,
  Mousepad: Square,
  Keyboard,
  Monitor,
};

function productThumb(p, size = 'w-20 h-20 max-md:w-16 max-md:h-16') {
  if (p?.image) return <img src={p.image} alt="" className={`${size} rounded-xl object-cover bg-subtle shrink-0`} />;
  return (
    <span className={`${size} rounded-xl bg-subtle inline-flex items-center justify-center text-[0.65rem] font-bold text-muted shrink-0`}>
      {p?.category || '?'}
    </span>
  );
}

function PromoBadge({ brand, promoBrands }) {
  if (!brand || !promoBrands.has(brand)) return null;
  return (
    <span className="inline-flex items-center gap-1 bg-accent text-ink rounded-full px-2 py-0.5 text-[0.7rem] font-extrabold">
      <Tag className="w-3 h-3" /> Promo {brand}
    </span>
  );
}

function CartStepper({ qty, onDec, onInc, disabled }) {
  const btn = 'inline-flex items-center justify-center px-2 py-1 bg-primary text-surface border-0 cursor-pointer disabled:opacity-40 disabled:cursor-default';
  return (
    <span className="inline-flex items-stretch rounded-lg overflow-hidden border border-border" aria-label={`Jumlah ${qty}`}>
      <button aria-label="Kurangi jumlah" className={btn} disabled={disabled || qty <= 1} onClick={onDec}>
        <Minus className="w-3.5 h-3.5" />
      </button>
      <span aria-live="polite" className="min-w-8 inline-flex items-center justify-center text-[0.85rem] font-bold bg-surface">
        {qty}
      </span>
      <button aria-label="Tambah jumlah" className={btn} disabled={disabled} onClick={onInc}>
        <Plus className="w-3.5 h-3.5" />
      </button>
    </span>
  );
}

function DeleteButton({ onClick, label = 'Hapus', busy, iconOnlyOnMobile = false }) {
  return (
    <button
      onClick={onClick}
      disabled={busy}
      className="inline-flex items-center gap-1.5 rounded-full border border-danger/40 text-danger bg-transparent px-3 py-1.5 text-[0.78rem] font-bold cursor-pointer hover:bg-danger/10 disabled:opacity-50 max-md:px-2.5"
    >
      <Trash2 className="w-3.5 h-3.5" /> <span className={iconOnlyOnMobile ? 'max-md:hidden' : ''}>{label}</span>
    </button>
  );
}

// Kartu accordion satu rakitan: checkbox + nama + badge + subtotal,
// detail = strip validasi live + tabel komponen + Ganti/Hapus utuh.
function BuildCard({ build, kind, selected, onToggle, promoBrands, onEdit, onDeleted }) {
  const toast = useToast();
  const [open, setOpen] = useState(false);
  const [validation, setValidation] = useState(null);
  const [busy, setBusy] = useState(false);
  const badge = kind === 'package' ? 'Group–Paket' : 'Group–PC Builder';
  const BadgeIcon = kind === 'package' ? Package : Box;

  const components = build.components || [];
  const subtotal = build.total_price ?? components.reduce((s, i) => s + (i.price_snapshot ?? effectivePrice(i.product)) * (i.qty || 1), 0);

  // Strip HARGA/STATUS/DAYA: validasi live saat accordion dibuka.
  useEffect(() => {
    if (!open || kind !== 'builder') return;
    let alive = true;
    const body = {};
    const extras = [];
    for (const item of components) {
      const cat = String(item.product?.category || '').toLowerCase();
      const id = item.product_id;
      if (cat === 'cpu') body.cpu_id = id;
      else if (cat === 'motherboard') body.motherboard_id = id;
      else if (cat === 'ram') body.ram_id = id;
      else if (cat === 'ssd') body.storage_id = id;
      else if (cat === 'gpu' || cat === 'vga') body.gpu_id = id;
      else if (cat === 'case') body.case_id = id;
      else if (cat === 'psu') body.psu_id = id;
      else extras.push(id);
    }
    if (extras.length > 0) body.extra_product_ids = extras;
    (async () => {
      try {
        const res = await api.post('/builder/validate', { body });
        if (alive) setValidation(res.data);
      } catch {
        if (alive) setValidation(null);
      }
    })();
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, build.build_id]);

  const destroy = async () => {
    setBusy(true);
    try {
      for (const item of components) await removeCartItem(item);
      toast.success(`${build.build_name || 'Rakitan'} dihapus dari keranjang.`);
      onDeleted();
    } catch (e) {
      toast.error(e.message || 'Gagal menghapus rakitan.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="border border-border rounded-2xl bg-surface overflow-hidden">
      <div className="flex items-center gap-3 px-4 py-3 max-md:flex-wrap">
        <input
          type="checkbox"
          checked={selected}
          onChange={onToggle}
          aria-label={`Pilih ${build.build_name}`}
          className="w-[18px] h-[18px] accent-primary cursor-pointer shrink-0"
        />
        <button
          onClick={() => setOpen((v) => !v)}
          aria-expanded={open}
          className="flex items-center gap-3 flex-1 min-w-0 bg-transparent border-0 p-0 cursor-pointer text-left"
        >
          <span className="min-w-0">
            <span className="block font-bold text-ink text-[0.95rem] truncate">{build.build_name || 'Rakitan PC'}</span>
            <span className="mt-1 inline-flex items-center gap-1 bg-subtle text-secondary rounded-full px-2 py-0.5 text-[0.7rem] font-bold">
              <BadgeIcon className="w-3 h-3" /> {badge} · {components.length} komponen
            </span>
          </span>
          <ChevronDown className={`w-4 h-4 text-secondary shrink-0 ml-auto transition-transform ${open ? 'rotate-180' : ''}`} />
        </button>
        <strong className="text-success text-[0.95rem] whitespace-nowrap max-md:basis-full max-md:text-right">
          {formatIDR(subtotal)}
        </strong>
      </div>

      {open && (
        <div className="border-t border-border px-4 py-3">
          {kind === 'builder' && validation && (
            <div className="grid grid-cols-3 gap-2 bg-subtle rounded-xl px-4 py-3 mb-3 text-center max-md:px-2">
              <div>
                <p className="m-0 text-[0.68rem] font-bold tracking-[0.06em] text-muted">HARGA</p>
                <p className="m-0 font-extrabold text-ink max-md:text-[0.85rem]">{formatIDR(validation.grand_total ?? subtotal)}</p>
              </div>
              <div className="border-x border-border">
                <p className="m-0 text-[0.68rem] font-bold tracking-[0.06em] text-muted">STATUS KOMPONEN</p>
                <p className={`m-0 font-extrabold max-md:text-[0.85rem] ${validation.is_compatible ? 'text-success' : 'text-danger'}`}>
                  {validation.is_compatible ? 'KOMPATIBEL' : 'TIDAK KOMPATIBEL'}
                </p>
              </div>
              <div>
                <p className="m-0 text-[0.68rem] font-bold tracking-[0.06em] text-muted">DAYA</p>
                <p className="m-0 font-extrabold text-ink max-md:text-[0.85rem]">{validation.power_analysis?.total_estimated_watt ?? '–'}W</p>
              </div>
            </div>
          )}
          <ul className="m-0 p-0 list-none flex flex-col">
            {components.map((item) => {
              const p = item.product || {};
              const Icon = CATEGORY_ICONS[p.category] || Box;
              const key = item._id || `${item.product_id}-${item.qty}`;
              return (
                <li key={key} className="flex items-center gap-3 py-2 border-b border-border last:border-0">
                  <span className="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-subtle text-secondary shrink-0">
                    <Icon className="w-4 h-4" />
                  </span>
                  <span className="flex-1 min-w-0">
                    <span className="block text-[0.83rem] text-secondary font-semibold">{p.category}</span>
                    <span className="block text-[0.88rem] font-bold text-ink truncate">{p.name || 'Produk'}</span>
                    <PromoBadge brand={p.brand} promoBrands={promoBrands} />
                  </span>
                  <span className="text-[0.82rem] font-bold whitespace-nowrap">{formatIDR(item.price_snapshot ?? effectivePrice(p))}</span>
                  <span className="text-[0.8rem] text-secondary font-bold w-6 text-right">×{item.qty || 1}</span>
                </li>
              );
            })}
          </ul>
          <div className="flex gap-2 mt-3 flex-wrap">
            {kind === 'builder' && (
              <button className={btnClasses('cta', 'sm')} onClick={() => onEdit(build.build_id)}>
                <Pencil className="w-4 h-4" /> Ganti Komponen
              </button>
            )}
            <DeleteButton onClick={destroy} label="Hapus Rakitan" busy={busy} />
          </div>
          {kind === 'builder' && (
            <p className="m-0 mt-2 text-[0.75rem] text-secondary">Satuan dalam rakitan tidak bisa diubah — pakai Ganti Komponen untuk edit utuh.</p>
          )}
        </div>
      )}
    </div>
  );
}

function KeranjangInner() {
  const toast = useToast();
  const navigate = useNavigate();
  const [cart, setCart] = useState(null);
  const [error, setError] = useState(null);
  const [promos, setPromos] = useState([]);
  const [selected, setSelected] = useState(() => new Set());
  const [busyKey, setBusyKey] = useState(null);

  const refresh = async () => {
    try {
      const res = await getCart();
      setCart(res.data);
      setError(null);
    } catch (e) {
      setError(e);
      setCart(null);
    }
  };

  useEffect(() => {
    refresh();
    (async () => {
      try {
        const res = await getData('/promotions');
        setPromos(res.data || []);
      } catch {
        setPromos([]);
      }
    })();
  }, []);

  const promoBrands = useMemo(() => new Set(promos.flatMap((p) => p.brands || [])), [promos]);

  const catalogItems = cart?.catalog_items || [];
  const builderBuilds = cart?.builder_builds || [];
  const packageBuilds = cart?.package_builds || [];
  const allKeys = useMemo(
    () => [
      ...catalogItems.map((i) => `c:${i._id}`),
      ...builderBuilds.map((b) => `b:${b.build_id}`),
      ...packageBuilds.map((b) => `p:${b.build_id}`),
    ],
    [cart],
  );
  const allSelected = allKeys.length > 0 && allKeys.every((k) => selected.has(k));

  const toggle = (key) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  };
  const toggleAll = () => {
    setSelected(allSelected ? new Set() : new Set(allKeys));
  };

  const summary = useMemo(() => {
    let count = 0;
    let total = 0;
    for (const i of catalogItems) {
      if (!selected.has(`c:${i._id}`)) continue;
      count += Number(i.qty) || 0;
      total += (i.price_snapshot ?? effectivePrice(i.product)) * (Number(i.qty) || 0);
    }
    for (const [prefix, list] of [['b', builderBuilds], ['p', packageBuilds]]) {
      for (const b of list) {
        if (!selected.has(`${prefix}:${b.build_id}`)) continue;
        const comps = b.components || [];
        count += comps.reduce((s, i) => s + (Number(i.qty) || 0), 0);
        total += b.total_price ?? comps.reduce((s, i) => s + (i.price_snapshot ?? effectivePrice(i.product)) * (Number(i.qty) || 0), 0);
      }
    }
    return { count, total };
  }, [selected, catalogItems, builderBuilds, packageBuilds]);

  const changeQty = async (item, next) => {
    const key = `c:${item._id}`;
    setBusyKey(key);
    try {
      await setCartItemQty(item, next);
      await refresh();
    } catch (e) {
      toast.error(e.message || 'Gagal mengubah jumlah.');
    } finally {
      setBusyKey(null);
    }
  };

  const deleteItem = async (item) => {
    const key = `c:${item._id}`;
    setBusyKey(key);
    try {
      await removeCartItem(item);
      setSelected((prev) => {
        const next = new Set(prev);
        next.delete(key);
        return next;
      });
      toast.success('Item dihapus dari keranjang.');
      await refresh();
    } catch (e) {
      toast.error(e.message || 'Gagal menghapus item.');
    } finally {
      setBusyKey(null);
    }
  };

  return (
    <div className="max-w-[1200px] mx-auto px-5 py-6 max-md:px-4 max-md:pb-6 max-lg:min-h-[calc(100dvh-48px)] max-lg:flex max-lg:flex-col">
      <div className="hidden md:flex items-center gap-2 text-[0.82rem] mb-3">
        <Link to="/" className="inline-flex items-center gap-1 text-secondary no-underline font-semibold">
          <ChevronLeft className="w-4 h-4" /> Beranda
        </Link>
        <span className="text-muted">/</span>
        <span className="text-ink font-bold">Keranjang</span>
      </div>

      {/* Mobile: tombol back bubble, sticky di bawah navbar */}
      <div className="md:hidden sticky top-[48px] z-30 -mx-4 px-4 py-2 bg-bg/85 backdrop-blur-sm mb-1">
        <div className="flex items-center gap-2.5">
          <Link
            to="/"
            aria-label="Kembali ke beranda"
            className="inline-flex items-center justify-center w-10 h-10 rounded-full bg-surface border border-border shadow-md text-ink no-underline shrink-0 active:scale-95"
          >
            <ChevronLeft className="w-5 h-5" />
          </Link>
          <span className="font-extrabold text-ink text-[0.95rem]">Keranjang</span>
        </div>
      </div>

      {error ? (
        <ErrorState message={error.message} onRetry={refresh} />
      ) : cart === null ? (
        <div className="flex flex-col gap-3">
          <Skeleton height="64px" />
          <Skeleton height="120px" />
          <Skeleton height="120px" />
        </div>
      ) : allKeys.length === 0 ? (
        <EmptyState title="Keranjang masih kosong">
          <p className="text-[0.85rem]">Yuk rakit PC impianmu atau cari komponen di katalog.</p>
          <div className="flex gap-2 justify-center mt-2 flex-wrap">
            <Link to="/builder" className={btnClasses('primary', 'sm')}>
              PC Builder
            </Link>
            <Link to="/katalog" className={btnClasses('secondary', 'sm')}>
              Lihat Katalog
            </Link>
          </div>
        </EmptyState>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start max-lg:contents">
          {/* Daftar item */}
          <section className="bg-surface border border-border rounded-3xl px-5 py-4 max-md:px-4" aria-live="polite">
            <div className="flex items-center justify-between gap-3">
              <h1 className="m-0 text-[1.2rem] font-extrabold inline-flex items-center gap-2">
                <ShoppingCart className="w-5 h-5" /> Keranjang
              </h1>
              <span className="text-[0.8rem] text-muted font-semibold">
                {summary.count} produk dipilih
              </span>
            </div>
            <label className="flex items-center gap-2 mt-3 pt-3 border-t border-border text-[0.85rem] font-bold cursor-pointer">
              <input
                type="checkbox"
                checked={allSelected}
                onChange={toggleAll}
                aria-label="Pilih semua"
                className="w-[18px] h-[18px] accent-primary cursor-pointer"
              />
              Pilih Semua
            </label>

            <div className="flex flex-col gap-3 mt-3">
              {[...builderBuilds.map((b) => ({ kind: 'builder', build: b })), ...packageBuilds.map((b) => ({ kind: 'package', build: b }))].map(
                ({ kind, build }) => {
                  const key = `${kind === 'builder' ? 'b' : 'p'}:${build.build_id}`;
                  return (
                    <BuildCard
                      key={key}
                      build={build}
                      kind={kind}
                      selected={selected.has(key)}
                      onToggle={() => toggle(key)}
                      promoBrands={promoBrands}
                      onEdit={(id) => navigate(`/builder?edit_build=${id}`)}
                      onDeleted={refresh}
                    />
                  );
                },
              )}

              {catalogItems.map((item) => {
                const p = item.product || {};
                const key = `c:${item._id}`;
                const busy = busyKey === key;
                return (
                  <div key={key} className="flex items-center gap-3 border-t border-border pt-3 flex-wrap">
                    <input
                      type="checkbox"
                      checked={selected.has(key)}
                      onChange={() => toggle(key)}
                      aria-label={`Pilih ${p.name}`}
                      className="w-[18px] h-[18px] accent-primary cursor-pointer shrink-0"
                    />
                    {productThumb(p)}
                    <div className="flex-1 min-w-0 max-md:min-w-[200px]">
                      <Link to={`/produk/${item.product_id}`} className="font-bold text-ink no-underline hover:text-primary text-[0.95rem] block truncate">
                        {p.name || 'Produk'}
                      </Link>
                      <p className="m-0 mt-0.5 text-success font-extrabold">{formatIDR(item.price_snapshot ?? effectivePrice(p))}</p>
                      <div className="mt-1.5">
                        <CartStepper qty={Number(item.qty) || 1} disabled={busy} onDec={() => changeQty(item, (Number(item.qty) || 1) - 1)} onInc={() => changeQty(item, (Number(item.qty) || 1) + 1)} />
                      </div>
                      <div className="mt-1.5">
                        <PromoBadge brand={p.brand} promoBrands={promoBrands} />
                      </div>
                    </div>
                    <div className="shrink-0 self-start ml-auto max-md:self-center">
                      <DeleteButton onClick={() => deleteItem(item)} busy={busy} iconOnlyOnMobile />
                    </div>
                  </div>
                );
              })}
            </div>
          </section>

          {/* Ringkasan */}
          <aside className="bg-surface border border-border rounded-3xl px-5 py-4 lg:sticky lg:top-[76px] max-lg:sticky max-lg:bottom-0 max-lg:z-30 max-lg:mt-auto max-lg:rounded-t-3xl max-lg:rounded-b-none max-lg:border-x-0 max-lg:border-b-0 max-lg:-mx-4 max-lg:pb-[max(2rem,env(safe-area-inset-bottom))] max-lg:shadow-[0_-8px_24px_rgba(23,20,39,0.12)]">
            <h2 className="m-0 text-[1rem] font-extrabold">Ringkasan Belanja</h2>
            <div className="flex justify-between text-[0.85rem] mt-3 pt-3 border-t border-border">
              <span className="text-secondary">Item</span>
              <strong>{summary.count} produk</strong>
            </div>
            <div className="flex justify-between text-[0.85rem] mt-2">
              <span className="text-secondary">Total</span>
              <strong className="text-primary">{formatIDR(summary.total)}</strong>
            </div>
            <button
              className={btnClasses('cta', null, 'w-full mt-4 justify-center')}
              disabled
              title="Checkout hadir di FE-08"
              onClick={() => toast.success('Checkout hadir di FE-08.')}
            >
              Beli Sekarang
            </button>
          </aside>
        </div>
      )}
    </div>
  );
}

export default function Keranjang() {
  return (
    <Shell hideBottomNav>
      <ToastProvider>
        <KeranjangInner />
      </ToastProvider>
    </Shell>
  );
}
