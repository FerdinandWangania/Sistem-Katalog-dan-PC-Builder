import { useState } from 'react';
import { Button } from '../components/Button.jsx';
import { Badge } from '../components/Badge.jsx';
import { Modal } from '../components/Modal.jsx';
import { ToastProvider, useToast } from '../components/Toast.jsx';
import { Accordion } from '../components/Accordion.jsx';
import { DataTable } from '../components/DataTable.jsx';
import { EmptyState, ErrorState, Skeleton } from '../components/States.jsx';
import { Shell } from '../components/Layout.jsx';

const ROWS = [
  { _id: '1', name: 'Ryzen 5 7600', brand: 'AMD', price: 3200000, stock: 12 },
  { _id: '2', name: 'B650M Mortar', brand: 'MSI', price: 2800000, stock: 3 },
];

function ToastDemo() {
  const toast = useToast();
  return (
    <div className="row">
      <Button size="sm" onClick={() => toast.success('Berhasil disimpan.')}>
        Toast sukses
      </Button>
      <Button size="sm" variant="secondary" onClick={() => toast.error('Gagal menyimpan.')}>
        Toast galat
      </Button>
    </div>
  );
}

export default function UiPreview() {
  const [modal, setModal] = useState(false);
  const [page, setPage] = useState(1);
  const [page10, setPage10] = useState(7);
  return (
    <ToastProvider>
      <Shell>
        <div className="bw-preview">
          <h1>UI Preview (khusus development)</h1>

          <section>
            <h2>Button</h2>
            <div className="row">
              <Button>Primer</Button>
              <Button variant="secondary">Sekunder</Button>
              <Button variant="cta">CTA Ungu</Button>
              <Button disabled>Nonaktif</Button>
            </div>
          </section>

          <section>
            <h2>Badge status</h2>
            <div className="row">
              <Badge status="pending">pending</Badge>
              <Badge status="success">processing</Badge>
              <Badge status="failed">cancelled</Badge>
              <Badge status="info">shipped</Badge>
            </div>
          </section>

          <section>
            <h2>Modal &amp; Toast</h2>
            <div className="row">
              <Button variant="secondary" onClick={() => setModal(true)}>
                Buka modal
              </Button>
            </div>
            <ToastDemo />
            <Modal open={modal} title="Contoh Modal" onClose={() => setModal(false)}>
              <p>Isi modal. Klik overlay, tombol ×, atau Escape untuk menutup.</p>
            </Modal>
          </section>

          <section>
            <h2>Accordion</h2>
            <Accordion
              items={[
                { title: 'Rakitan #1 — 7 komponen', content: 'Isi rakitan tampil di sini.' },
                { title: 'Rakitan #2 — 7 komponen', content: 'Isi rakitan tampil di sini.' },
              ]}
            />
          </section>

          <section>
            <h2>Table + Pagination</h2>
            <DataTable
              columns={[
                { key: 'name', label: 'Nama' },
                { key: 'brand', label: 'Merek' },
                { key: 'price', label: 'Harga' },
                { key: 'stock', label: 'Stok' },
              ]}
              rows={ROWS}
              pagination={{ page, pageSize: 2, total: 10, onPage: setPage }}
            />
            <h3>10 halaman (posisi tengah)</h3>
            <DataTable
              columns={[
                { key: 'name', label: 'Nama' },
                { key: 'brand', label: 'Merek' },
                { key: 'price', label: 'Harga' },
                { key: 'stock', label: 'Stok' },
              ]}
              rows={ROWS}
              pagination={{ page: page10, pageSize: 2, total: 20, onPage: setPage10 }}
            />
          </section>

          <section>
            <h2>States</h2>
            <EmptyState title="Keranjang kosong" />
            <ErrorState message="Gagal memuat." onRetry={() => {}} />
            <Skeleton />
            <br />
            <Skeleton width="60%" />
          </section>
        </div>
      </Shell>
    </ToastProvider>
  );
}
