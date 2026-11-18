import { createContext, useCallback, useContext, useState } from 'react';

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
      <div className="fixed bottom-4 right-4 flex flex-col gap-2 z-[200] max-md:left-4" aria-live="polite">
        {toasts.map((t) => (
          <div
            key={t.id}
            className={`bg-ink text-surface px-[1.1rem] py-3 rounded-xl text-[0.9rem] shadow-[0_4px_16px_rgba(0,0,0,0.2)] border-l-4 ${
              t.type === 'error' ? 'border-danger' : 'border-success'
            }`}
          >
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
