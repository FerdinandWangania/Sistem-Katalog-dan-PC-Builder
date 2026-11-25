import { api } from './api.js';
import { getAuth, getSessionId } from './session.js';
import { notifyCartChanged } from './cart.js';
import { shouldMock } from './data.js';
import { mockProducts } from '../mocks/products.js';

// Identitas checkout: kirim user_id DAN session_id agar backend
// menggabung keranjang guest ke user (OrderController@checkout).
export function checkoutIdentity() {
  const auth = getAuth();
  const q = { session_id: getSessionId() };
  if (auth?.user_id) q.user_id = auth.user_id;
  return q;
}

function orderQuery() {
  return checkoutIdentity();
}

// ---------- Mock fallback (VITE_USE_MOCK + backend tak terjangkau) ----------

const ORDERS_KEY = 'bw_orders_mock';
const CART_KEY = 'bw_cart_mock';

function readOrders() {
  try {
    const raw = JSON.parse(localStorage.getItem(ORDERS_KEY) || '[]');
    return Array.isArray(raw) ? raw : [];
  } catch {
    return [];
  }
}

function writeOrders(list) {
  try {
    localStorage.setItem(ORDERS_KEY, JSON.stringify(list));
  } catch {
    // abaikan
  }
}

function effectivePriceOf(p) {
  return p && p.discount_price > 0 ? p.discount_price : p?.price || 0;
}

function mockOrderEnvelope(order) {
  return { status: 'success', data: order };
}

function mockCheckout({ shipping_address, shipping_fee, gateway_provider }) {
  let cartItems = [];
  try {
    cartItems = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
  } catch {
    cartItems = [];
  }
  if (cartItems.length === 0) {
    throw Object.assign(new Error('Keranjang belanja kosong'), { status: 400, payload: { message: 'Keranjang belanja kosong' } });
  }
  const items = cartItems.map((it, idx) => {
    const product = mockProducts.find((p) => (p._id || p.id) === it.product_id) || null;
    const price = product ? effectivePriceOf(product) : 0;
    return {
      _id: `mock-oi-${Date.now()}-${idx}`,
      product_id: it.product_id,
      source_type: it.source_type || 'catalog',
      build_id: it.build_id || null,
      build_name: it.build_name || null,
      qty: it.qty || 1,
      price_snapshot: price,
      product,
    };
  });
  const subtotal = items.reduce((s, i) => s + i.price_snapshot * i.qty, 0);
  const fee = Number(shipping_fee) || 0;
  const now = new Date().toISOString();
  const n = readOrders().length + 1;
  const order = {
    _id: `mock-order-${Date.now()}`,
    order_number: `ORD-${now.slice(0, 10).replace(/-/g, '')}-${String(n).padStart(4, '0')}`,
    subtotal,
    shipping_fee: fee,
    total_amount: subtotal + fee,
    status: 'pending',
    shipping_address,
    session_id: getSessionId(),
    user_id: getAuth()?.user_id || null,
    created_at: now,
    items,
    payments: [
      {
        _id: `mock-pay-${Date.now()}`,
        gateway_provider: gateway_provider || 'midtrans',
        gateway_transaction_id: `TRX-${Date.now().toString(36).toUpperCase()}`,
        status: 'pending',
        amount: subtotal + fee,
        paid_at: null,
      },
    ],
  };
  writeOrders([order, ...readOrders()]);
  try {
    localStorage.setItem(CART_KEY, '[]');
  } catch {
    // abaikan
  }
  notifyCartChanged();
  return { status: 'success', message: 'Checkout berhasil! Pesanan dibuat.', data: { order, payment: order.payments[0] } };
}

/** GET /orders — list milik sendiri. */
export async function listOrders() {
  try {
    return await api.get('/orders', { query: orderQuery() });
  } catch (e) {
    if (!shouldMock(e)) throw e;
    console.warn('[mock] backend unreachable (/orders), using mock data.');
    return { status: 'success', count: readOrders().length, data: readOrders() };
  }
}

/** GET /orders/{id} — detail + items.product + payments. */
export async function getOrder(id) {
  try {
    return await api.get(`/orders/${id}`, { query: orderQuery() });
  } catch (e) {
    if (!shouldMock(e)) throw e;
    const found = readOrders().find((o) => o._id === id || o.order_number === id);
    if (!found) throw Object.assign(new Error('Pesanan tidak ditemukan'), { status: 404 });
    return mockOrderEnvelope(found);
  }
}

/**
 * POST /orders/checkout — buat pesanan (tanpa order ganda: backend
 * memvalidasi dulu; 422 per build_id ditampilkan tanpa navigasi).
 */
export async function checkoutOrder({ shipping_address, shipping_fee, gateway_provider }) {
  const body = { ...checkoutIdentity(), shipping_address, shipping_fee, gateway_provider };
  try {
    const res = await api.post('/orders/checkout', { body });
    notifyCartChanged();
    return res;
  } catch (e) {
    if (!shouldMock(e)) throw e;
    return mockCheckout({ shipping_address, shipping_fee, gateway_provider });
  }
}

/** POST /payments/{orderId}/pay — simulasi bayar (order yang sama untuk Bayar Ulang). */
export async function payOrder(orderId) {
  try {
    return await api.post(`/payments/${orderId}/pay`, { query: orderQuery() });
  } catch (e) {
    if (!shouldMock(e)) throw e;
    const list = readOrders();
    const order = list.find((o) => o._id === orderId);
    if (!order) throw Object.assign(new Error('Pesanan tidak ditemukan'), { status: 404 });
    const payment = order.payments?.[order.payments.length - 1];
    if (payment && payment.status !== 'paid') {
      payment.status = 'paid';
      payment.paid_at = new Date().toISOString();
      order.status = 'processing';
      writeOrders(list);
    }
    return { status: 'success', message: 'Pembayaran berhasil dikonfirmasi!', data: { order, payment } };
  }
}

/** POST /orders/{id}/cancel — batalkan pesanan pending (stok kembali). */
export async function cancelOrder(orderId) {
  try {
    return await api.post(`/orders/${orderId}/cancel`, { query: orderQuery() });
  } catch (e) {
    if (!shouldMock(e)) throw e;
    const list = readOrders();
    const order = list.find((o) => o._id === orderId);
    if (!order) throw Object.assign(new Error('Pesanan tidak ditemukan'), { status: 404 });
    if (order.status !== 'pending') {
      throw Object.assign(new Error(`Pesanan ${order.status} tidak bisa dibatalkan sendiri`), { status: 422 });
    }
    order.status = 'cancelled';
    writeOrders(list);
    return { status: 'success', message: 'Pesanan dibatalkan.', data: order };
  }
}
