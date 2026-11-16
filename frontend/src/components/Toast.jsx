import { createContext, useCallback, useContext, useState } from 'react';
import './ui.css';

const ToastCtx = createContext(null);
let nextId = 1;

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const push = useCallback((message, type = 'success') => {
    const id = nextId++;
    setToasts((list) => [...list, { id, message, type }]);
    setTimeout(() => {
      setToasts((list) => list.filter((t) => t.id !== id));
    }, 4000);
  }, []);

  return (
    <ToastCtx.Provider value={push}>
      {children}
      <div className="bw-toasts" aria-live="polite">
        {toasts.map((t) => (
          <div key={t.id} className={`bw-toast bw-toast-${t.type}`}>
            {t.message}
          </div>
        ))}
      </div>
    </ToastCtx.Provider>
  );
}

export function useToast() {
  const push = useContext(ToastCtx);
  if (!push) throw new Error('useToast harus dipakai di dalam <ToastProvider>');
  return {
    success: (msg) => push(msg, 'success'),
    error: (msg) => push(msg, 'error'),
  };
}
