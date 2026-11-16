import React from 'react';
import ReactDOM from 'react-dom/client';
import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import './index.css';
import Placeholder from './routes/Placeholder.jsx';

const page = (title) => <Placeholder title={title} />;

const router = createBrowserRouter([
  { path: '/', element: page('Home') },
  { path: '/katalog', element: page('Katalog') },
  { path: '/produk/:id', element: page('Detail Produk') },
  { path: '/builder', element: page('PC Builder') },
  { path: '/keranjang', element: page('Keranjang') },
  { path: '/checkout', element: page('Checkout') },
  { path: '/login', element: page('Login') },
  { path: '/pesanan', element: page('Pesanan Saya') },
  { path: '/pesanan/:id', element: page('Detail Pesanan') },
  { path: '/paket', element: page('Paket Rakitan') },
  { path: '/paket/:id', element: page('Detail Paket') },
  { path: '/promo', element: page('Semua Promo') },
  { path: '/promo/:id', element: page('Detail Promo') },
  { path: '/login-admin', element: page('Login Admin') },
  { path: '/admin', element: page('Dashboard Admin') },
  { path: '/admin/produk', element: page('Admin Produk') },
  { path: '/admin/paket', element: page('Admin Paket') },
  { path: '/admin/pesanan', element: page('Admin Pesanan') },
  { path: '/admin/pelanggan', element: page('Admin Pelanggan') },
  { path: '/admin/promo', element: page('Admin Promo') },
  { path: '/admin/keuangan', element: page('Keuangan') },
  { path: '/admin/akun', element: page('Manajemen Akun') },
  { path: '*', element: page('404 Tidak Ditemukan') },
]);

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <RouterProvider router={router} />
  </React.StrictMode>,
);
