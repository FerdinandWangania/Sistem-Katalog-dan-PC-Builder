import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import {
  Box,
  ChevronLeft,
  CircleAlert,
  CircleCheck,
  CircuitBoard,
  Cpu,
  Database,
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
  Pencil,
  Plug,
  Plus,
  RotateCcw,
  Search,
  Speaker,
  Square,
  Trash2,
  TriangleAlert,
  Video,
  Zap,
} from 'lucide-react';
import { get as getData, mockEnabled, shouldMock } from '../lib/data.js';
import { api, ownerParams } from '../lib/api.js';
import { appendMockCart, getCart, notifyCartChanged, replaceMockBuild } from '../lib/cart.js';
import { effectivePrice, formatIDR } from '../lib/catalog.js';
import { specRows } from '../components/ProductCard.jsx';
import { Shell } from '../components/Layout.jsx';
import { Modal } from '../components/Modal.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import { btnClasses } from '../components/Button.jsx';

const STORAGE_KEY = 'bw_builder_v1';

// 7 slot utama (qty terkunci 1, wajib lengkap) + ekstra via extra_product_ids[].
// Urutan & pengelompokan mengikuti mockup: 2 baris kompatibilitas,
// 9 baris komponen, 5 produk lainnya, 4 aksesoris = 20 baris.
const MAIN_ROWS = [
  { key: 'cpu', label: 'CPU', category: 'CPU', field: 'cpu_id', Icon: Cpu },
  { key: 'motherboard', label: 'Motherboard', category: 'Motherboard', field: 'motherboard_id', Icon: CircuitBoard },
  { key: 'ram', label: 'RAM', category: 'RAM', field: 'ram_id', Icon: MemoryStick },
  { key: 'case', label: 'Case', category: 'Case', field: 'case_id', Icon: Box },
  { key: 'ssd', label: 'SSD', category: 'SSD', field: 'storage_id', Icon: HardDrive },
  { key: 'harddisk', label: 'Harddisk', category: 'Harddisk', extra: true, Icon: Database },
  { key: 'gpu', label: 'VGA', category: 'GPU', field: 'gpu_id', Icon: Gpu },
  { key: 'psu', label: 'PSU', category: 'PSU', field: 'psu_id', Icon: Plug },
  { key: 'caseFan', label: 'Case Fan', category: 'Case Fan', extra: true, Icon: Fan },
];

const OTHER_ROWS = [
  { key: 'thermalPaste', label: 'Thermal Paste', category: 'Thermal Paste', extra: true, Icon: Droplet },
  { key: 'os', label: 'Operating System', category: 'Operating System', extra: true, Icon: Disc3 },
  { key: 'soundCard', label: 'Sound Card', category: 'Sound Card', extra: true, Icon: Speaker },
  { key: 'networkCard', label: 'Network Card', category: 'Network Card', extra: true, Icon: Network },
  { key: 'captureCard', label: 'Capture Card', category: 'Capture Card', extra: true, Icon: Video },
];

const ACCESSORY_ROWS = [
  { key: 'mouse', label: 'Mouse', category: 'Mouse', extra: true, Icon: Mouse },
  { key: 'mousepad', label: 'Mousepad', category: 'Mousepad', extra: true, Icon: Square },
  { key: 'keyboard', label: 'Keyboard', category: 'Keyboard', extra: true, Icon: Keyboard },
  { key: 'monitor', label: 'Monitor', category: 'Monitor', extra: true, Icon: Monitor },
];

const ALL_ROWS = [...MAIN_ROWS, ...OTHER_ROWS, ...ACCESSORY_ROWS];
const REQUIRED_KEYS = MAIN_ROWS.filter((r) => !r.extra).map((r) => r.key);
const ROW_BY_KEY = Object.fromEntries(ALL_ROWS.map((r) => [r.key, r]));

// Batas qty wajar per slot (standar umum). RAM menyesuaikan form factor
// motherboard (ITX umumnya 2 slot DIMM); semua slot juga dibatasi stok.
const SLOT_MAX = {
  cpu: 1,
  motherboard: 1,
  ram: 4,
  case: 1,
  ssd: 4,
  harddisk: 4,
  gpu: 2,
  psu: 1,
  caseFan: 6,
  thermalPaste: 3,
  os: 1,
  soundCard: 2,
  networkCard: 2,
  captureCard: 2,
  mouse: 2,
  mousepad: 2,
  keyboard: 2,
  monitor: 4,
};

function normSocket(v) {
  return String(v || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
}

function powerOf(p) {
  const s = p?.specs || {};
  const raw = s.tdp ?? s.wattage ?? s.wattage_w ?? s.power;
  if (raw === undefined || raw === null || raw === '') return null;
  const m = String(raw).match(/(\d+(?:[.,]\d+)?)/);
  return m ? `${m[1]}W` : String(raw);
}

function loadPersisted() {
  try {
    const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    return {
      sel: raw.sel && typeof raw.sel === 'object' ? raw.sel : {},
      buildName: raw.buildName || '',
      touched: Boolean(raw.touched),
      brandFilter: raw.brandFilter || '',
      socketFilter: raw.socketFilter || '',
      qty: raw.qty && typeof raw.qty === 'object' ? raw.qty : {},
    };
  } catch {
    return { sel: {}, buildName: '', touched: false, brandFilter: '', socketFilter: '', qty: {} };
  }
}

function QtyStepper({ value, max, onChange }) {
  const btn =
    'inline-flex items-center justify-center w-5 h-5 rounded-full border-0 bg-transparent text-ink cursor-pointer disabled:opacity-40 disabled:cursor-default';
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-accent px-1.5 py-1 text-ink" title={`Jumlah (maks ${max} untuk slot ini)`}>
      <button aria-label="Tambah jumlah" disabled={value >= max} onClick={() => onChange(value + 1)} className={btn}>
        <Plus className="w-3.5 h-3.5" />
      </button>
      <span aria-live="polite" className="min-w-5 text-center text-[0.8rem] font-extrabold">
        {value}
      </span>
      <button aria-label="Kurangi jumlah" disabled={value <= 1} onClick={() => onChange(value - 1)} className={btn}>
        <Minus className="w-3.5 h-3.5" />
      </button>
    </span>
  );
}

function QtyEmpty() {
  return (
    <span
      className="inline-flex items-center gap-1 rounded-full bg-accent px-1.5 py-1 text-ink opacity-60"
      title="Jumlah terkunci sampai komponen dipilih"
    >
      <Plus className="w-3.5 h-3.5 opacity-40" />
      <span className="min-w-5 text-center text-[0.8rem] font-extrabold">–</span>
      <Minus className="w-3.5 h-3.5 opacity-40" />
    </span>
  );
}

function SlotRow({ row, product, qty, maxQty, onQty, onAdd, onClear, detailHref, disabled = false, disabledHint = '' }) {
  const { Icon } = row;
  const trash = (
    <button
      onClick={onClear}
      disabled={!product}
      aria-label={product ? `Hapus ${row.label}` : `${row.label} kosong`}
      title={product ? `Hapus ${row.label}` : `${row.label} kosong`}
      className={`inline-flex items-center justify-center w-7 h-7 rounded-lg border-0 cursor-pointer shrink-0 ${
        product ? 'bg-danger/10 text-danger hover:bg-danger/20' : 'bg-subtle text-muted/40 cursor-default'
      } disabled:cursor-default`}
    >
      <Trash2 className="w-4 h-4" />
    </button>
  );
  const control = product ? (
    <Link to={detailHref} className="text-[0.85rem] font-bold text-ink no-underline hover:text-primary break-words" title="Lihat detail produk">
      {product.name}
    </Link>
  ) : (
    <>
      <button
        className={btnClasses('cta', 'sm')}
        onClick={onAdd}
        disabled={disabled}
        title={disabled ? disabledHint : `Pilih ${row.label}`}
      >
        <Plus className="w-4 h-4" /> Tambah Komponen
      </button>
      <span className="text-[0.78rem] text-placeholder italic truncate hidden sm:inline">
        {disabled ? disabledHint : `Belum ada ${row.label} dipilih`}
      </span>
    </>
  );
  return (
    <>
      {/* Desktop: baris tabel */}
      <div className="hidden md:grid grid-cols-[150px_minmax(0,1fr)_130px_110px_40px] items-center gap-2 px-4 py-2.5 border-b border-border last:border-0">
        <div className="flex items-center gap-2 min-w-0">
          <span className="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-subtle text-secondary shrink-0">
            <Icon className="w-4 h-4" />
          </span>
          <span className="text-[0.82rem] font-bold text-ink truncate">{row.label}</span>
        </div>
        <div className="flex items-center gap-3 min-w-0">{control}</div>
        <div className="text-[0.82rem] font-bold text-ink whitespace-nowrap">
          {product ? formatIDR(effectivePrice(product)) : <span className="text-muted font-normal">-</span>}
        </div>
        <div>{product ? <QtyStepper value={qty} max={maxQty} onChange={onQty} /> : <QtyEmpty />}</div>
        <div className="flex justify-end">{trash}</div>
      </div>

      {/* Mobile: kartu vertikal per baris */}
      <div className="md:hidden bg-surface border border-border rounded-2xl p-3.5 mb-2.5">
        <div className="flex items-center gap-2">
          <span className="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-subtle text-secondary shrink-0">
            <Icon className="w-4 h-4" />
          </span>
          <span className="text-[0.82rem] font-bold text-ink">{row.label}</span>
          <span className="ml-auto inline-flex">{trash}</span>
        </div>
        <div className="mt-2.5">
          {product ? (
            control
          ) : (
            <div className="flex flex-col gap-1.5">
              <button
                className={btnClasses('cta', 'sm', 'w-full justify-center')}
                onClick={onAdd}
                disabled={disabled}
                title={disabled ? disabledHint : `Pilih ${row.label}`}
              >
                <Plus className="w-4 h-4" /> Tambah Komponen
              </button>
              <span className="text-[0.75rem] text-placeholder italic">
                {disabled ? disabledHint : `Belum ada ${row.label} dipilih`}
              </span>
            </div>
          )}
        </div>
        <div className="mt-2.5 pt-2.5 border-t border-border flex items-center justify-between gap-2">
          <div>
            <p className="m-0 text-[0.68rem] font-bold tracking-[0.06em] text-muted">HARGA</p>
            <p className="m-0 text-[0.85rem] font-bold text-ink">
              {product ? formatIDR(effectivePrice(product)) : <span className="text-muted font-normal">-</span>}
            </p>
          </div>
          {product ? <QtyStepper value={qty} max={maxQty} onChange={onQty} /> : <span className="text-muted text-[0.78rem]">–</span>}
        </div>
      </div>
    </>
  );
}

function PickerModal({ slotKey, initialBrand = '', initialSocket = '', onPick, onClose }) {
  const cfg = ROW_BY_KEY[slotKey];
  const [q, setQ] = useState('');
  const [debouncedQ, setDebouncedQ] = useState('');
  const [brand, setBrand] = useState(initialBrand);
  const [socket, setSocket] = useState(initialSocket);
  const [brands, setBrands] = useState([]);
  const [sockets, setSockets] = useState([]);
  const [items, setItems] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedQ(q.trim()), 350);
    return () => clearTimeout(t);
  }, [q]);

  // Opsi brand/socket diambil dari seluruh produk kategori ini (stabil antar filter).
  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const res = await getData('/products', { query: { category: cfg.category } });
        if (!alive) return;
        const list = res.data || [];
        setBrands([...new Set(list.map((p) => p.brand).filter(Boolean))].sort());
        setSockets([...new Set(list.map((p) => p?.specs?.socket).filter(Boolean))].sort());
      } catch {
        if (alive) {
          setBrands([]);
          setSockets([]);
        }
      }
    })();
    return () => {
      alive = false;
    };
  }, [cfg.category]);

  useEffect(() => {
    let alive = true;
    setError(null);
    (async () => {
      try {
        const query = { category: cfg.category };
        if (brand) query.brand = brand;
        if (socket && (cfg.category === 'CPU' || cfg.category === 'Motherboard')) query.socket = socket;
        if (debouncedQ) query.q = debouncedQ;
        const res = await getData('/products', { query });
        if (!alive) return;
        let list = res.data || [];
        // Mock fallback mengabaikan filter socket: saring sisi klien agar konsisten.
        // Hanya untuk kategori CPU/Motherboard; kategori lain tidak punya socket.
        if (socket && (cfg.category === 'CPU' || cfg.category === 'Motherboard')) {
          list = list.filter((p) => normSocket(p?.specs?.socket).includes(normSocket(socket)));
        }
        if (brand) list = list.filter((p) => p.brand === brand);
        setItems(list);
      } catch (e) {
        if (alive) {
          setError(e);
          setItems([]);
        }
      }
    })();
    return () => {
      alive = false;
    };
  }, [cfg.category, brand, socket, debouncedQ]);

  return (
    <Modal open title={`Pilih ${cfg.label}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <label className="flex items-center gap-2 bg-subtle border border-border rounded-xl px-3 py-2">
          <Search className="w-4 h-4 text-secondary shrink-0" />
          <input
            autoFocus
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder={`Pilih ${cfg.label}`}
            aria-label={`Cari ${cfg.label}`}
            className="flex-1 bg-transparent border-0 outline-none text-[0.9rem] text-ink placeholder:text-placeholder"
          />
        </label>
        {(brands.length > 0 || sockets.length > 0) && (
          <div className="flex flex-wrap gap-2">
            {brands.map((b) => (
              <button
                key={b}
                onClick={() => setBrand((v) => (v === b ? '' : b))}
                aria-pressed={brand === b}
                className={`rounded-full border px-3 py-1 text-[0.75rem] font-bold cursor-pointer ${
                  brand === b ? 'bg-ink text-surface border-transparent' : 'bg-surface text-secondary border-border'
                }`}
              >
                {b}
              </button>
            ))}
            {sockets.map((s) => (
              <button
                key={s}
                onClick={() => setSocket((v) => (v === s ? '' : s))}
                aria-pressed={socket === s}
                className={`rounded-full border px-3 py-1 text-[0.75rem] font-bold cursor-pointer ${
                  socket === s ? 'bg-primary text-surface border-transparent' : 'bg-surface text-secondary border-border'
                }`}
              >
                Socket: {s}
              </button>
            ))}
          </div>
        )}
        <div className="flex flex-col max-h-[46vh] overflow-auto pr-1">
          {error ? (
            <p className="text-danger text-[0.85rem]">Gagal memuat produk: {error.message}</p>
          ) : items === null ? (
            <p className="text-secondary text-[0.85rem]">Memuat produk…</p>
          ) : items.length === 0 ? (
            <p className="text-secondary text-[0.85rem]">Tidak ada {cfg.label} yang cocok dengan filter.</p>
          ) : (
            items.map((p) => {
              const id = p._id || p.id;
              const power = powerOf(p);
              return (
                <div key={id} className="flex gap-3 max-md:flex-wrap py-3 border-b border-border last:border-0">
                  {p.image ? (
                    <img src={p.image} alt="" className="w-14 h-14 rounded-xl object-cover bg-subtle shrink-0" />
                  ) : (
                    <span className="w-14 h-14 rounded-xl bg-subtle inline-flex items-center justify-center text-[0.65rem] font-bold text-muted shrink-0">
                      {p.category}
                    </span>
                  )}
                  <div className="flex-1 min-w-0">
                    <Link
                      to={`/produk/${id}?mode=builder&slot=${cfg.key}&return=${encodeURIComponent(`/builder?picker=${cfg.key}`)}`}
                      className="font-bold text-ink no-underline hover:text-primary text-[0.95rem]"
                    >
                      {p.name}
                    </Link>
                    <p className="m-0 text-[0.78rem] text-secondary">
                      Merek: {p.brand || '-'}
                      {power ? ` · Daya: ${power}` : ''}
                      {Number(p.stock) <= 0 ? ' · Stok habis' : ''}
                    </p>
                    <div className="flex flex-wrap gap-1.5 mt-1.5">
                      {specRows(p).map((s) => (
                        <span key={s.label} className="bg-subtle text-secondary rounded-md px-2 py-0.5 text-[0.72rem] font-semibold">
                          {s.label}: {s.value}
                        </span>
                      ))}
                    </div>
                    <p className="m-0 mt-1 font-extrabold text-[0.9rem]">{formatIDR(effectivePrice(p))}</p>
                  </div>
                  <button
                    className={btnClasses('primary', 'sm', 'self-start shrink-0 max-md:w-full max-md:justify-center')}
                    disabled={Number(p.stock) <= 0}
                    onClick={() => onPick(p)}
                  >
                    Pilih
                  </button>
                </div>
              );
            })
          )}
        </div>
      </div>
    </Modal>
  );
}

// Deskripsi tiap varian socket (ditampilkan di modal Pilih Socket).
const SOCKET_INFO = {
  AM5: 'Socket Release: September 2022. Type: PC Desktop. CPU families: Ryzen 7000, 8000, 9000 series.',
  AM4: 'Socket Release: September 2016. Type: PC Desktop. CPU families: Ryzen 1000–5000 series.',
  LGA1700: 'Socket Release: November 2021. Type: PC Desktop. CPU families: 12th, 13th, 14th Gen.',
  LGA1200: 'Socket Release: April 2020. Type: PC Desktop. CPU families: 10th Gen, 11th Gen.',
};

// Modal pilih Brand CPU / Socket: berisi daftar opsi, bukan daftar produk.
// Brand = merek unik dari kategori CPU; Socket = socket unik milik brand terpilih.
function CompatOptionModal({ mode, brandFilter, onPick, onClose }) {
  const [options, setOptions] = useState(null);

  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const query = { category: 'CPU' };
        if (mode === 'socket' && brandFilter) query.brand = brandFilter;
        const res = await getData('/products', { query });
        if (!alive) return;
        const list = res.data || [];
        if (mode === 'brand') {
          setOptions([...new Set(list.map((p) => p.brand).filter(Boolean))].sort());
        } else {
          setOptions([...new Set(list.map((p) => p?.specs?.socket).filter(Boolean))].sort());
        }
      } catch {
        if (alive) setOptions([]);
      }
    })();
    return () => {
      alive = false;
    };
  }, [mode, brandFilter]);

  return (
    <Modal open title={mode === 'brand' ? 'Pilih Brand CPU' : 'Pilih Socket'} onClose={onClose}>
      <div className="flex flex-col gap-2">
        {mode === 'socket' && !brandFilter && (
          <p className="m-0 text-[0.8rem] text-secondary">Menampilkan semua socket. Pilih Brand CPU dulu untuk menyaring per merek.</p>
        )}
        {options === null ? (
          <p className="text-secondary text-[0.85rem]">Memuat opsi…</p>
        ) : options.length === 0 ? (
          <p className="text-secondary text-[0.85rem]">Tidak ada opsi yang cocok.</p>
        ) : (
          options.map((opt) => (
            <button
              key={opt}
              onClick={() => onPick(opt)}
              className="w-full text-left bg-subtle border border-border rounded-xl px-4 py-2.5 text-ink cursor-pointer hover:border-primary"
            >
              <span className="block text-[0.9rem] font-bold">{opt}</span>
              {mode === 'socket' && SOCKET_INFO[opt] && (
                <span className="block text-[0.78rem] font-normal text-secondary mt-0.5">{SOCKET_INFO[opt]}</span>
              )}
            </button>
          ))
        )}
      </div>
    </Modal>
  );
}

function BuilderInner() {
  const toast = useToast();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [persisted] = useState(loadPersisted);
  const [sel, setSel] = useState(persisted.sel);
  const [buildName, setBuildName] = useState(persisted.buildName);
  const [nameTouched, setNameTouched] = useState(persisted.touched);
  const [brandFilter, setBrandFilter] = useState(persisted.brandFilter || '');
  const [socketFilter, setSocketFilter] = useState(persisted.socketFilter || '');
  const [qty, setQty] = useState(persisted.qty || {});
  const [compatModal, setCompatModal] = useState(null); // 'brand' | 'socket' | null
  // Mode edit rakitan dari keranjang (FE-07): ?edit_build={build_id}.
  const editBuildId = searchParams.get('edit_build');
  const [editLoaded, setEditLoaded] = useState(false);
  const [ignorePsu, setIgnorePsu] = useState(false);
  const [analysis, setAnalysis] = useState(null);
  const [validating, setValidating] = useState(false);
  const [validateError, setValidateError] = useState(null);

  const pickerKey = searchParams.get('picker');
  const pickerCfg = pickerKey ? ROW_BY_KEY[pickerKey] : null;

  const openPicker = (key) => {
    const next = new URLSearchParams(searchParams);
    next.set('picker', key);
    setSearchParams(next);
  };
  const closePicker = () => {
    const next = new URLSearchParams(searchParams);
    next.delete('picker');
    setSearchParams(next, { replace: true });
  };

  // Terapkan pilihan dari halaman detail (?slot=&product=), lalu bersihkan URL.
  // Pilihan komponen PC diabaikan bila Brand/Socket belum dipilih (alur terkunci).
  useEffect(() => {
    const slot = searchParams.get('slot');
    const pid = searchParams.get('product');
    if (!slot || !pid || !ROW_BY_KEY[slot]) return;
    const next = new URLSearchParams(searchParams);
    next.delete('slot');
    next.delete('product');
    next.delete('picker');
    setSearchParams(next, { replace: true });
    const isPcRow = MAIN_ROWS.some((r) => r.key === slot);
    if (isPcRow && (!brandFilter || !socketFilter)) return;
    let alive = true;
    (async () => {
      try {
        const res = await getData(`/products/${pid}`);
        if (alive && res.data) setSel((s) => ({ ...s, [slot]: res.data }));
      } catch {
        // abaikan: pengguna tetap di builder tanpa perubahan
      }
    })();
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams, brandFilter, socketFilter]);

  // Mode edit: prefill 7 slot + ekstra dari isi rakitan di keranjang.
  useEffect(() => {
    if (!editBuildId || editLoaded) return;
    let alive = true;
    (async () => {
      try {
        const res = await getCart();
        const build = [...(res.data?.builder_builds || [])].find((b) => String(b.build_id) === String(editBuildId));
        if (!alive) return;
        if (!build) {
          toast.error('Rakitan tidak ditemukan di keranjang.');
        } else {
          const nextSel = {};
          const nextQty = {};
          for (const item of build.components || []) {
            const p = item.product;
            if (!p) continue;
            const row = ALL_ROWS.find((r) => r.category === p.category);
            if (!row) continue;
            nextSel[row.key] = p;
            if (Number(item.qty) > 1) nextQty[row.key] = Number(item.qty);
          }
          setSel(nextSel);
          setQty(nextQty);
          if (build.build_name) {
            setBuildName(String(build.build_name).slice(0, 60));
            setNameTouched(true);
          }
          const cpu = (build.components || []).map((i) => i.product).find((p) => p?.category === 'CPU');
          if (cpu?.brand) setBrandFilter(cpu.brand);
          if (cpu?.specs?.socket) setSocketFilter(cpu.specs.socket);
        }
      } catch (e) {
        if (alive) toast.error(e.message || 'Gagal memuat rakitan.');
      } finally {
        if (alive) setEditLoaded(true);
      }
    })();
    return () => {
      alive = false;
    };
  }, [editBuildId, editLoaded, toast]);

  // Persistensi lokal agar bolak-balik ke detail produk tidak menghilangkan rakitan.
  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ sel, buildName, touched: nameTouched, brandFilter, socketFilter, qty }));
    } catch {
      // abaikan bila storage tidak tersedia
    }
  }, [sel, buildName, nameTouched, brandFilter, socketFilter, qty]);

  // Batas qty satu slot: standar kategori (RAM ikut form factor board) + stok.
  const maxQty = (key) => {
    const stock = Number(sel[key]?.stock);
    let max = SLOT_MAX[key] ?? 1;
    if (key === 'ram') {
      const ff = String(sel.motherboard?.specs?.form_factor || '').toUpperCase().replace(/[^A-Z]/g, '');
      if (ff === 'ITX' || ff === 'MINIITX') max = Math.min(max, 2);
    }
    if (Number.isFinite(stock) && stock > 0) max = Math.min(max, stock);
    return Math.max(1, max);
  };
  const qtyOf = (key) => Math.min(Math.max(1, Number(qty[key]) || 1), maxQty(key));

  // Prefill nama rakitan dari CPU (maks 60 karakter, tetap bisa diedit).
  const cpuName = sel.cpu?.name || '';
  useEffect(() => {
    if (!nameTouched) setBuildName(cpuName.slice(0, 60));
  }, [cpuName, nameTouched]);

  const payload = useMemo(() => {
    const body = {};
    for (const row of MAIN_ROWS) {
      if (row.extra) continue;
      const p = sel[row.key];
      if (p) body[row.field] = p._id || p.id;
    }
    const extras = ALL_ROWS.filter((r) => r.extra).map((r) => sel[r.key]).filter(Boolean).map((p) => p._id || p.id);
    if (extras.length > 0) body.extra_product_ids = extras;
    return body;
  }, [sel]);
  const payloadKey = JSON.stringify(payload);

  // Validasi live (debounce) ke POST /builder/validate tiap ada perubahan.
  useEffect(() => {
    const t = setTimeout(async () => {
      setValidating(true);
      try {
        const res = await api.post('/builder/validate', {
          body: { ...JSON.parse(payloadKey), build_name: buildName.slice(0, 60), ignore_psu_warning: ignorePsu },
        });
        setAnalysis(res.data);
        setValidateError(null);
      } catch (e) {
        if (shouldMock(e)) {
          const t = Object.entries(sel).reduce((sum, [k, p]) => sum + (p ? effectivePrice(p) * qtyOf(k) : 0), 0);
          setAnalysis(clientFallback(sel, buildName, t));
          setValidateError(null);
        } else {
          setValidateError(e);
        }
      } finally {
        setValidating(false);
      }
    }, 450);
    return () => clearTimeout(t);
  }, [payloadKey, buildName, ignorePsu, sel, qty]);

  const clientTotal = useMemo(
    () => Object.entries(sel).reduce((sum, [k, p]) => sum + (p ? effectivePrice(p) * qtyOf(k) : 0), 0),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [sel, qty],
  );
  // Sama dengan grand_total backend saat semua qty 1 (aritmetika & harga sama);
  // bila ada qty > 1, total diperluas per qty di sisi klien.
  const allSingle = Object.keys(sel).every((k) => qtyOf(k) === 1);
  const total = allSingle ? (analysis?.grand_total ?? analysis?.total_price ?? clientTotal) : clientTotal;
  const watt = analysis?.power_analysis?.total_estimated_watt;
  const psuIssue =
    analysis?.error_details?.some((d) => d.rule === 'RULE-03') ||
    analysis?.warning_details?.some((d) => d.rule === 'RULE-03');
  const recommendedWatt = analysis?.power_analysis?.recommended_min_psu_watt;
  const missingSlots = analysis?.missing_slots || [];
  const complete = REQUIRED_KEYS.every((k) => sel[k]);
  const canContinue = analysis?.is_compatible === true;
  const errors = analysis?.errors || [];
  const warnings = analysis?.warnings || [];

  // Panel merah hanya untuk masalah kompatibilitas asli. Error "belum dipilih"
  // (RULE-05 slot kosong) disembunyikan karena kotak info biru sudah
  // mencantumkan slot kosong — tapi tetap memblokir tombol lanjut.
  const displayErrors = errors.filter((m, i) => {
    const d = analysis?.error_details?.[i];
    const text = (d?.message || m || '');
    if ((d?.rule === 'RULE-05' || !d) && /belum dipilih/.test(text)) return false;
    return true;
  });
  // Warning mode offline (fallback mock) tidak ditampilkan sebagai panel.
  const displayWarnings = warnings.filter((m) => !/^mode offline/i.test(m || ''));

  const clearSlot = (key) => setSel((s) => ({ ...s, [key]: null }));
  const clearAll = () => {
    setSel({});
    setBuildName('');
    setNameTouched(false);
    setIgnorePsu(false);
  };

  // Filter kompatibilitas: pilihan yang konflik ikut dibersihkan.
  const pickBrand = (b) => {
    setBrandFilter(b);
    setSocketFilter('');
    setSel((s) => (s.cpu && s.cpu.brand !== b ? { ...s, cpu: null } : s));
    setCompatModal(null);
  };
  const pickSocket = (sk) => {
    setSocketFilter(sk);
    setSel((s) => {
      const next = { ...s };
      if (next.cpu && normSocket(next.cpu?.specs?.socket) !== normSocket(sk)) next.cpu = null;
      if (next.motherboard && normSocket(next.motherboard?.specs?.socket) !== normSocket(sk)) next.motherboard = null;
      return next;
    });
    setCompatModal(null);
  };

  // Simpan rakitan ke keranjang (FE-08): validasi backend dulu via
  // POST /builder/add-to-cart, samakan qty > 1, lalu ke /keranjang.
  const [saving, setSaving] = useState(false);
  const saveToCart = async () => {
    if (saving) return;
    setSaving(true);
    const body = { ...JSON.parse(payloadKey), build_name: buildName.slice(0, 60), ignore_psu_warning: ignorePsu };
    try {
      const res = await api.post('/builder/add-to-cart', { query: ownerParams(), body });
      const buildId = res.data?.build_id;
      // Endpoint selalu menyimpan qty 1: tulis ulang item yang qty-nya > 1
      // (POST /cart/items mendukung source_type builder + build_id + qty).
      const multi = Object.keys(sel).filter((k) => sel[k] && qtyOf(k) > 1);
      if (buildId && multi.length > 0) {
        const cartRes = await getCart();
        const saved = [...(cartRes.data?.builder_builds || [])].find((b) => String(b.build_id) === String(buildId));
        for (const comp of saved?.components || []) {
          const key = multi.find((k) => String(sel[k]?._id || sel[k]?.id) === String(comp.product_id));
          const q = key ? qtyOf(key) : 1;
          if (q <= 1) continue;
          await api.delete(`/cart/items/${comp._id}`, { query: ownerParams() });
          await api.post('/cart/items', {
            body: { ...ownerParams(), product_id: comp.product_id, qty: q, source_type: 'builder', build_id: buildId },
          });
        }
      }
      notifyCartChanged();
      toast.success('Rakitan masuk keranjang.');
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch {
        // abaikan
      }
      navigate('/keranjang');
    } catch (e) {
      if (shouldMock(e)) {
        const buildId = `mock-build-${Date.now()}`;
        appendMockCart(
          Object.keys(sel)
            .filter((k) => sel[k])
            .map((k) => ({
              product_id: sel[k]._id || sel[k].id,
              qty: qtyOf(k),
              source_type: 'builder',
              build_id: buildId,
              build_name: (buildName || sel.cpu?.name || 'Rakitan PC').slice(0, 60),
            })),
        );
        toast.success('Rakitan masuk keranjang (mode offline).');
        try {
          localStorage.removeItem(STORAGE_KEY);
        } catch {
          // abaikan
        }
        navigate('/keranjang');
      } else {
        toast.error(e.payload?.message || e.message || 'Gagal menyimpan rakitan.');
      }
    } finally {
      setSaving(false);
    }
  };

  // Simpan atomik pengganti rakitan (PUT /builder/builds/{id}, revalidasi backend).
  const saveEdit = async () => {
    if (!editBuildId) return;
    const entries = () =>
      Object.keys(sel)
        .filter((k) => sel[k])
        .map((k) => ({
          product_id: sel[k]._id || sel[k].id,
          qty: qtyOf(k),
          source_type: 'builder',
          build_id: editBuildId,
          build_name: (buildName || sel.cpu?.name || 'Rakitan PC').slice(0, 60),
        }));
    try {
      await api.put(`/builder/builds/${editBuildId}`, {
        query: ownerParams(),
        body: { ...JSON.parse(payloadKey), build_name: buildName.slice(0, 60), ignore_psu_warning: ignorePsu },
      });
      notifyCartChanged();
      toast.success('Rakitan berhasil diperbarui.');
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch {
        // abaikan
      }
      navigate('/keranjang');
    } catch (e) {
      if (shouldMock(e)) {
        replaceMockBuild(editBuildId, entries());
        toast.success('Rakitan berhasil diperbarui (mode offline).');
        try {
          localStorage.removeItem(STORAGE_KEY);
        } catch {
          // abaikan
        }
        navigate('/keranjang');
        return;
      }
      toast.error(e.payload?.message || e.message || 'Gagal menyimpan perubahan.');
    }
  };

  const detailHref = (key) => {
    const p = sel[key];
    if (!p) return '#';
    const id = p._id || p.id;
    return `/produk/${id}?mode=builder&slot=${key}&return=${encodeURIComponent(`/builder?picker=${key}`)}`;
  };

  // Alur terkunci bertahap: komponen PC butuh Brand (1) + Socket (2) dulu.
  // Produk non-PC (thermal paste, OS, sound/network/capture card, aksesoris) bebas.
  const pcGated = !brandFilter || !socketFilter;
  const pcGateHint = !brandFilter ? 'Pilih Brand CPU dulu' : 'Pilih Socket dulu';

  return (
    <div className="max-w-[1100px] mx-auto px-5 py-6 max-md:px-4 max-md:pb-28">
      <div className="flex items-center gap-2 text-[0.82rem] mb-3">
        <Link to="/" className="inline-flex items-center gap-1 text-secondary no-underline font-semibold">
          <ChevronLeft className="w-4 h-4" /> Beranda
        </Link>
        <span className="text-muted">/</span>
        <span className="text-ink font-bold">PC Builder</span>
        {validating && <span className="text-muted text-[0.78rem]">· memvalidasi…</span>}
      </div>

      {/* Nama rakitan: prefill CPU, maks 60 karakter, ikut dikirim ke validate */}
      {editBuildId && (
        <div className="flex items-center gap-2 bg-info/10 border border-info/30 rounded-2xl px-4 py-3 mb-5 text-[0.85rem] text-ink" role="status">
          <Pencil className="w-4 h-4 shrink-0" />
          <span className="flex-1">
            <strong>Mode edit rakitan.</strong> Ubah komponen, lalu simpan — perubahan mengganti rakitan utuh setelah revalidasi.
          </span>
          <button className={btnClasses('secondary', 'sm')} onClick={() => navigate('/keranjang')}>
            Batal
          </button>
        </div>
      )}
      <section className="bg-surface border border-border rounded-2xl px-5 py-4 mb-5">
        <div className="flex items-center justify-between gap-3 flex-wrap">
          <div>
            <h1 className="m-0 text-[1.05rem] font-extrabold tracking-[0.04em]">PC BUILDER</h1>
            <p className="m-0 text-[0.8rem] text-secondary">Rakit, cek kompatibilitas live, lalu lanjut ke pembayaran.</p>
          </div>
          <label className="flex items-center gap-2 text-[0.8rem] font-bold text-secondary">
            Nama rakitan
            <input
              value={buildName}
              maxLength={60}
              onChange={(e) => {
                setBuildName(e.target.value);
                setNameTouched(true);
              }}
              placeholder={cpuName ? cpuName.slice(0, 60) : 'cth. Rakitan Gaming AMD'}
              aria-label="Nama rakitan (maks 60 karakter)"
              className="bg-subtle border border-border rounded-full px-3 py-1.5 text-[0.82rem] text-ink w-56 max-md:w-auto max-md:flex-1 max-md:min-w-0 outline-none focus:border-primary"
            />
            <span className="text-muted font-semibold">{buildName.length}/60</span>
          </label>
        </div>
      </section>

      {validateError && (
        <div className="flex items-start gap-2 bg-danger/10 border border-danger/30 text-danger rounded-2xl px-4 py-3 mb-5 text-[0.85rem]" role="alert">
          <CircleAlert className="w-4 h-4 mt-0.5 shrink-0" />
          <span>
            Validasi live gagal: {validateError.message || 'backend tidak terjangkau.'}{' '}
            {mockEnabled ? '' : 'Total dihitung dari harga produk yang dipilih.'}
          </span>
        </div>
      )}

      {/* Panel kompatibilitas: filter Brand CPU + Socket, menyaring picker CPU/Motherboard */}
      <h2 className="text-[1rem] font-extrabold tracking-[0.04em] mt-6 mb-2">PENGECEKAN KOMPATIBILITAS</h2>
      <section className="bg-surface border border-border rounded-2xl overflow-hidden">
        <div className="hidden md:grid grid-cols-[150px_minmax(0,1fr)_40px] items-center gap-2 bg-ink text-surface px-4 py-2.5 text-[0.72rem] font-bold tracking-[0.06em]">
          <span>KOMPONEN</span>
          <span>NAMA</span>
          <span />
        </div>
        <div className="grid grid-cols-[104px_minmax(0,1fr)_36px] md:grid-cols-[150px_minmax(0,1fr)_40px] items-center gap-1.5 md:gap-2 px-3.5 md:px-4 py-2.5 border-b border-border">
          <span className="text-[0.82rem] font-bold">Brand CPU</span>
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 min-w-0">
            {!brandFilter ? (
              <>
                <button className={btnClasses('cta', 'sm')} onClick={() => setCompatModal('brand')}>
                  <Plus className="w-4 h-4" /> Pilih Brand
                </button>
                <span className="text-[0.78rem] text-placeholder italic">Belum ada Brand CPU dipilih</span>
              </>
            ) : (
              <span className="text-[0.85rem] font-semibold truncate">{brandFilter}</span>
            )}
          </div>
          <button
            onClick={() => {
              setBrandFilter('');
              setSocketFilter('');
            }}
            disabled={!brandFilter}
            aria-label="Hapus filter Brand CPU"
            className={`inline-flex items-center justify-center w-7 h-7 rounded-lg border-0 justify-self-end ${
              brandFilter ? 'bg-danger/10 text-danger cursor-pointer' : 'bg-subtle text-muted/40'
            }`}
          >
            <Trash2 className="w-4 h-4" />
          </button>
        </div>
        <div className="grid grid-cols-[104px_minmax(0,1fr)_36px] md:grid-cols-[150px_minmax(0,1fr)_40px] items-center gap-1.5 md:gap-2 px-3.5 md:px-4 py-2.5">
          <span className="text-[0.82rem] font-bold">Socket</span>
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 min-w-0">
            {!socketFilter ? (
              <>
                <button
                  className={btnClasses('cta', 'sm')}
                  onClick={() => setCompatModal('socket')}
                  disabled={!brandFilter}
                  title={!brandFilter ? 'Pilih Brand CPU dulu' : 'Pilih Socket'}
                >
                  <Plus className="w-4 h-4" /> Pilih Socket
                </button>
                <span className="text-[0.78rem] text-placeholder italic">
                  {!brandFilter ? 'Pilih Brand CPU dulu untuk membuka pilihan Socket' : 'Belum ada Socket dipilih'}
                </span>
              </>
            ) : (
              <span className="text-[0.85rem] font-semibold truncate">{socketFilter}</span>
            )}
          </div>
          <button
            onClick={() => setSocketFilter('')}
            disabled={!socketFilter}
            aria-label="Hapus filter Socket"
            className={`inline-flex items-center justify-center w-7 h-7 rounded-lg border-0 justify-self-end ${
              socketFilter ? 'bg-danger/10 text-danger cursor-pointer' : 'bg-subtle text-muted/40'
            }`}
          >
            <Trash2 className="w-4 h-4" />
          </button>
        </div>
      </section>

      {/* Komponen utama */}
      <h2 className="text-[1rem] font-extrabold tracking-[0.04em] mt-6 mb-2">PILIH KOMPONEN PC ANDA</h2>
      {pcGated && (
        <p className="m-0 mb-2 text-[0.8rem] text-secondary">
          Terkunci — {pcGateHint}. Produk lainnya & aksesoris di bawah tetap bisa dipilih.
        </p>
      )}
      <section className="bg-surface border border-border rounded-2xl overflow-hidden max-md:bg-transparent max-md:border-0 max-md:overflow-visible">
        <div className="overflow-x-auto max-md:overflow-visible">
          <div className="min-w-[760px] max-md:min-w-0">
            <div className="hidden md:grid grid-cols-[150px_minmax(0,1fr)_130px_110px_40px] gap-2 bg-ink text-surface px-4 py-2.5 text-[0.72rem] font-bold tracking-[0.06em]">
              <span>KOMPONEN</span>
              <span>SPESIFIKASI</span>
              <span>HARGA</span>
              <span>JUMLAH</span>
              <span />
            </div>
            {MAIN_ROWS.map((row) => (
              <SlotRow
                key={row.key}
                row={row}
                product={sel[row.key] || null}
                qty={qtyOf(row.key)}
                maxQty={maxQty(row.key)}
                onQty={(v) => setQty((q) => ({ ...q, [row.key]: v }))}
                onAdd={() => openPicker(row.key)}
                onClear={() => clearSlot(row.key)}
                detailHref={detailHref(row.key)}
                disabled={pcGated}
                disabledHint={pcGateHint}
              />
            ))}
          </div>
        </div>
      </section>

      {/* Produk lainnya */}
      <h2 className="text-[1rem] font-extrabold tracking-[0.04em] mt-6 mb-2">PRODUK LAINNYA</h2>
      <section className="bg-surface border border-border rounded-2xl overflow-hidden max-md:bg-transparent max-md:border-0 max-md:overflow-visible">
        <div className="overflow-x-auto max-md:overflow-visible">
          <div className="min-w-[760px] max-md:min-w-0">
            <div className="hidden md:grid grid-cols-[150px_minmax(0,1fr)_130px_110px_40px] gap-2 bg-ink text-surface px-4 py-2.5 text-[0.72rem] font-bold tracking-[0.06em]">
              <span>KOMPONEN</span>
              <span>SPESIFIKASI</span>
              <span>HARGA</span>
              <span>JUMLAH</span>
              <span />
            </div>
            {OTHER_ROWS.map((row) => (
              <SlotRow
                key={row.key}
                row={row}
                product={sel[row.key] || null}
                qty={qtyOf(row.key)}
                maxQty={maxQty(row.key)}
                onQty={(v) => setQty((q) => ({ ...q, [row.key]: v }))}
                onAdd={() => openPicker(row.key)}
                onClear={() => clearSlot(row.key)}
                detailHref={detailHref(row.key)}
              />
            ))}
          </div>
        </div>
      </section>

      {/* Aksesoris */}
      <h2 className="text-[1rem] font-extrabold tracking-[0.04em] mt-6 mb-1">AKSESORIS SETUP</h2>
      <p className="m-0 mb-2 text-[0.8rem] text-secondary">Sesuaikan komponen sesuai kebutuhan & performa impian Anda</p>
      <section className="bg-surface border border-border rounded-2xl overflow-hidden max-md:bg-transparent max-md:border-0 max-md:overflow-visible">
        <div className="overflow-x-auto max-md:overflow-visible">
          <div className="min-w-[760px] max-md:min-w-0">
            <div className="hidden md:grid grid-cols-[150px_minmax(0,1fr)_130px_110px_40px] gap-2 bg-ink text-surface px-4 py-2.5 text-[0.72rem] font-bold tracking-[0.06em]">
              <span>KOMPONEN</span>
              <span>SPESIFIKASI</span>
              <span>HARGA</span>
              <span>JUMLAH</span>
              <span />
            </div>
            {ACCESSORY_ROWS.map((row) => (
              <SlotRow
                key={row.key}
                row={row}
                product={sel[row.key] || null}
                qty={qtyOf(row.key)}
                maxQty={maxQty(row.key)}
                onQty={(v) => setQty((q) => ({ ...q, [row.key]: v }))}
                onAdd={() => openPicker(row.key)}
                onClear={() => clearSlot(row.key)}
                detailHref={detailHref(row.key)}
              />
            ))}
          </div>
        </div>
      </section>

      {/* Hasil validasi live */}
      {(displayErrors.length > 0 || displayWarnings.length > 0) && (
        <div className="flex flex-col gap-3 mt-5">
          {displayErrors.length > 0 && (
            <div className="bg-danger/10 border border-danger/30 rounded-2xl px-4 py-3" role="alert">
              <p className="m-0 mb-1.5 flex items-center gap-2 text-danger font-extrabold text-[0.85rem]">
                <TriangleAlert className="w-4 h-4" /> {displayErrors.length} masalah kompatibilitas — wajib diperbaiki
              </p>
              <ul className="m-0 pl-5 text-[0.83rem] text-danger flex flex-col gap-1">
                {displayErrors.map((m, i) => (
                  <li key={i}>{m}</li>
                ))}
              </ul>
            </div>
          )}
          {displayWarnings.length > 0 && (
            <div className="bg-warning/10 border border-warning/40 rounded-2xl px-4 py-3" role="status">
              <p className="m-0 mb-1.5 flex items-center gap-2 text-warning font-extrabold text-[0.85rem]">
                <CircleAlert className="w-4 h-4" /> {displayWarnings.length} peringatan
              </p>
              <ul className="m-0 pl-5 text-[0.83rem] text-ink flex flex-col gap-1">
                {displayWarnings.map((m, i) => (
                  <li key={i}>{m}</li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      {!complete && (
        <div className="bg-info/10 border border-info/30 rounded-2xl px-4 py-3 mt-5 text-[0.85rem] text-ink">
          Belum lengkap — slot kosong: <strong>{missingSlots.length > 0 ? missingSlots.join(', ') : REQUIRED_KEYS.filter((k) => !sel[k]).map((k) => ROW_BY_KEY[k].label).join(', ')}</strong>.
          Tombol lanjut aktif setelah 7 slot utama terisi dan kompatibel.
        </div>
      )}

      {psuIssue && recommendedWatt > 0 && (
        <label className="flex items-start gap-2 bg-subtle border border-border rounded-2xl px-4 py-3 mt-5 text-[0.83rem] cursor-pointer">
          <input type="checkbox" checked={ignorePsu} onChange={(e) => setIgnorePsu(e.target.checked)} className="mt-1" />
          <span>
            <strong>Paksa lanjut dengan PSU ini</strong> (RULE-03: kirim <code>ignore_psu_warning=true</code>).
            Disarankan minimal {recommendedWatt}W. Bila dicentang, error PSU menjadi peringatan.
          </span>
        </label>
      )}

      {/* Grand total bar */}
      <section className="sticky bottom-4 z-30 bg-ink text-surface rounded-2xl px-5 py-4 mt-6 flex items-center gap-4 flex-wrap max-md:bottom-[72px]">
        <div>
          <p className="m-0 text-[0.72rem] font-bold tracking-[0.08em] text-muted">GRAND TOTAL</p>
          <p className="m-0 text-[0.72rem] text-muted">TOTAL</p>
          <p className="m-0 font-extrabold">{formatIDR(total)}</p>
        </div>
        <div>
          <p className="m-0 text-[0.72rem] text-muted">STATUS</p>
          <p
            className={`m-0 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[0.72rem] font-extrabold ${
              canContinue ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger'
            }`}
          >
            {canContinue ? (
              <>
                <CircleCheck className="w-3.5 h-3.5" /> KOMPATIBEL
              </>
            ) : (
              <>
                <CircleAlert className="w-3.5 h-3.5" /> {complete ? 'TIDAK KOMPATIBEL' : 'BELUM LENGKAP'}
              </>
            )}
          </p>
        </div>
        <div>
          <p className="m-0 text-[0.72rem] text-muted">DAYA</p>
          <p className="m-0 font-extrabold inline-flex items-center gap-1">
            <Zap className="w-3.5 h-3.5 text-accent" /> {watt === undefined || watt === null ? '–' : `${watt}W`}
          </p>
        </div>
        <button className="inline-flex items-center gap-1.5 rounded-full border border-surface/25 bg-transparent text-surface px-3 py-1.5 text-[0.78rem] font-bold cursor-pointer hover:bg-surface/10" onClick={clearAll}>
          <RotateCcw className="w-3.5 h-3.5" /> Bersihkan Build
        </button>
        <div className="ml-auto flex items-center gap-3 max-md:ml-0 max-md:basis-full max-md:justify-between">
          <strong className="text-accent text-[1.2rem] whitespace-nowrap">{formatIDR(total)}</strong>
          {editBuildId ? (
            <button
              className={btnClasses('primary', null, 'tracking-[0.06em] max-md:flex-1 max-md:justify-center')}
              disabled={!canContinue}
              title={canContinue ? 'Simpan perubahan rakitan' : 'Perbaiki error kompatibilitas dulu'}
              onClick={saveEdit}
            >
              SIMPAN PERUBAHAN
            </button>
          ) : (
            <button
              className={btnClasses('primary', null, 'tracking-[0.06em] max-md:flex-1 max-md:justify-center')}
              disabled={!canContinue || saving}
              title={canContinue ? 'Simpan rakitan ke keranjang' : 'Lengkapi 7 slot utama dan perbaiki error kompatibilitas'}
              onClick={saveToCart}
            >
              {saving ? 'Menyimpan…' : 'LANJUT PEMBAYARAN'}
            </button>
          )}
        </div>
      </section>

      {pickerCfg && (
        <PickerModal
          slotKey={pickerCfg.key}
          initialBrand={pickerCfg.key === 'cpu' ? brandFilter : ''}
          initialSocket={pickerCfg.key === 'cpu' || pickerCfg.key === 'motherboard' ? socketFilter : ''}
          onPick={(p) => {
            setSel((s) => ({ ...s, [pickerCfg.key]: p }));
            // Pilihan CPU ikut mengisi filter kompatibilitas (bisa diubah lagi).
            if (pickerCfg.key === 'cpu') {
              if (p.brand && !brandFilter) setBrandFilter(p.brand);
              const sk = p?.specs?.socket;
              if (sk && !socketFilter) setSocketFilter(sk);
            }
            closePicker();
          }}
          onClose={closePicker}
        />
      )}

      {compatModal && (
        <CompatOptionModal
          mode={compatModal}
          brandFilter={brandFilter}
          onPick={compatModal === 'brand' ? pickBrand : pickSocket}
          onClose={() => setCompatModal(null)}
        />
      )}
    </div>
  );
}

// Fallback sisi klien saat backend tidak terjangkau (mode mock):
// total dari harga × qty + cek socket CPU vs Motherboard.
function clientFallback(sel, buildName, total) {
  const errors = [];
  const missing = REQUIRED_KEYS.filter((k) => !sel[k]).map((k) => ROW_BY_KEY[k].label);
  for (const k of REQUIRED_KEYS) {
    if (!sel[k]) errors.push(`Komponen ${ROW_BY_KEY[k].label} belum dipilih.`);
  }
  const cs = normSocket(sel.cpu?.specs?.socket);
  const ms = normSocket(sel.motherboard?.specs?.socket);
  if (cs && ms && cs !== ms) {
    errors.push(`Socket tidak cocok! CPU memakai ${sel.cpu.specs.socket}, sedangkan Motherboard memakai ${sel.motherboard.specs.socket}. (RULE-01, mode offline)`);
  }
  return {
    is_compatible: errors.length === 0,
    errors,
    error_details: [],
    warnings: ['Mode offline: validasi penuh memakai backend.'],
    warning_details: [],
    missing_slots: missing,
    build_name: (buildName || sel.cpu?.name || 'Rakitan PC').slice(0, 60),
    total_price: total,
    grand_total: total,
    power_analysis: { total_estimated_watt: null, recommended_min_psu_watt: 0 },
  };
}

export default function Builder() {
  return (
    <Shell>
      <ToastProvider>
        <BuilderInner />
      </ToastProvider>
    </Shell>
  );
}
