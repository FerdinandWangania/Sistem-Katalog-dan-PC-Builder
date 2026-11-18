import { Link } from 'react-router-dom';
import { Heart, Plus } from 'lucide-react';
import { discountPercent, effectivePrice, formatIDR } from '../lib/catalog.js';
import { btnClasses } from './Button.jsx';

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

export function ProductCard({ product, onAdd, wishlisted, onWish, minimal = false }) {
  if (!product) return null;
  const pct = discountPercent(product);
  return (
    <div className="relative flex flex-col bg-surface border border-border rounded-[20px] overflow-hidden text-ink">
      {onWish && !minimal && (
        <button
          className={`absolute top-2 right-2 z-[1] bg-surface border rounded-full w-[1.9rem] h-[1.9rem] inline-flex items-center justify-center cursor-pointer ${
            wishlisted ? 'text-danger border-danger [&>svg]:fill-current' : 'text-muted border-border'
          }`}
          onClick={() => onWish(product._id || product.id)}
          aria-label="Wishlist"
        >
          <Heart className="w-4 h-4" />
        </button>
      )}
      <Link to={`/produk/${product._id || product.id}`} className="relative block">
        {pct > 0 && (
          <span className="absolute top-0 left-0 bg-danger text-surface text-[0.8rem] font-extrabold px-[0.7rem] py-1 rounded-br-xl z-[1]">
            {pct}%
          </span>
        )}
        {product.image ? (
          <img src={product.image} alt={product.name} loading="lazy" className="w-full aspect-[4/3] object-cover block bg-subtle" />
        ) : (
          <div className="w-full aspect-[4/3] object-cover bg-subtle flex items-center justify-center text-muted font-bold">
            {product.category}
          </div>
        )}
      </Link>
      <div className="px-4 pt-[0.9rem] pb-[1.1rem] flex flex-col gap-[0.3rem] flex-1 max-md:px-[0.8rem] max-md:pt-[0.7rem] max-md:pb-[0.9rem]">
        <div className="text-[0.72rem] font-extrabold text-primary uppercase tracking-[0.05em]">{product.category}</div>
        <Link to={`/produk/${product._id || product.id}`} className="font-bold no-underline text-ink max-md:text-[0.85rem]">
          {product.name}
        </Link>
        <div className="flex gap-2 items-baseline flex-wrap">
          {pct > 0 && <span className="line-through text-muted text-[0.85rem]">{formatIDR(product.price)}</span>}
          <strong>{formatIDR(effectivePrice(product))}</strong>
        </div>
        {!minimal && (
          <dl className="my-[0.4rem] text-[0.78rem] max-md:hidden">
            {specRows(product).map((s) => (
              <div key={s.label} className="flex justify-between gap-2 py-[0.1rem]">
                <dt className="text-muted">{s.label}</dt>
                <dd className="m-0 font-semibold text-right">{s.value}</dd>
              </div>
            ))}
          </dl>
        )}
        {onAdd && !minimal && (
          <button className={btnClasses('primary', null, 'w-full mt-auto text-[0.8rem] px-4 py-[0.6rem]')} onClick={() => onAdd(product)}>
            <Plus className="w-4 h-4" /> TAMBAH KE KERANJANG
          </button>
        )}
      </div>
    </div>
  );
}
