import { useEffect } from 'react';
import { X } from 'lucide-react';
import './ui.css';

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
    <div className="bw-modal-overlay" onClick={() => onClose?.()} role="dialog" aria-modal="true">
      <div className="bw-modal" onClick={(e) => e.stopPropagation()}>
        <div className="bw-modal-head">
          <h2>{title}</h2>
          <button className="bw-modal-close" onClick={() => onClose?.()} aria-label="Tutup">
            <X className="w-5 h-5" />
          </button>
        </div>
        <div>{children}</div>
        {footer && <div className="bw-modal-foot">{footer}</div>}
      </div>
    </div>
  );
}
