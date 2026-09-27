import { useEffect, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import AppLayout from '../components/AppLayout';

function formatCurrency(value) {
  return `KES ${Number(value ?? 0).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
}

export default function DividendsPage() {
  const { user } = useAuth();
  const [declarations, setDeclarations] = useState([]);
  const [expanded, setExpanded] = useState(null);
  const [payouts, setPayouts] = useState([]);
  const [periodLabel, setPeriodLabel] = useState('');
  const [ratePct, setRatePct] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const ORG_ID = 1;

  async function loadDeclarations() {
    const res = await api.get(`/dividends?org_id=${ORG_ID}`);
    setDeclarations(res.data);
  }

  useEffect(() => {
    let isMounted = true;
    loadDeclarations().finally(() => { if (isMounted) setLoading(false); });
    return () => { isMounted = false; };
  }, []);

  async function handleDeclare(event) {
    event.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      await api.post('/dividends/declare', {
        org_id: ORG_ID,
        period_label: periodLabel,
        rate_pct: Number(ratePct),
        declared_by: user.id,
      });
      setPeriodLabel('');
      setRatePct('');
      await loadDeclarations();
    } catch (err) {
      setError(err.response?.data?.message || 'Could not declare dividends.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleViewPayouts(id) {
    if (expanded === id) {
      setExpanded(null);
      return;
    }
    const res = await api.get(`/dividends/${id}`);
    setPayouts(res.data.payouts);
    setExpanded(id);
  }

  async function handlePay(id) {
    setError('');
    try {
      await api.post(`/dividends/${id}/pay`);
      await loadDeclarations();
      if (expanded === id) await handleViewPayouts(id);
    } catch (err) {
      setError(err.response?.data?.message || 'Could not process payout.');
    }
  }

  return (
    <AppLayout title="Dividends">
      <h1 className="text-2xl font-bold text-slate-900 mb-6">Dividends</h1>

      <form onSubmit={handleDeclare} className="bg-white border border-slate-200 rounded-xl p-6 mb-6 flex items-end gap-4">
        <div>
          <label className="block text-xs font-semibold text-slate-600 mb-1">Period Label</label>
          <input
            type="text" value={periodLabel} onChange={(e) => setPeriodLabel(e.target.value)}
            placeholder="e.g. 2026-Q4" required
            className="px-3 py-2 border border-slate-300 rounded-lg text-sm"
          />
        </div>
        <div>
          <label className="block text-xs font-semibold text-slate-600 mb-1">Rate (%)</label>
          <input
            type="number" step="0.001" value={ratePct} onChange={(e) => setRatePct(e.target.value)}
            placeholder="10" required
            className="px-3 py-2 border border-slate-300 rounded-lg text-sm w-28"
          />
        </div>
        <button
          type="submit" disabled={submitting}
          className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg"
        >
          {submitting ? 'Declaring...' : 'Declare Dividend'}
        </button>
      </form>

      {error && (
        <div className="bg-red-50 text-red-700 px-4 py-3 rounded-lg mb-4">{error}</div>
      )}

      {loading && <p className="text-slate-500">Loading...</p>}

      {!loading && declarations.length === 0 && (
        <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-500">
          No dividend declarations yet.
        </div>
      )}

      {!loading && declarations.map((d) => (
        <div key={d.id} className="bg-white border border-slate-200 rounded-xl p-4 mb-3">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-semibold text-slate-900">{d.period_label} — {d.rate_pct}%</p>
              <p className="text-xs text-slate-400 capitalize">{d.status}</p>
            </div>
            <div className="flex gap-2">
              <button
                onClick={() => handleViewPayouts(d.id)}
                className="text-sm text-blue-600 hover:underline"
              >
                {expanded === d.id ? 'Hide' : 'View'} Payouts
              </button>
              {d.status === 'approved' && (
                <button
                  onClick={() => handlePay(d.id)}
                  className="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-3 py-1.5 rounded-lg"
                >
                  Pay Out
                </button>
              )}
            </div>
          </div>

          {expanded === d.id && (
            <table className="w-full text-sm mt-4 border-t border-slate-100 pt-3">
              <thead className="text-slate-500 text-left">
                <tr><th>Client</th><th>Basis</th><th>Dividend</th><th>Status</th></tr>
              </thead>
              <tbody>
                {payouts.map((p) => (
                  <tr key={p.id}>
                    <td className="py-1">{p.client?.first_name} {p.client?.last_name}</td>
                    <td className="py-1">{formatCurrency(p.basis_amount)}</td>
                    <td className="py-1 font-semibold">{formatCurrency(p.dividend_amount)}</td>
                    <td className="py-1 capitalize">{p.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      ))}
    </AppLayout>
  );
}
