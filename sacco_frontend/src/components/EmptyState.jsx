export default function EmptyState({ icon: Icon, title, subtitle }) {
  return (
    <div className="bg-card dark:bg-[#0F172A] border border-light-border dark:border-white/10 rounded-2xl p-10 text-center">
      {Icon && <Icon size={28} className="mx-auto text-slate-300 mb-3" />}
      <p className="text-slate-500 dark:text-slate-400 font-medium">{title}</p>
      {subtitle && <p className="text-slate-400 dark:text-slate-500 text-sm mt-1">{subtitle}</p>}
    </div>
  );
}
