import { useEffect, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import AppLayout from '../components/AppLayout';

/**
 * A single inbox for EVERY pending maker-checker request, regardless of
 * entity_type (SAVINGS_ACCOUNT_CREATION, LOAN_APPROVAL, and whatever
 * gets added later). This mirrors the generic design of
 * ApprovalController itself -- one screen, not one per entity type.
 */
export default function ApprovalsPage() {
  const { user } = useAuth();
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState('');

  const ORG_ID = 1;

  async function loadRequests() {
    const res = await api.get('/approvals', {
      params: { org_id: ORG_ID, status: 'pending' },
    });
    setRequests(res.data.data);
  }

  useEffect(() => {
    let isMounted = true;
    loadRequests().finally(() => { if (isMounted) setLoading(false); });
    return () => { isMounted = false; };
  }, []);

  async function handleDecide(requestId, action) {
    setError('');
    setBusyId(requestId);
    try {
      await api.post(`/approvals/${requestId}/decide`, { acted_by: user.id, action });
      await loadRequests();
    } catch (err) {
      setError(err.response?.data?.message || 'Could not record the decision.');
    } finally {
      setBusyId(null);
    }
  }

  // Turn the entity_type code into something readable, and the
  // payload into a short human-friendly summary line.
  function describe(request) {
    if (request.entity_type === 'SAVINGS_ACCOUNT_CREATION') {
      return `Open a new savings account for client #${request.payload_json.client_id}`;
    }
    if (request.entity_type === 'SHARES_ACCOUNT_CREATION') {
      return `Open a new shares account for client #${request.payload_json.client_id}`;
    }
    if (request.entity_type === 'LOAN_APPROVAL') {
      return `Approve loan #${request.entity_id} for KES ${Number(request.payload_json.amount_applied).toLocaleString()}`;
    }
    if (request.entity_type === 'EXPENSE_AUTHORIZATION') {
      return `Authorize expense #${request.entity_id} for KES ${Number(request.payload_json.amount).toLocaleString()}`;
    }
    return `${request.entity_type} — ${request.action}`;
  }

  return (
    <AppLayout title="Approvals">
      <h1 className="text-2xl font-bold text-slate-900 mb-6">Pending Approvals</h1>

      {loading && <p className="text-slate-500">Loading...</p>}

      {error && (
        <div className="bg-red-50 text-red-700 px-4 py-3 rounded-lg mb-4">{error}</div>
      )}

      {!loading && requests.length === 0 && (
        <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-500">
          Nothing pending right now.
        </div>
      )}

      {!loading && requests.length > 0 && (
        <div className="space-y-3">
          {requests.map((request) => (
            <div
              key={request.id}
              className="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between"
            >
              <div>
                <p className="text-sm text-slate-900">{describe(request)}</p>
                <p className="text-xs text-slate-400 mt-1">
                  Initiated by user #{request.initiated_by} · {request.required_approvals} approver(s) required
                </p>
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => handleDecide(request.id, 'approve')}
                  disabled={busyId === request.id}
                  className="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-lg"
                >
                  Approve
                </button>
                <button
                  onClick={() => handleDecide(request.id, 'reject')}
                  disabled={busyId === request.id}
                  className="bg-red-100 hover:bg-red-200 text-red-700 text-sm font-semibold px-4 py-2 rounded-lg"
                >
                  Reject
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </AppLayout>
  );
}
