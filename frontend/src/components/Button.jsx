const VARIANTS = {
  primary: 'bg-accent text-ink border-transparent hover:brightness-95',
  secondary: 'bg-surface text-ink border-border hover:bg-subtle',
  cta: 'bg-primary text-surface border-transparent hover:brightness-[0.92]',
};

export function btnClasses(variant = 'primary', size, extra = '') {
  return [
    'inline-flex items-center justify-center gap-2 rounded-full border font-bold cursor-pointer no-underline transition active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed',
    size === 'sm' ? 'px-[0.9rem] py-[0.4rem] text-[0.85rem]' : 'px-[1.4rem] py-[0.65rem] text-[0.95rem]',
    VARIANTS[variant] || VARIANTS.primary,
    extra,
  ].join(' ');
}

export function Button({ variant = 'primary', size, children, ...props }) {
  return (
    <button className={btnClasses(variant, size)} {...props}>
      {children}
    </button>
  );
}
