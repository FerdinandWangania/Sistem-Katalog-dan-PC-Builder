import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  Ban,
  Check,
  ChevronLeft,
  CircleAlert,
  CircleCheck,
  Clock,
  Copy,
  CreditCard,
  Package,
  QrCode,
  RotateCcw,
  Wallet,
} from 'lucide-react';
import { cancelOrder, getOrder, payOrder } from '../lib/orders.js';
import { effectivePrice, formatIDR } from '../lib/catalog.js';
import { Shell } from '../components/Layout.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import { btnClasses } from '../components/Button.jsx';

const EXPIRY_HOURS = 24;
// Backend tak menyimpan expired_at (job kedaluwarsa P2 belum ada):
// timer simulasi = created_at + 24 jam.
const PROVIDERS = {
  midtrans: { label: 'QRIS', Icon: QrCode },
  xendit: { label: 'Kartu Kredit', Icon: CreditCard },
  bca_va: { label: 'BCA Virtual Account', short: 'BCA', Icon: Wallet },
  mandiri_va: { label: 'Mandiri Virtual Account', short: 'Mandiri', Icon: Wallet },
};

function guideSteps(provider, identifier, total) {
  const bank = provider === 'mandiri_va' ? 'Livin’ by Mandiri' : 'm-BCA (BCA Mobile)';
  const menu = provider === 'mandiri_va' ? 'Bayar → e-Commerce' : 'm-Transfer → BCA Virtual Account';
  const va = [
    { title: `Masuk ke Aplikasi ${bank}`, desc: `Buka aplikasi, lalu pilih menu ${menu}.` },
    { title: 'Masukkan Nomor Virtual Account', desc: `Ketik atau tempel kode ${identifier} lalu tekan Send.` },
    { title: 'Periksa Nominal', desc: `Pastikan nama penerima BUILDWISE dengan nominal pas senilai ${formatIDR(total)}.` },
    { title: 'Otorisasi Pembayaran', desc: 'Masukkan PIN. Simpan bukti transfer digital hingga status terverifikasi.' },
  ];
  if (provider === 'midtrans') {
    return [
      { title: 'Buka e-Wallet / m-Banking', desc: 'Pilih menu Scan / QRIS di aplikasi pembayaran Anda.' },
      { title: 'Pindai Kode QR', desc: `Pindai kode ${identifier} yang tampil di layar.` },
      { title: 'Periksa Nominal', desc: `Pastikan merchant BUILDWISE dengan nominal pas senilai ${formatIDR(total)}.` },
      { title: 'Konfirmasi Pembayaran', desc: 'Masukkan PIN dan simpan bukti hingga status terverifikasi.' },
    ];
  }
  if (provider === 'xendit') {
    return [
      { title: 'Siapkan Kartu Kredit', desc: 'Pastikan limit mencukupi nominal tagihan.' },
      { title: 'Otorisasi Otomatis (Simulasi)', desc: `Klik Bayar Sekarang — tagihan ${formatIDR(total)} termasuk biaya layanan diproses instan.` },
      { title: 'Simpan Bukti', desc: 'Simpan bukti hingga status pesanan terverifikasi sistem.' },
    ];
  }
  return va;
}

function useCountdown(createdAt, expiredAt) {
  // Pakai expired_at asli dari backend; fallback created_at + 24 jam bila tak ada.
  const target = useMemo(() => {
    if (expiredAt) return new Date(expiredAt).getTime();
    if (createdAt) return new Date(new Date(createdAt).getTime() + EXPIRY_HOURS * 3600e3).getTime();
    return null;
  }, [createdAt, expiredAt]);
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);
  if (!target) return { expired: false, d: 0, h: 0, m: 0, s: 0, deadline: null };
  const diff = target - now;
  return {
    expired: diff <= 0,
    d: Math.max(0, Math.floor(diff / 864e5)),
    h: Math.max(0, Math.floor((diff / 36e5) % 24)),
    m: Math.max(0, Math.floor((diff / 6e4) % 60)),
    s: Math.max(0, Math.floor((diff / 1e3) % 60)),
    deadline: new Date(target),
  };
}

function CopyValue({ value, label }) {
  const toast = useToast();
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(String(value));
      toast.success(`${label} disalin.`);
    } catch {
      toast.error('Gagal menyalin. Salin manual dari layar.');
    }
  };
  return (
    <button
      onClick={copy}
      title={`Salin ${label}`}
      className="inline-flex items-center gap-2 bg-subtle border border-border rounded-full px-3 py-1.5 text-[0.85rem] font-extrabold text-ink cursor-pointer hover:border-primary"
    >
      {value} <Copy className="w-3.5 h-3.5 text-secondary" />
    </button>
  );
}

function fmtDate(v) {
  try {
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(v));
  } catch {
    return '-';
  }
}

function fmtTime(v) {
  try {
    return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(v));
  } catch {
    return '-';
  }
}

function PesananDetailInner() {
  const toast = useToast();
  const navigate = useNavigate();
  const { id } = useParams();
  const [order, setOrder] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const [payFailed, setPayFailed] = useState(false);

  const refresh = async () => {
    try {
      const res = await getOrder(id);
      setOrder(res.data);
      setError(null);
    } catch (e) {
      setError(e);
      setOrder(null);
    }
  };

  useEffect(() => {
    refresh();
  }, [id]);

  const payment = order?.payments?.[order.payments.length - 1] || null;
  const provider = payment?.gateway_provider || 'bca_va';
  const providerMeta = PROVIDERS[provider] || PROVIDERS.bca_va;
  const identifier = payment?.gateway_transaction_id || order?.order_number || '-';
  // Timer dari orders.expired_at (dibuat backend saat checkout); backend juga
  // menolak POST pay bila sudah lewat (422 "Pesanan sudah kedaluwarsa").
  const cd = useCountdown(order?.created_at, order?.expired_at);
  const expired = order?.status === 'pending' && cd.expired;
  const items = order?.items || [];

  const pay = async () => {
    if (busy) return; // kunci anti double-bayar
    setBusy(true);
    setPayFailed(false);
    try {
      await payOrder(order._id);
      toast.success('Pembayaran berhasil dikonfirmasi!');
      await refresh();
    } catch (e) {
      setPayFailed(true);
      await refresh(); // mis. order ternyata sudah kedaluwarsa -> tampil state Kedaluwarsa
      toast.error(e.payload?.message || e.message || 'Pembayaran gagal. Coba Bayar Ulang.');
    } finally {
      setBusy(false);
    }
  };

  const cancel = async () => {
    if (busy) return;
    setBusy(true);
    try {
      await cancelOrder(order._id);
      toast.success('Pesanan dibatalkan, stok dikembalikan.');
      await refresh();
    } catch (e) {
      toast.error(e.message || 'Gagal membatalkan.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="max-w-[1200px] mx-auto px-5 py-6 max-md:px-4 max-md:pb-28">
      <div className="flex items-center gap-2 text-[0.82rem] mb-3">
        <Link to="/pesanan" className="inline-flex items-center gap-1 text-secondary no-underline font-semibold">
          <ChevronLeft className="w-4 h-4" /> Daftar Transaksi
        </Link>
        <span className="text-muted">/</span>
        <span className="text-ink font-bold truncate">{order?.order_number || 'Detail Pesanan'}</span>
      </div>

      {error ? (
        <ErrorState message={error.message} onRetry={refresh} />
      ) : order === null ? (
        <div className="flex flex-col gap-3">
          <Skeleton height="160px" />
          <Skeleton height="300px" />
        </div>
      ) : order.status === 'cancelled' ? (
        <div className="bg-surface border border-border rounded-3xl px-6 py-10 text-center">
          <Ban className="w-10 h-10 text-danger mx-auto" />
          <h1 className="text-[1.4rem] font-extrabold mt-3 mb-1">PESANAN DIBATALKAN</h1>
          <p className="text-secondary text-[0.9rem]">Pesanan {order.order_number} dibatalkan. Stok sudah dikembalikan.</p>
          <div className="flex gap-2 justify-center mt-4 flex-wrap">
            <Link to="/pesanan" className={btnClasses('secondary', 'sm')}>
              Kembali ke Transaksi
            </Link>
            <Link to="/katalog" className={btnClasses('primary', 'sm')}>
              Belanja Lagi
            </Link>
          </div>
        </div>
      ) : ['processing', 'shipped', 'delivered'].includes(order.status) ? (
        <>
          <section className="bg-surface border border-border rounded-3xl px-6 py-6 max-md:px-4">
            <h1 className="m-0 text-[1.4rem] font-extrabold inline-flex items-center gap-2 max-md:text-[1.1rem]">
              <span className="inline-flex items-center justify-center w-8 h-8 rounded-full bg-accent shrink-0">
                <Check className="w-5 h-5 text-ink" />
              </span>
              PEMBAYARAN DITERIMA!
            </h1>
            <p className="text-secondary text-[0.9rem] mt-2 max-w-[640px]">
              Terima kasih, pembayaran Anda telah kami verifikasi secara instan. Tim kami sedang menyiapkan pesanan Anda.
            </p>
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-px bg-border border border-border rounded-2xl overflow-hidden mt-4 max-md:grid-cols-2">
              {[
                ['NOMOR PESANAN', `#${order.order_number}`],
                ['METODE PEMBAYARAN', providerMeta.label],
                ['WAKTU TRANSAKSI', `${fmtDate(payment?.paid_at || order.created_at)} ${fmtTime(payment?.paid_at || order.created_at)}`],
                ['TUJUAN PENGIRIMAN', (order.shipping_address || '-').split('|')[0].trim() || '-'],
              ].map(([label, value]) => (
                <div key={label} className="bg-subtle px-4 py-3">
                  <p className="m-0 text-[0.68rem] font-bold tracking-[0.06em] text-muted">{label}</p>
                  <p className="m-0 mt-1 font-extrabold text-[0.95rem] break-words">{value}</p>
                </div>
              ))}
            </div>
            <div className="flex gap-6 mt-4 flex-wrap max-md:gap-3">
              <div>
                <p className="m-0 text-[0.72rem] font-bold tracking-[0.06em] text-muted">TOTAL JUMLAH TRANSFER</p>
                <div className="mt-1">
                  <CopyValue value={formatIDR(order.total_amount)} label="Total" />
                </div>
              </div>
              <div>
                <p className="m-0 text-[0.72rem] font-bold tracking-[0.06em] text-muted">IDENTIFIER PEMBAYARAN</p>
                <div className="mt-1">
                  <CopyValue value={identifier} label="Identifier" />
                </div>
              </div>
            </div>
          </section>

          <section className="bg-surface border border-border rounded-3xl px-6 py-5 mt-5 max-md:px-4">
            <h2 className="m-0 text-[1.1rem] font-extrabold">Detail Pesanan</h2>
            <ul className="m-0 mt-2 p-0 list-none">
              {items.map((item, idx) => {
                const p = item.product || {};
                return (
                  <li key={item._id || idx} className="flex items-center gap-3 py-2.5 border-t border-border">
                    {p.image ? (
                      <img src={p.image} alt="" className="w-14 h-14 rounded-xl object-cover bg-subtle shrink-0" />
                    ) : (
                      <span className="w-14 h-14 rounded-xl bg-subtle inline-flex items-center justify-center text-[0.6rem] font-bold text-muted shrink-0">
                        {p.category || 'Produk'}
                      </span>
                    )}
                    <span className="flex-1 min-w-0">
                      <span className="block font-bold text-[0.9rem] truncate">{p.name || item.build_name || 'Produk'}</span>
                      <span className="block text-success font-extrabold text-[0.85rem]">
                        ×{item.qty || 1} {formatIDR(item.price_snapshot ?? effectivePrice(p))}
                      </span>
                    </span>
                  </li>
                );
              })}
            </ul>
            <div className="flex gap-2 mt-3 flex-wrap">
              <Link to="/pesanan" className={btnClasses('primary', 'sm')}>
                Ke Daftar Transaksi
              </Link>
            </div>
          </section>
        </>
      ) : expired ? (
        <div className="bg-surface border border-border rounded-3xl px-6 py-10 text-center">
          <Clock className="w-10 h-10 text-danger mx-auto" />
          <h1 className="text-[1.4rem] font-extrabold mt-3 mb-1">PEMBAYARAN KEDALUWARSA</h1>
          <p className="text-secondary text-[0.9rem]">
            Batas waktu {fmtDate(cd.deadline)} telah lewat. Pesanan {order.order_number} tidak bisa dibayar lagi (simulasi).
          </p>
          <div className="flex gap-2 justify-center mt-4 flex-wrap">
            <Link to="/pesanan" className={btnClasses('secondary', 'sm')}>
              Kembali ke Transaksi
            </Link>
            <Link to="/keranjang" className={btnClasses('primary', 'sm')}>
              Buat Pesanan Baru
            </Link>
          </div>
        </div>
      ) : (
        <>
          <p className="text-[0.8rem] text-muted tracking-wide mb-1">TRANSAKSI ID/#{order.order_number}</p>
          <h1 className="m-0 mb-4 text-[1.6rem] font-extrabold max-md:text-[1.25rem]">Selesaikan Pembayaran Anda</h1>
          {payFailed && (
            <div className="flex items-start gap-2 bg-danger/10 border border-danger/30 text-danger rounded-2xl px-4 py-3 mb-5 text-[0.85rem]" role="alert">
              <CircleAlert className="w-4 h-4 mt-0.5 shrink-0" />
              <span className="flex-1">
                <strong>Pembayaran gagal.</strong> Order yang sama masih bisa dipakai — coba Bayar Ulang.
              </span>
              <button className={btnClasses('secondary', 'sm')} disabled={busy} onClick={pay}>
                <RotateCcw className="w-3.5 h-3.5" /> Bayar Ulang
              </button>
            </div>
          )}
          <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
            <div className="flex flex-col gap-5 min-w-0">
              <section className="bg-surface border border-border rounded-3xl px-6 py-5 max-md:px-4">
                <div className="flex items-center justify-between gap-2 flex-wrap">
                  <h2 className="m-0 text-[1.15rem] font-extrabold">{providerMeta.label}</h2>
                  <span className="inline-flex items-center gap-1.5 bg-warning/15 text-warning border border-warning/40 rounded-full px-3 py-1 text-[0.78rem] font-extrabold">
                    <Clock className="w-3.5 h-3.5" /> Menunggu Pembayaran
                  </span>
                </div>
                <div className="grid sm:grid-cols-2 gap-4 mt-4 max-sm:grid-cols-1">
                  <div>
                    <p className="m-0 text-[0.7rem] font-bold tracking-[0.06em] text-muted">TOTAL JUMLAH TRANSFER</p>
                    <div className="mt-1.5">
                      <CopyValue value={formatIDR(order.total_amount)} label="Total" />
                    </div>
                  </div>
                  <div>
                    <p className="m-0 text-[0.7rem] font-bold tracking-[0.06em] text-muted">NOMOR {provider === 'midtrans' ? 'QRIS' : 'VIRTUAL ACCOUNT'} {providerMeta.short || ''}</p>
                    <div className="mt-1.5">
                      <CopyValue value={identifier} label="Identifier" />
                    </div>
                  </div>
                </div>
              </section>

              <section className="bg-surface border border-border rounded-3xl px-6 py-5 max-md:px-4">
                <h2 className="m-0 text-[1rem] font-extrabold">Panduan pembayaran</h2>
                <div className="flex flex-col gap-2.5 mt-3">
                  {guideSteps(provider, identifier, order.total_amount).map((s, i) => (
                    <div key={i} className="flex gap-3 border border-border rounded-2xl px-4 py-3">
                      <span className="font-extrabold text-ink">{i + 1}</span>
                      <span>
                        <strong className="block text-[0.87rem]">{s.title}</strong>
                        <span className="block text-[0.8rem] text-secondary">{s.desc}</span>
                      </span>
                    </div>
                  ))}
                </div>
                <div className="flex gap-2 mt-4 flex-wrap">
                  <button className={btnClasses('cta', null, 'flex-1 justify-center min-w-[200px]')} disabled={busy || expired} onClick={pay}>
                    {busy ? 'Memproses…' : payFailed ? 'Bayar Ulang (Order Sama)' : 'Bayar Sekarang (Simulasi)'}
                  </button>
                  {!expired && (
                  <button className={btnClasses('secondary', null)} disabled={busy} onClick={cancel}>
                    Batalkan Pesanan
                  </button>
                  )}
                </div>
              </section>
            </div>

            <aside className="bg-surface border border-border rounded-3xl px-5 py-4 lg:sticky lg:top-[76px] max-lg:sticky max-lg:bottom-[76px] max-lg:z-30">
              <h2 className="m-0 text-[1rem] font-extrabold">Ringkasan Pembelian</h2>
              <div className="flex justify-between text-[0.85rem] mt-3 pt-3 border-t border-border">
                <span className="text-secondary">Item</span>
                <strong>{items.reduce((s, i) => s + (Number(i.qty) || 0), 0)}</strong>
              </div>
              <div className="flex justify-between text-[0.85rem] mt-2">
                <span className="text-secondary">Subtotal</span>
                <strong>{formatIDR(order.subtotal)}</strong>
              </div>
              <div className="flex justify-between text-[0.85rem] mt-2">
                <span className="text-secondary">Ongkos Kirim</span>
                <strong>{formatIDR(order.shipping_fee)}</strong>
              </div>
              <div className="flex justify-between text-[0.85rem] mt-2">
                <span className="text-secondary">Total Tagihan</span>
                <strong>{formatIDR(order.total_amount)}</strong>
              </div>
              <h3 className="m-0 mt-4 pt-3 border-t border-border text-[0.72rem] font-bold tracking-[0.06em] text-muted">
                BATAS WAKTU PEMBAYARAN
              </h3>
              <div className="grid grid-cols-4 gap-1.5 mt-2 text-center" role="timer" aria-label="Sisa waktu pembayaran">
                {[['Hari', cd.d], ['Jam', cd.h], ['Menit', cd.m], ['Detik', cd.s]].map(([label, v]) => (
                  <div key={label} className="border border-border rounded-xl py-1.5">
                    <p className="m-0 text-[0.62rem] text-primary font-bold">{label}</p>
                    <p className="m-0 font-extrabold text-[1.05rem] tabular-nums">{String(v).padStart(2, '0')}</p>
                  </div>
                ))}
              </div>
              <p className="m-0 mt-2 text-[0.8rem] font-bold">{cd.deadline ? fmtDate(cd.deadline) : ''}</p>
              <div className="mt-3 pt-3 border-t border-border border-dashed">
                <h3 className="m-0 text-[0.9rem] font-extrabold">Alamat Pengiriman</h3>
                <p className="m-0 mt-1.5 text-[0.82rem] text-secondary break-words">{order.shipping_address}</p>
              </div>
            </aside>
          </div>
        </>
      )}
    </div>
  );
}

export default function PesananDetail() {
  return (
    <Shell>
      <ToastProvider>
        <PesananDetailInner />
      </ToastProvider>
    </Shell>
  );
}
