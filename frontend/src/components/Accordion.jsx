import { useState } from 'react';
import { Minus, Plus } from 'lucide-react';

// items: [{ title, content }]. Only one open at a time.
export function Accordion({ items = [] }) {
  const [open, setOpen] = useState(0);
  return (
    <div className="border border-border rounded-2xl overflow-hidden bg-surface">
      {items.map((item, i) => (
        <div key={i} className={i > 0 ? 'border-t border-border' : ''}>
          <button
            className="w-full flex justify-between items-center px-5 py-4 bg-transparent border-0 text-[1rem] font-bold text-ink cursor-pointer text-left"
            onClick={() => setOpen(open === i ? -1 : i)}
          >
            <span>{item.title}</span>
            <span>{open === i ? <Minus className="w-4 h-4" /> : <Plus className="w-4 h-4" />}</span>
          </button>
          {open === i && <div className="px-5 pb-5 text-secondary">{item.content}</div>}
        </div>
      ))}
    </div>
  );
}
