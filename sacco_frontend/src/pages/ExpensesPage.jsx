import { useEffect, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import AppLayout from '../components/AppLayout';

function formatCurrency(value) {
  return `KES ${Number(value ?? 0).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
}

const STATUS_STYLES = {
  pending: 'bg-yellow-100 text-yellow-800',
  authorized: 'bg-green-100 text-green-800',
  paid: 'bg-blue-100 text-blue-800',
  rejected: 'bg-red-100 text-red-700',
};

export default function ExpensesPage() {
  const { user } = useAuth();
  const [expenses, setExpenses] = useState([]);
  const [description, setDescription] = useState('');
  const [amount, setAmount] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const ORG_ID = 1;
  const BRANCH_ID = 1; // only one branch exists in our test data so far

  async function loadExpenses() {
    const res = await api.get(`/expenses?org_id=${ORG_ID}`);
    setExpenses(res.data);
  }

  useEffect(() => {
    let isMounted = true;
    loadExpenses().finally(() => { if (isMounted) setLoading(false); });
    return () => { isMounted = false; };
  }, []);

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      await api.post('/expenses', {
        org_id: ORG_ID,
        branch_id: BRANCH_ID,
        description,
        amount: Number(amount),
        created_by: user.id,
      });
      setDescription('');
      setAmount('');
      await loadExpenses();
    } catch (err) {
      setError(err.response?.data?.message || 'Could not submit this expense.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AppLayout title="Expenses">
      <h1 className="text-2xl font-bold text-slate-900 mb-6">Expenses</h1>

      <form onSubmit={handleSubmit} className="bg-white border border-slate-200 rounded-xl p-6 mb-6 flex items-end gap-4">
        <div className="flex-1">
          <label className="block text-xs font-semibold text-slate-600 mb-1">Description</label>
          <input
            type="text" value={description} onChange={(e) => setDescription(e.target.value)}
            required className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
          />
        </div>
        <div>
          <label className="block text-xs font-semibold text-slate-600 mb-1">Amount (KES)</label>
          <input
            type="number" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)}
            required className="w-32 px-3 py-2 border border-slate-300 rounded-lg text-sm"
          />
        </div>
        <button
          type="submit" disabled={submitting}
          className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg"
        >
          {submitting ? 'Submitting...' : 'Submit Expense'}
        </button>
      </form>

      <p className="text-xs text-slate-400 mb-4">
        Expenses require two separate approval stages (Approval + Authorization) — visit the Approvals page to progress them.
      </p>

      {error && (
        <div className="bg-red-50 text-red-700 px-4 py-3 rounded-lg mb-4">{error}</div>
      )}

      {loading && <p className="text-slate-500">Loading...</p>}

      {!loading && expenses.length === 0 && (
        <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-500">
          No expenses recorded yet.
        </div>
      )}

      {!loading && expenses.length > 0 && (
        <div className="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-slate-600 text-left">
              <tr>
                <th className="px-4 py-3 font-semibold">Description</th>
                <th className="px-4 py-3 font-semibold">Amount</th>
                <th className="px-4 py-3 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {expenses.map((exp) => (
                <tr key={exp.id}>
                  <td className="px-4 py-3 text-slate-900">{exp.description}</td>
                  <td className="px-4 py-3 text-slate-900">{formatCurrency(exp.amount)}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${STATUS_STYLES[exp.status] ?? 'bg-slate-100 text-slate-600'}`}>
                      {exp.status}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
