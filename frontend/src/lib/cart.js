import { useEffect, useState } from 'react';
import { ApiError, api, ownerParams } from './api.js';
import { shouldMock } from './data.js';
import { mockProducts } from '../mocks/products.js';

// Cart store: backend real dulu, fallback mock localStorage bila
// VITE_USE_MOCK=true dan backend tidak terjangkau. Setiap perubahan
// (sukses real maupun mock) memicu event agar badge ter-update.

const CART_EVENT = 'bw-cart-changed';
const MOCK_CART_KEY = 'bw_cart_mock';

function notifyCartChanged() {
  // Diekspor untuk orders.js (checkout mock juga mengosongkan keranjang)
  // dan Builder.jsx (FE-08: simpan rakitan lalu ke keranjang).
  window.dispatchEvent(new CustomEvent(CART_EVENT));
}

export { notifyCartChanged };

/** Tulis entri mentah ke mock cart (dipakai simpan rakitan saat offline). */
export function appendMockCart(entries) {
  const items = readMockCart();
  items.push(...entries.filter(Boolean));
  writeMockCart(items);
  notifyCartChanged();
}

/** Ganti utuh satu rakitan mock (dipakai edit rakitan saat offline). */
export function replaceMockBuild(buildId, entries) {
  const items = readMockCart().filter((it) => !(it.source_type === 'builder' && String(it.build_id) === String(buildId)));
  items.push(...entries.filter(Boolean));
  writeMockCart(items);
  notifyCartChanged();
}

function readMockCart() {
  try {
    const raw = JSON.parse(localStorage.getItem(MOCK_CART_KEY) || '[]');
    return Array.isArray(raw) ? raw : [];
  } catch {
    return [];
  }
}

function writeMockCart(items) {
  try {
    localStorage.setItem(MOCK_CART_KEY, JSON.stringify(items));
  } catch {
    // abaikan bila storage tidak tersedia
  }
}

function mockCount() {
  return readMockCart().reduce((sum, it) => sum + (Number(it.qty) || 0), 0);
}

async function refreshCount() {
  try {
    const res = await api.get('/cart', { query: ownerParams() });
    const n = Number(res?.data?.item_count);
    return Number.isFinite(n) ? n : 0;
  } catch (e) {
    if (shouldMock(e)) return mockCount();
    return 0;
  }
}

/** Jumlah item (sum qty) di keranjang aktif. Ter-update otomatis tiap ada perubahan. */
export function useCartCount() {
  const [count, setCount] = useState(0);
  useEffect(() => {
    let alive = true;
    refreshCount().then((c) => {
      if (alive) setCount(c);
    });
    const onChange = () => {
      refreshCount().then((c) => {
        if (alive) setCount(c);
      });
    };
    window.addEventListener(CART_EVENT, onChange);
    return () => {
      alive = false;
      window.removeEventListener(CART_EVENT, onChange);
    };
  }, []);
  return count;
}

/**
 * POST /cart/items (body: product_id, qty, source_type + owner identity).
 * Sukses -> badge cart ter-update via event. Gagal 422/404 diteruskan
 * (stok tidak cukup / produk tidak ada). Mock fallback meniru aturan
 * stok backend agar demo tanpa backend tetap konsisten.
 */
export async function addToCart({ product_id, qty = 1, source_type = 'catalog' }) {
  const body = { ...ownerParams(), product_id, qty, source_type };
  try {
    const res = await api.post('/cart/items', { body });
    notifyCartChanged();
    return res;
  } catch (e) {
    if (!shouldMock(e)) throw e;
    const product = mockProducts.find((p) => (p._id || p.id) === product_id);
    if (!product) throw new ApiError(404, { message: 'Produk tidak ditemukan' });
    const items = readMockCart();
    const existing = items.find((it) => it.product_id === product_id && it.source_type === source_type);
    const existingQty = existing ? Number(existing.qty) || 0 : 0;
    if (product.stock < existingQty + qty) {
      throw new ApiError(422, { message: `Stok ${product.name} tidak mencukupi (tersedia: ${product.stock}).` });
    }
    if (existing) existing.qty = existingQty + qty;
    else items.push({ product_id, qty, source_type });
    writeMockCart(items);
    notifyCartChanged();
    return { status: 'success', message: `${product.name} masuk keranjang.`, data: { item_count: mockCount() } };
  }
}

// ---------- Halaman keranjang (FE-07) ----------

function effectivePriceOf(p) {
  return p && p.discount_price > 0 ? p.discount_price : p?.price || 0;
}

function mockCartItem(entry, idx) {
  const product = mockProducts.find((p) => (p._id || p.id) === entry.product_id);
  if (!product) return null;
  return {
    _id: `mock-item-${idx}`,
    product_id: entry.product_id,
    qty: entry.qty,
    source_type: entry.source_type || 'catalog',
    build_id: entry.build_id || null,
    build_name: entry.build_name || null,
    price_snapshot: effectivePriceOf(product),
    product,
  };
}

function groupMockBuilds(items) {
  const byId = new Map();
  for (const it of items) {
    if (!byId.has(it.build_id)) byId.set(it.build_id, []);
    byId.get(it.build_id).push(it);
  }
  return [...byId.entries()].map(([buildId, list]) => ({
    build_id: buildId,
    build_name: list[0]?.build_name || 'Rakitan PC',
    total_price: list.reduce((s, i) => s + i.price_snapshot * i.qty, 0),
    components: list,
  }));
}

function mockCartEnvelope() {
  const items = readMockCart().map(mockCartItem).filter(Boolean);
  const catalog = items.filter((i) => i.source_type === 'catalog');
  const builder = groupMockBuilds(items.filter((i) => i.source_type === 'builder'));
  const packs = groupMockBuilds(items.filter((i) => i.source_type === 'package'));
  return {
    status: 'success',
    data: {
      cart_id: 'mock-cart',
      total_price: items.reduce((s, i) => s + i.price_snapshot * i.qty, 0),
      item_count: items.reduce((s, i) => s + (Number(i.qty) || 0), 0),
      catalog_items: catalog,
      builder_builds: builder,
      package_builds: packs,
    },
  };
}

/** GET /cart — bentuk backend: { catalog_items, builder_builds, package_builds }. */
export async function getCart() {
  try {
    return await api.get('/cart', { query: ownerParams() });
  } catch (e) {
    if (!shouldMock(e)) throw e;
    console.warn('[mock] backend unreachable (/cart), using mock data.');
    return mockCartEnvelope();
  }
}

/** DELETE /cart/items/{id} — terima objek item (mock dicocokkan via produk). */
export async function removeCartItem(item) {
  try {
    await api.delete(`/cart/items/${item._id}`, { query: ownerParams() });
  } catch (e) {
    if (!shouldMock(e)) throw e;
    writeMockCart(
      readMockCart().filter(
        (it) => !(it.product_id === item.product_id && (it.source_type || 'catalog') === item.source_type && (it.build_id || null) === (item.build_id || null)),
      ),
    );
  }
  notifyCartChanged();
}

/**
 * Ubah qty item katalog: tambah via POST, kurang via hapus + tulis ulang.
 * (Backend tidak punya endpoint update qty; edit satuan rakitan dilarang.)
 */
export async function setCartItemQty(item, newQty) {
  const target = Math.max(1, Number(newQty) || 1);
  if (target === Number(item.qty)) return;
  const product = item.product || mockProducts.find((p) => (p._id || p.id) === item.product_id);
  const stock = Number(product?.stock);
  if (Number.isFinite(stock) && target > stock) {
    throw new ApiError(422, { message: `Stok ${product?.name || 'produk'} tidak mencukupi (tersedia: ${stock}).` });
  }
  try {
    if (target > item.qty) {
      await api.post('/cart/items', { body: { ...ownerParams(), product_id: item.product_id, qty: target - item.qty, source_type: 'catalog' } });
    } else {
      await api.delete(`/cart/items/${item._id}`, { query: ownerParams() });
      await api.post('/cart/items', { body: { ...ownerParams(), product_id: item.product_id, qty: target, source_type: 'catalog' } });
    }
  } catch (e) {
    if (!shouldMock(e)) throw e;
    const items = readMockCart();
    const found = items.find(
      (it) => it.product_id === item.product_id && (it.source_type || 'catalog') === item.source_type,
    );
    if (found) found.qty = target;
    writeMockCart(items);
  }
  notifyCartChanged();
}
