// Data layer with mock fallback. Does NOT modify src/lib/api.js:
// it wraps api.get — real backend first, mock when unreachable.
//
// Fallback is active only when VITE_USE_MOCK=true (dev/demo without backend).
// Trigger: network failure OR HTTP >= 500 (e.g. BE down with missing ext-mongodb).

import { api } from './api.js';
import { mockProducts } from '../mocks/products.js';
import { mockPromotions } from '../mocks/promotions.js';
import { mockPackages } from '../mocks/packages.js';

const USE_MOCK = import.meta.env.VITE_USE_MOCK === 'true';

function envelope(data) {
  return { status: 'success', count: data.length, data };
}

// Handlers per resource. Each receives the same query object passed to api.get
// and returns a backend-shaped envelope. Add entries here as screens need them.
const MOCKS = {
  '/promotions': () => envelope(mockPromotions),
  '/products': (query = {}) => {
    const eff = (p) => (p.discount_price > 0 ? p.discount_price : p.price);
    const list = mockProducts.filter((p) => {
      if (query.category && p.category !== query.category) return false;
      if (query.brand && p.brand !== query.brand) return false;
      if (query.q && !p.name.toLowerCase().includes(String(query.q).toLowerCase())) return false;
      return true;
    });
    if (query.sort === 'price_asc') list.sort((a, b) => eff(a) - eff(b));
    else if (query.sort === 'price_desc') list.sort((a, b) => eff(b) - eff(a));
    return envelope(list);
  },
  '/packages': () => envelope(mockPackages),
};

function isDown(error) {
  if (!error) return true;
  if (error instanceof TypeError) return true; // network failure (fetch rejects)
  return typeof error.status === 'number' && error.status >= 500;
}

/** GET with mock fallback. Signature mirrors api.get(path, { query }). */
export async function get(path, { query } = {}) {
  try {
    return await api.get(path, { query });
  } catch (error) {
    if (USE_MOCK && MOCKS[path] && isDown(error)) {
      console.warn(`[mock] backend unreachable (${path}), using mock data.`, error?.message || error);
      return MOCKS[path](query);
    }
    throw error;
  }
}

export const mockEnabled = USE_MOCK;
