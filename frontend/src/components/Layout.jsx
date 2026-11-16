import { useState } from 'react';
import { Link } from 'react-router-dom';
import './ui.css';

export function Navbar() {
  const [open, setOpen] = useState(false);
  return (
    <nav className="bw-navbar bw-navbar-light">
      <Link to="/" className="bw-navbar-brand" onClick={() => setOpen(false)}>
        <span className="bw-logo-mark">✕</span> BUILDWISE
      </Link>
      <button className="bw-navbar-toggle" onClick={() => setOpen(!open)} aria-label="Menu">
        ☰
      </button>
      <div className={`bw-navbar-links${open ? ' open' : ''}`}>
        <Link to="/" onClick={() => setOpen(false)}>
          Home
        </Link>
        <Link to="/katalog" onClick={() => setOpen(false)}>
          Katalog
        </Link>
      </div>
      <div className={`bw-navbar-actions${open ? ' open' : ''}`}>
        <Link to="/keranjang" className="bw-cart-link" aria-label="Keranjang" onClick={() => setOpen(false)}>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="9" cy="21" r="1" />
            <circle cx="20" cy="21" r="1" />
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
          </svg>
        </Link>
        <Link to="/builder" className="bw-btn bw-btn-primary bw-btn-sm" onClick={() => setOpen(false)}>
          PC Builder
        </Link>
        <Link to="/login" className="bw-btn bw-btn-cta bw-btn-sm" onClick={() => setOpen(false)}>
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
