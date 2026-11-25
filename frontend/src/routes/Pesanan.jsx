import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronLeft, CircleAlert, Package, ReceiptText, Search } from 'lucide-react';
import { cancelOrder, listOrders } from '../lib/orders.js';
import { effectivePrice, formatIDR } from '../lib/catalog.js';
import { Shell } from '../components/Layout.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import { btnClasses } from '../components/Button.jsx';

const EXPIRY_MS = 24 * 3600e3;

function isExpired(order) {
  if (order?.status !== 'pending' || !order?.created_at) return false;
  return Date.now() - new Date(order.created_at).getTime() > EXPIRY_MS;
}

function statusOf(order) {
  if (order.status === 'pending' && isExpired(order)) return 'expired';
  return order.status; // pending | processing | shipped | delivered | cancelled
}

const CHIPS = [
  { key: 'semua', label: 'Semua' },
  { key: 'proses', label: 'Dalam Proses', match: ['processing', 'shipped'] },
  { key: 'lunas', label: 'Sudah Dibayar', match: ['delivered'] },
  { key: 'kedaluwarsa', label: 'Kadaluarsa', match: ['expired'] },
  { key: 'menunggu', label: 'Menunggu Pembayaran', match: ['pending'] },
  { key: 'batal', label: 'Dibatalkan', match: ['cancelled'] },
];

const BADGES = {
  pending: 'bg-warning/15 text-warning border-warning/40',
  expired: 'bg-danger/10 text-danger border-danger/30',
  processing: 'bg-primary/10 text-primary border-primary/30',
  shipped: 'bg-primary/10 text-primary border-primary/30',
  delivered: 'bg-success/15 text-success border-success/30',
  cancelled: 'bg-subtle text-muted border-border',
};

const BADGE_LABEL = {
  pending: 'Menunggu Pembayaran',
  expired: 'Kadaluarsa',
  processing: 'Dalam Proses',
  shipped: 'Dalam Proses',
  delivered: 'Sudah Dibayar',
  cancelled: 'Dibatalkan',
};

function fmtDateTime(v) {
  try {
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(v));
  } catch {
    return '-';
  }
}

function PesananInner() {
  const toast = useToast();
  const [orders, setOrders] = useState(null);
  const [error, setError] = useState(null);
  const [q, setQ] = useState('');
  const [chip, setChip] = useState('semua');
  const [busyId, setBusyId] = useState(null);

  const refresh = async () => {
    try {
      const res = await listOrders();
      setOrders(res.data || []);
      setError(null);
    } catch (e) {
      setError(e);
      setOrders([]);
    }
  };

  useEffect(() => {
    refresh();
  }, []);

  const filtered = useMemo(() => {
    const query = q.trim().toLowerCase();
    return (orders || []).filter((o) => {
      const st = statusOf(o);
      const chipCfg = CHIPS.find((c) => c.key === chip);
      if (chipCfg?.match && !chipCfg.match.includes(st)) return false;
      if (!query) return true;
      const hay = [o.order_number, ...(o.items || []).map((i) => i.product?.name || i.build_name || '')].join(' ').toLowerCase();
      return hay.includes(query);
    });
  }, [orders, q, chip]);

  const cancel = async (order) => {
    setBusyId(order._id);
    try {
      await cancelOrder(order._id);
      toast.success('Pesanan dibatalkan, stok dikembalikan.');
      await refresh();
    } catch (e) {
      toast.error(e.message || 'Gagal membatalkan.');
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="max-w-[1100px] mx-auto px-5 py-6 max-md:px-4 max-md:pb-28">
      <div className="flex items-center gap-2 text-[0.82rem] mb-3">
        <Link to="/" className="inline-flex items-center gap-1 text-secondary no-underline font-semibold">
          <ChevronLeft className="w-4 h-4" /> Beranda
        </Link>
        <span className="text-muted">/</span>
        <span className="text-ink font-bold">Pesanan Saya</span>
      </div>

      <section className="bg-surface border border-border rounded-3xl px-6 py-5 max-md:px-4">
        <h1 className="m-0 text-[1.2rem] font-extrabold inline-flex items-center gap-2">
          <ReceiptText className="w-5 h-5" /> Daftar Transaksi
        </h1>

        <label className="flex items-center gap-2 bg-subtle border border-border rounded-full px-4 py-2.5 mt-4">
          <Search className="w-4 h-4 text-secondary shrink-0" />
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Cari Transaksi"
            aria-label="Cari transaksi"
            className="flex-1 bg-transparent border-0 outline-none text-[0.88rem] text-ink placeholder:text-placeholder"
          />
        </label>

        <div className="flex gap-2 mt-3 flex-wrap" role="tablist" aria-label="Filter status">
          {CHIPS.map((c) => (
            <button
              key={c.key}
              role="tab"
              aria-selected={chip === c.key}
              onClick={() => setChip(c.key)}
              className={`rounded-full border px-4 py-1.5 text-[0.8rem] font-bold cursor-pointer ${
                chip === c.key ? 'bg-primary text-surface border-transparent' : 'bg-surface text-secondary border-border hover:bg-subtle'
              }`}
            >
              {c.label}
            </button>
          ))}
        </div>

        <div className="mt-4 flex flex-col gap-3">
          {error ? (
            <ErrorState message={error.message} onRetry={refresh} />
          ) : orders === null ? (
            <>
              <Skeleton height="140px" />
              <Skeleton height="140px" />
            </>
          ) : filtered.length === 0 ? (
            <EmptyState title={orders.length === 0 ? 'Belum ada transaksi' : 'Tidak ada transaksi yang cocok'}>
              <p className="text-[0.85rem]">
                {orders.length === 0 ? 'Checkout keranjangmu untuk membuat pesanan pertama.' : 'Coba kata kunci atau filter lain.'}
              </p>
              {orders.length === 0 && (
                <Link to="/keranjang" className={`${btnClasses('primary', 'sm')} mt-2`}>
                  Ke Keranjang
                </Link>
              )}
            </EmptyState>
          ) : (
            filtered.map((o) => {
              const st = statusOf(o);
              const first = o.items?.[0];
              const firstProduct = first?.product || {};
              const rest = (o.items || []).length - 1;
              return (
                <article key={o._id || o.order_number} className="border border-border rounded-2xl px-4 py-3">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[0.72rem] font-extrabold ${BADGES[st]}`}>
                      {BADGE_LABEL[st]}
                    </span>
                    <span className="text-[0.78rem] text-muted font-semibold">#{o.order_number}</span>
                    <span className="ml-auto text-[0.75rem] text-muted">Dipesan: {fmtDateTime(o.created_at)}</span>
                  </div>
                  <div className="flex items-center gap-3 mt-3">
                    {firstProduct.image ? (
                      <img src={firstProduct.image} alt="" className="w-14 h-14 rounded-xl object-cover bg-subtle shrink-0" />
                    ) : (
                      <span className="w-14 h-14 rounded-xl bg-subtle inline-flex items-center justify-center shrink-0">
                        <Package className="w-6 h-6 text-muted" />
                      </span>
                    )}
                    <div className="flex-1 min-w-0">
                      <p className="m-0 font-bold text-[0.92rem] truncate">{firstProduct.name || first?.build_name || 'Pesanan'}</p>
                      <p className="m-0 text-primary font-extrabold text-[0.88rem]">
                        ×{first?.qty || 1} {formatIDR(first?.price_snapshot ?? effectivePrice(firstProduct))}
                        {rest > 0 && <span className="text-secondary font-semibold"> +{rest} item lain</span>}
                      </p>
                    </div>
                    <strong className="whitespace-nowrap max-md:hidden">{formatIDR(o.total_amount)}</strong>
                  </div>
                  <div className="flex items-center gap-2 mt-3 pt-3 border-t border-border flex-wrap">
                    <span className="text-[0.78rem] text-muted">
                      Total: <strong className="text-ink">{formatIDR(o.total_amount)}</strong>
                    </span>
                    <span className="ml-auto flex gap-2">
                      {st === 'pending' && (
                        <button className={btnClasses('secondary', 'sm')} disabled={busyId === o._id} onClick={() => cancel(o)}>
                          Batalkan
                        </button>
                      )}
                      <Link to={`/pesanan/${o._id}`} className={btnClasses('primary', 'sm')}>
                        {st === 'pending' ? 'Bayar Sekarang' : 'Detail Transaksi'}
                      </Link>
                    </span>
                  </div>
                </article>
              );
            })
          )}
        </div>
        {orders !== null && !error && (
          <p className="m-0 mt-3 text-[0.78rem] text-muted">
            Menampilkan {filtered.length} dari {orders.length} transaksi · Status: {CHIPS.find((c) => c.key === chip)?.label}
          </p>
        )}
      </section>

      {orders !== null && orders.length === 0 && !error && (
        <p className="mt-3 text-[0.8rem] text-secondary inline-flex items-center gap-1.5">
          <CircleAlert className="w-4 h-4" /> Riwayat transaksi kosong — mulai dari keranjang.
        </p>
      )}
    </div>
  );
}

export default function Pesanan() {
  return (
    <Shell>
      <ToastProvider>
        <PesananInner />
      </ToastProvider>
    </Shell>
  );
}
