// Static catalog metadata (PRD §8.2: 20 slugs). Used until GET /categories exists (blocked).
export const MAIN_CATEGORIES = [
  { slug: 'CPU', label: 'Processor' },
  { slug: 'Motherboard', label: 'Motherboard' },
  { slug: 'RAM', label: 'Memory' },
  { slug: 'SSD', label: 'SSD' },
  { slug: 'GPU', label: 'Graphics Card' },
  { slug: 'Case', label: 'Case' },
  { slug: 'PSU', label: 'Power Supply' },
];

export const EXTRA_CATEGORIES = [
  'Harddisk',
  'Case Fan',
  'Thermal Paste',
  'Operating System',
  'Sound Card',
  'Network Card',
  'Capture Card',
  'Mouse',
  'Mousepad',
  'Keyboard',
  'Monitor',
];

export function effectivePrice(p) {
  if (!p) return 0;
  return p.discount_price > 0 ? p.discount_price : p.price;
}

// "Unggulan" order (discount_desc) is computed client-side:
// the backend only sorts price_asc/price_desc (ProductController.php:59-63).
export function discountPercent(p) {
  if (!p?.price || !(p.discount_price > 0)) return 0;
  return Math.round(((p.price - p.discount_price) / p.price) * 100);
}

export function formatIDR(n) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(n || 0);
}

// Resolve a promotion hero click to an in-app route or external URL.
// Backend P2 will add banner_image/click_target_*; until then every field is optional.
export function promoLink(promo) {
  if (!promo) return '/promo';
  const type = promo.click_target_type;
  const value = promo.click_target_value;
  switch (type) {
    case 'brand':
      return `/katalog?brand=${encodeURIComponent(value)}`;
    case 'category':
      return `/katalog?category=${encodeURIComponent(value)}`;
    case 'product':
      return `/produk/${value}`;
    case 'package':
      return `/paket/${value}`;
    case 'promo':
      return `/promo/${value}`;
    case 'url':
      return value || '/promo';
    default:
      return '/promo';
  }
}

export function isExternal(href) {
  return /^https?:\/\//i.test(href || '');
}
