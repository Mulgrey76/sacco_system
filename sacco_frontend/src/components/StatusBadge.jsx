const PALETTES = {
  success: 'bg-green-100 text-success',
  warning: 'bg-amber-100 text-warning',
  danger: 'bg-red-100 text-danger',
  info: 'bg-blue-100 text-primary',
  neutral: 'bg-slate-100 text-slate-600',
};

export default function StatusBadge({ children, tone = 'neutral' }) {
  return (
    <span className={`px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap ${PALETTES[tone] ?? PALETTES.neutral}`}>
      {children}
    </span>
  );
}
