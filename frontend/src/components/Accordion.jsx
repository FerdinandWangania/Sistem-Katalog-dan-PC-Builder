import { useState } from 'react';
import { Minus, Plus } from 'lucide-react';
import './ui.css';

// items: [{ title, content }]. Only one open at a time.
export function Accordion({ items = [] }) {
  const [open, setOpen] = useState(0);
  return (
    <div className="bw-accordion">
      {items.map((item, i) => (
        <div className="bw-accordion-item" key={i}>
          <button className="bw-accordion-head" onClick={() => setOpen(open === i ? -1 : i)}>
            <span>{item.title}</span>
            <span>{open === i ? <Minus className="w-4 h-4" /> : <Plus className="w-4 h-4" />}</span>
          </button>
          {open === i && <div className="bw-accordion-body">{item.content}</div>}
        </div>
      ))}
    </div>
  );
}
