import { Link, useLocation } from 'react-router-dom';
import { Cpu, House, LayoutGrid, User } from 'lucide-react';

// Mobile-only bottom nav (mobile-only via `hidden max-md:flex`).
// Dirender di Shell agar semua halaman memilikinya tanpa duplikasi.
export function MobileBottomNav() {
  const { pathname } = useLocation();
  // Semua menu navbar pindah ke bawah, kecuali keranjang (tetap di atas).
  const items = [
    { to: '/', label: 'Home', icon: House },
    { to: '/katalog', label: 'Katalog', icon: LayoutGrid },
    { to: '/builder', label: 'Builder', icon: Cpu },
    { to: '/login', label: 'Account', icon: User },
  ];
  return (
    <nav className="hidden max-md:flex fixed left-0 right-0 bottom-0 z-[60] bg-surface border-t border-border px-4 pt-[0.6rem] pb-[calc(0.6rem+env(safe-area-inset-bottom))]">
      {items.map((it) => (
        <Link
          key={it.to}
          to={it.to}
          className={`flex-1 flex flex-col items-center gap-[0.15rem] no-underline text-[0.7rem] font-semibold ${
            pathname === it.to ? 'text-primary' : 'text-muted'
          }`}
        >
          <it.icon className="w-5 h-5" />
          {it.label}
        </Link>
      ))}
    </nav>
  );
}
