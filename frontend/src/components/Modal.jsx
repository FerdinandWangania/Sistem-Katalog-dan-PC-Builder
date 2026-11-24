import { useEffect } from 'react';
import { X } from 'lucide-react';

export function Modal({ open, title, children, footer, onClose }) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e) => {
      if (e.key === 'Escape') onClose?.();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;
  return (
    <div
      className="fixed inset-0 flex items-center justify-center p-4 z-[100] bg-[rgba(23,20,39,0.5)]"
      onClick={() => onClose?.()}
      role="dialog"
      aria-modal="true"
    >
      <div
        className="bg-surface rounded-[20px] max-w-[560px] w-full max-h-[90vh] overflow-auto p-6 max-md:p-4"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex justify-between items-center mb-4">
          <h2 className="m-0 text-[1.2rem] text-ink">{title}</h2>
          <button className="bg-transparent border-0 text-[1.4rem] cursor-pointer text-secondary" onClick={() => onClose?.()} aria-label="Tutup">
            <X className="w-5 h-5" />
          </button>
        </div>
        <div>{children}</div>
        {footer && <div className="flex justify-end gap-3 mt-6">{footer}</div>}
      </div>
    </div>
  );
}
