import { Link } from 'react-router-dom';
import { ShoppingCart } from 'lucide-react';
import './ui.css';

export function Navbar() {
  return (
    <nav className="bw-navbar bw-navbar-light">
      <Link to="/" className="bw-navbar-brand">
        <span className="bw-logo-mark">✕</span> BUILDWISE
      </Link>
      <div className="bw-navbar-links">
        <Link to="/">Home</Link>
        <Link to="/katalog">Katalog</Link>
      </div>
      <div className="bw-navbar-actions">
        <Link to="/keranjang" className="bw-cart-link" aria-label="Keranjang">
          <ShoppingCart className="w-5 h-5" />
        </Link>
        <Link to="/builder" className="bw-btn bw-btn-primary bw-btn-sm bw-navbar-cta">
          PC Builder
        </Link>
        <Link to="/login" className="bw-btn bw-btn-cta bw-btn-sm bw-navbar-cta">
          Log in
        </Link>
      </div>
    </nav>
  );
}

export function Footer() {
  return (
    <footer className="bw-footer-panel">
      <div className="bw-footer-grid">
        <div>
          <div className="bw-navbar-brand">✕ BUILDWISE</div>
          <p>Platform PC Builder & e-Commerce komponen hardware terdepan. Kustomisasi PC impian Anda dengan cek kompatibilitas real-time.</p>
        </div>
        <div>
          <strong>NAVIGASI</strong>
          <Link to="/">Beranda</Link>
          <Link to="/builder">PC Builder</Link>
          <Link to="/katalog">Katalog Produk</Link>
          <Link to="/promo">Promo Spesial</Link>
        </div>
        <div>
          <strong>KATEGORI</strong>
          <Link to="/katalog?category=CPU">CPU (CPU)</Link>
          <Link to="/katalog?category=Motherboard">Motherboard</Link>
          <Link to="/katalog?category=GPU">Graphics Card (GPU)</Link>
          <Link to="/katalog?category=RAM">Memory (RAM)</Link>
          <Link to="/katalog?category=SSD">Storage (SSD)</Link>
        </div>
        <div>
          <strong>LAYANAN</strong>
          <span>Cek Garansi</span>
          <span>Panduan Rakit PC</span>
          <span>Kebijakan Retur</span>
          <span>Hubungi Konsultan</span>
        </div>
      </div>
      <div className="bw-footer-bottom">
        <span>© 2026 BuildWise PC Builder. Hak Cipta Dilindungi Undang-Undang.</span>
        <span>Syarat & Ketentuan&nbsp;&nbsp;&nbsp;Kebijakan Privasi</span>
      </div>
    </footer>
  );
}

export function Shell({ children }) {
  return (
    <>
      <Navbar />
      {children}
      <Footer />
    </>
  );
}
