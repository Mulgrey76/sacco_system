export default function ChartCard({ title, children, empty }) {
  return (
    <div className="bg-card border border-light-border rounded-2xl p-5 shadow-sm">
      <h3 className="text-sm font-semibold text-secondary mb-4">{title}</h3>
      {empty ? (
        <div className="h-64 flex items-center justify-center text-sm text-slate-400">
          Not enough data yet.
        </div>
      ) : (
        <div className="h-64">{children}</div>
      )}
    </div>
  );
}
