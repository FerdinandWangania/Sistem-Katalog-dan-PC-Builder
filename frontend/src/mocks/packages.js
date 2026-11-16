// Mock packages — same shape as GET /api/packages
// (name, tag, total_price, items[] with embedded product).
import { mockProducts } from './products.js';

const byId = (id) => mockProducts.find((p) => p._id === id);

function pkg(id, name, tag, itemIds) {
  const items = itemIds.map((pid) => ({ product_id: pid, product: byId(pid) }));
  const total = items.reduce((sum, it) => {
    const p = it.product || {};
    return sum + (p.discount_price > 0 ? p.discount_price : p.price || 0);
  }, 0);
  return { _id: id, name, tag, total_price: total, items };
}

export const mockPackages = [
  pkg('mock-pack-1', 'PC Rakitan Flagship', 'gaming', ['mock-cpu-3', 'mock-mobo-3', 'mock-ram-1']),
  pkg('mock-pack-2', 'PC Rakitan 1440p Gaming', 'gaming', ['mock-cpu-2', 'mock-mobo-2', 'mock-ram-1']),
  pkg('mock-pack-3', 'PC Rakitan Creator Workstation', 'workstation', ['mock-cpu-1', 'mock-mobo-1', 'mock-ram-1']),
];
