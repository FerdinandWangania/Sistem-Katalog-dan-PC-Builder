import { Link } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { discountPercent, effectivePrice, formatIDR } from '../lib/catalog.js';
import './ProductCard.css';

const SPEC_LABELS = {
  socket: 'Socket',
  cores: 'Cores',
  base_clock: 'Base Clock',
  form_factor: 'Form Factor',
  chipset: 'Chipset',
  type: 'Tipe',
  capacity: 'Kapasitas',
  speed: 'Speed',
  wattage_w: 'Wattage',
  tdp: 'TDP',
  length: 'Panjang',
};

const SPEC_ORDER = ['socket', 'cores', 'base_clock', 'form_factor', 'chipset', 'type', 'capacity', 'speed', 'wattage_w', 'tdp', 'length'];

function prettyLabel(key) {
  if (SPEC_LABELS[key]) return SPEC_LABELS[key];
  return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

// Up to 3 key specs as label/value rows (matches the design's spec table).
export function specRows(product, max = 3) {
  const specs = product?.specs || {};
  return SPEC_ORDER.filter((k) => specs[k] !== undefined && specs[k] !== '' && specs[k] !== null)
    .slice(0, max)
    .map((k) => ({ label: prettyLabel(k), value: String(specs[k]) }));
}

export function ProductCard({ product, onAdd }) {
  if (!product) return null;
  const pct = discountPercent(product);
  const badge = product.badge || (pct > 0 ? '★ PILIHAN UTAMA' : null);
  return (
    <div className="bw-product-card">
      <Link to={`/produk/${product._id || product.id}`} className="bw-product-media">
        {badge && <span className="bw-product-flag">{badge}</span>}
        {product.image ? (
          <img src={product.image} alt={product.name} loading="lazy" />
        ) : (
          <div className="bw-product-img-fallback">{product.category}</div>
        )}
      </Link>
      <div className="bw-product-body">
        <div className="bw-product-cat">{product.category}</div>
        <Link to={`/produk/${product._id || product.id}`} className="bw-product-name">
          {product.name}
        </Link>
        <div className="bw-product-price">
          {pct > 0 && <span className="bw-product-was">{formatIDR(product.price)}</span>}
          <strong>{formatIDR(effectivePrice(product))}</strong>
          {pct > 0 && <span className="bw-product-save">HEMAT {pct}%</span>}
        </div>
        <dl className="bw-product-specs">
          {specRows(product).map((s) => (
            <div key={s.label}>
              <dt>{s.label}</dt>
              <dd>{s.value}</dd>
            </div>
          ))}
        </dl>
        {onAdd && (
          <button className="bw-btn bw-btn-primary bw-btn-block" onClick={() => onAdd(product)}>
            <Plus className="w-4 h-4" /> TAMBAH KE KERANJANG
          </button>
        )}
      </div>
    </div>
  );
}
