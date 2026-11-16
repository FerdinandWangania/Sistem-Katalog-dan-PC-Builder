import { useState } from 'react';
import { Link } from 'react-router-dom';
import './ui.css';

const LINKS = [
  { to: '/', label: 'Home' },
  { to: '/katalog', label: 'Katalog' },
  { to: '/builder', label: 'PC Builder' },
  { to: '/paket', label: 'Paket' },
  { to: '/keranjang', label: 'Keranjang' },
  { to: '/pesanan', label: 'Pesanan' },
  { to: '/login', label: 'Login' },
];

export function Navbar() {
  const [open, setOpen] = useState(false);
  return (
    <nav className="bw-navbar">
      <Link to="/" className="bw-navbar-brand" onClick={() => setOpen(false)}>
        BUILDWISE
      </Link>
      <button className="bw-navbar-toggle" onClick={() => setOpen(!open)} aria-label="Menu">
        ☰
      </button>
      <div className={`bw-navbar-links${open ? ' open' : ''}`}>
        {LINKS.map((l) => (
          <Link key={l.to} to={l.to} onClick={() => setOpen(false)}>
            {l.label}
          </Link>
        ))}
      </div>
    </nav>
  );
}

export function Footer() {
  return <footer className="bw-footer">© 2026 BuildWise PC Builder. Hak Cipta Dilindungi Undang-Undang.</footer>;
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
