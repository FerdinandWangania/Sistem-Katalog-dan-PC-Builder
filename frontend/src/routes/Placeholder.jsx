import { useParams } from 'react-router-dom';

// Temporary route skeleton for FE-01. Real screens arrive in later tasks.
export default function Placeholder({ title }) {
  const params = useParams();
  return (
    <main style={{ padding: '2rem', fontFamily: 'system-ui, sans-serif' }}>
      <h1>{title}</h1>
      <p>
        Rute kerangka FE-01 — layar asli menyusul di task berikutnya.
        {Object.keys(params).length > 0 && (
          <>
            {' '}
            Param: <code>{JSON.stringify(params)}</code>
          </>
        )}
      </p>
    </main>
  );
}
