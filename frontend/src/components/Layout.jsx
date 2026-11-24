import { Link } from 'react-router-dom';
import { ShoppingCart } from 'lucide-react';
import { btnClasses } from './Button.jsx';
import { MobileBottomNav } from './MobileBottomNav.jsx';
import { useCartCount } from '../lib/cart.js';

export function Navbar() {
  const cartCount = useCartCount();
  return (
    <nav className="flex items-center justify-between gap-4 px-6 py-3 bg-surface text-ink border-b border-border sticky top-0 z-50">
      <Link to="/" className="font-black tracking-[0.1em] inline-flex items-center gap-[0.4rem] no-underline font-bold">
        <span className="bg-ink text-surface rounded-md px-[0.3rem] text-[0.9em]">✕</span> BUILDWISE
      </Link>
      <div className="hidden md:flex gap-5">
        <Link to="/" className="no-underline font-semibold text-ink">
          Home
        </Link>
        <Link to="/katalog" className="no-underline font-semibold text-ink">
          Katalog
        </Link>
      </div>
      <div className="flex gap-[0.6rem] items-center">
        <Link to="/keranjang" className="relative inline-flex items-center text-ink" aria-label="Keranjang">
          <ShoppingCart className="w-5 h-5" />
          {cartCount > 0 && (
            <span className="absolute -top-2 -right-2 bg-danger text-surface text-[0.65rem] font-bold rounded-full min-w-[1.1rem] h-[1.1rem] px-1 inline-flex items-center justify-center">
              {cartCount > 99 ? '99+' : cartCount}
            </span>
          )}
        </Link>
        <span className="hidden md:flex md:items-center md:gap-[0.6rem]">
          <Link to="/builder" className={btnClasses('primary', 'sm')}>
            PC Builder
          </Link>
          <Link to="/login" className={btnClasses('cta', 'sm')}>
            Log in
          </Link>
        </span>
      </div>
    </nav>
  );
}

export function Footer() {
  return (
    <footer className="max-w-[1200px] mx-auto mt-12 mb-6 bg-ink text-surface rounded-[28px] px-10 pt-10 pb-5 max-md:hidden">
      <div className="grid gap-8 grid-cols-[1.4fr_1fr_1fr_1fr]">
        <div>
          <div className="font-black tracking-[0.1em] inline-flex items-center gap-[0.4rem] font-bold">
            ✕ BUILDWISE
          </div>
          <p className="block text-muted text-[0.85rem] mt-[0.6rem]">
            Platform PC Builder & e-Commerce komponen hardware terdepan. Kustomisasi PC impian Anda dengan cek
            kompatibilitas real-time.
          </p>
        </div>
        <div>
          <strong className="text-[0.8rem] tracking-[0.08em]">NAVIGASI</strong>
          <Link to="/" className="block text-muted text-[0.85rem] no-underline mt-[0.6rem]">
            Beranda
          </Link>
          <Link to="/builder" className="block text-muted text-[0.85rem] no-underline mt-[0.6rem]">
            PC Builder
          </Link>
          <Link to="/katalog" className="block text-muted text-[0.85rem] no-underline mt-[0.6rem]">
            Katalog Produk
          </Link>
          <Link to="/promo" className="block text-muted text-[0.85rem] no-underline mt-[0.6rem]">
            Promo Spesial
          </Link>
        </div>
        <div>
          <strong className="text-[0.8rem] tracking-[0.08em]">KATEGORI</strong>
          <Link to="/katalog?category=CPU" className="block text-muted text-[0.85rem] no-underline mt-[0.6rem]">
            CPU (CPU)
          </Link>
          <Link to="/katalog?category=Motherboard" className="block text-muted text-[0.85rem] no-underline mt-[0.6rem]">
            Motherboard
          </Link>
          <Link to="/katalog?category=GPU" className="block text-muted text-[0.85rem] no-underline mt-[0.6rem]">
            Graphics Card (GPU)
          </Link>
          <Link to="/katalog?category=RAM" className="block text-muted text-[0.85rem] no-underline mt-[0.6rem]">
            Memory (RAM)
          </Link>
          <Link to="/katalog?category=SSD" className="block text-muted text-[0.85rem] no-underline mt-[0.6rem]">
            Storage (SSD)
          </Link>
        </div>
        <div>
          <strong className="text-[0.8rem] tracking-[0.08em]">LAYANAN</strong>
          <span className="block text-muted text-[0.85rem] mt-[0.6rem]">Cek Garansi</span>
          <span className="block text-muted text-[0.85rem] mt-[0.6rem]">Panduan Rakit PC</span>
          <span className="block text-muted text-[0.85rem] mt-[0.6rem]">Kebijakan Retur</span>
          <span className="block text-muted text-[0.85rem] mt-[0.6rem]">Hubungi Konsultan</span>
        </div>
      </div>
      <div className="flex justify-between flex-wrap gap-2 border-t border-white/15 mt-6 pt-4 text-[0.75rem] text-muted">
        <span>© 2026 BuildWise PC Builder. Hak Cipta Dilindungi Undang-Undang.</span>
        <span>Syarat & Ketentuan&nbsp;&nbsp;&nbsp;Kebijakan Privasi</span>
      </div>
    </footer>
  );
}

export function Shell({ children, hideFooter = false, hideBottomNav = false }) {
  return (
    <>
      <Navbar />
      {children}
      {!hideBottomNav && <MobileBottomNav />}
      {!hideFooter && <Footer />}
    </>
  );
}
