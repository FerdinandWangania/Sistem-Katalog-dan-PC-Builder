import './ui.css';

export function Button({ variant = 'primary', size, children, ...props }) {
  return (
    <button className={`bw-btn bw-btn-${variant}${size === 'sm' ? ' bw-btn-sm' : ''}`} {...props}>
      {children}
    </button>
  );
}
