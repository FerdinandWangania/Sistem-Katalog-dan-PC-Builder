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
  window.dispatchEvent(new CustomEvent(CART_EVENT));
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
