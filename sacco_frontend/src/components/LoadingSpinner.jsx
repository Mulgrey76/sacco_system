export default function LoadingSpinner({ label = 'Loading...' }) {
  return (
    <div className="flex items-center gap-2 text-slate-500 dark:text-slate-400 text-sm py-6">
      <span className="w-4 h-4 border-2 border-slate-300 border-t-primary rounded-full animate-spin" />
      {label}
    </div>
  );
}
