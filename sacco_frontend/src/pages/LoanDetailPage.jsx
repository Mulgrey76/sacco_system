import { useEffect, useState, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import AppLayout from '../components/AppLayout';

const LOAN_STATUS_TEXT = {
  0: 'Pending', 1: 'Approved', 2: 'Active', 3: 'Closed', 4: 'Overdue',
  5: 'Withdrawn', 6: 'Rejected', 7: 'Rescheduled', 8: 'Written Off', 9: 'Overpaid',
};

export default function LoanDetailPage() {
  const { id } = useParams();
  const { user } = useAuth();

  const [loan, setLoan] = useState(null);
  const [approvalRequest, setApprovalRequest] = useState(null);
  const [loading, setLoading] = useState(true);
  const [actionError, setActionError] = useState('');
  const [actionBusy, setActionBusy] = useState(false);

  const [installments, setInstallments] = useState(3);
  const [externalReference, setExternalReference] = useState('');

  const loadLoan = useCallback(async () => {
    const loanRes = await api.get(`/loans/${id}`);
    setLoan(loanRes.data);

    // Look up whether a LOAN_APPROVAL request already exists for this
    // loan -- lets the page show "awaiting approval" state correctly
    // even after a page refresh, not just right after submitting.
    if (loanRes.data.status === 0) {
      try {
        const approvalRes = await api.get('/approvals', {
          params: { org_id: loanRes.data.org_id, entity_type: 'LOAN_APPROVAL', entity_id: id },
        });
        const requests = approvalRes.data.data;
        setApprovalRequest(requests.length > 0 ? requests[requests.length - 1] : null);
      } catch {
        setApprovalRequest(null);
      }
    } else {
      setApprovalRequest(null);
    }
  }, [id]);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    loadLoan().finally(() => { if (isMounted) setLoading(false); });
    return () => { isMounted = false; };
  }, [loadLoan]);

  async function handleApproveGuarantor(guarantorId) {
    setActionError('');
    setActionBusy(true);
    try {
      await api.post(`/loans/${id}/guarantors/${guarantorId}/approve`);
      await loadLoan();
    } catch (err) {
      setActionError(err.response?.data?.message || 'Could not approve guarantor.');
    } finally {
      setActionBusy(false);
    }
  }

  async function handleRequestApproval() {
    setActionError('');
    setActionBusy(true);
    try {
      await api.post(`/loans/${id}/request-approval`, { initiated_by: user.id });
      await loadLoan();
    } catch (err) {
      setActionError(err.response?.data?.message || 'Could not submit for approval.');
    } finally {
      setActionBusy(false);
    }
  }

  async function handleDecideApproval(action) {
    setActionError('');
    setActionBusy(true);
    try {
      await api.post(`/approvals/${approvalRequest.id}/decide`, {
        acted_by: user.id,
        action,
      });
      await loadLoan();
    } catch (err) {
      // This is where the maker-checker rule surfaces directly to the
      // user: if the same person who initiated tries to approve, our
      // backend rejects it with a clear message -- shown here verbatim.
      setActionError(err.response?.data?.message || 'Could not record the decision.');
    } finally {
      setActionBusy(false);
    }
  }

  async function handleDisburse(event) {
    event.preventDefault();
    setActionError('');
    setActionBusy(true);
    try {
      await api.post(`/loans/${id}/disburse`, {
        installments: Number(installments),
        external_reference: externalReference,
        disbursed_by: user.id,
      });
      await loadLoan();
    } catch (err) {
      setActionError(err.response?.data?.message || 'Could not disburse this loan.');
    } finally {
      setActionBusy(false);
    }
  }

  if (loading) {
    return <AppLayout title="Loan Detail"><p className="text-slate-500">Loading...</p></AppLayout>;
  }

  if (!loan) {
    return <AppLayout title="Loan Detail"><p className="text-red-600">Loan not found.</p></AppLayout>;
  }

  const allGuarantorsApproved = loan.guarantors.every((g) => g.approval_status === 'approved');

  return (
    <AppLayout title={loan.loan_reference}>
      <Link to="/loans" className="text-sm text-blue-600 hover:underline">&larr; Back to Loans</Link>

      <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Loan summary */}
        <div className="md:col-span-2 bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between mb-4">
            <h1 className="text-xl font-bold text-slate-900">{loan.loan_reference}</h1>
            <span className="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              {LOAN_STATUS_TEXT[loan.status]}
            </span>
          </div>

          <dl className="grid grid-cols-2 gap-y-3 text-sm mb-6">
            <dt className="text-slate-500">Client</dt>
            <dd className="text-slate-900">{loan.client.first_name} {loan.client.last_name}</dd>

            <dt className="text-slate-500">Product</dt>
            <dd className="text-slate-900">{loan.product.name}</dd>

            <dt className="text-slate-500">Amount Applied</dt>
            <dd className="text-slate-900">KES {Number(loan.amount_applied).toLocaleString()}</dd>

            <dt className="text-slate-500">Amount Approved</dt>
            <dd className="text-slate-900">
              {loan.amount_approved ? `KES ${Number(loan.amount_approved).toLocaleString()}` : '—'}
            </dd>
          </dl>

          {/* Guarantors */}
          <h2 className="text-sm font-semibold text-slate-700 mb-2">Guarantors</h2>
          <ul className="space-y-2 mb-4">
            {loan.guarantors.map((g) => (
              <li key={g.id} className="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2 text-sm">
                <span>
                  {g.guarantor_type === 'internal'
                    ? `Client #${g.client_id}`
                    : g.external_full_name}
                </span>
                {g.approval_status === 'approved' ? (
                  <span className="text-green-700 text-xs font-medium">✓ Approved</span>
                ) : (
                  <button
                    onClick={() => handleApproveGuarantor(g.id)}
                    disabled={actionBusy}
                    className="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg"
                  >
                    Approve
                  </button>
                )}
              </li>
            ))}
          </ul>

          {actionError && (
            <div className="bg-red-50 text-red-700 text-sm px-3 py-2 rounded-lg mb-4">
              {actionError}
            </div>
          )}

          {/* Workflow actions, one stage at a time */}
          {loan.status === 0 && allGuarantorsApproved && !approvalRequest && (
            <button
              onClick={handleRequestApproval}
              disabled={actionBusy}
              className="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-4 py-2 rounded-lg text-sm"
            >
              Submit for Approval
            </button>
          )}

          {loan.status === 0 && approvalRequest && approvalRequest.status === 'pending' && (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <p className="text-sm text-yellow-800 mb-3">
                Awaiting approval ({approvalRequest.required_approvals} approver(s) required).
                Initiated by user #{approvalRequest.initiated_by}.
              </p>
              <div className="flex gap-2">
                <button
                  onClick={() => handleDecideApproval('approve')}
                  disabled={actionBusy}
                  className="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-lg"
                >
                  Approve
                </button>
                <button
                  onClick={() => handleDecideApproval('reject')}
                  disabled={actionBusy}
                  className="bg-red-100 hover:bg-red-200 text-red-700 text-sm font-semibold px-4 py-2 rounded-lg"
                >
                  Reject
                </button>
              </div>
            </div>
          )}

          {loan.status === 1 && (
            <form onSubmit={handleDisburse} className="space-y-3 bg-blue-50 border border-blue-200 rounded-lg p-4">
              <p className="text-sm text-blue-800 font-semibold">Ready to disburse</p>
              <div>
                <label className="block text-xs font-semibold text-slate-600 mb-1">Installments</label>
                <input
                  type="number" min="1" max="36" value={installments}
                  onChange={(e) => setInstallments(e.target.value)}
                  className="w-full px-3 py-1.5 border border-slate-300 rounded-lg text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-600 mb-1">
                  Transaction Reference (e.g. M-Pesa code)
                </label>
                <input
                  type="text" value={externalReference}
                  onChange={(e) => setExternalReference(e.target.value)}
                  required
                  className="w-full px-3 py-1.5 border border-slate-300 rounded-lg text-sm"
                />
              </div>
              <button
                type="submit"
                disabled={actionBusy}
                className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg"
              >
                Disburse Loan
              </button>
            </form>
          )}

          {loan.status === 2 && (
            <div className="bg-green-50 border border-green-200 rounded-lg p-4">
              <p className="text-sm text-green-800 font-semibold mb-2">Active — Repayment Schedule</p>
              <table className="w-full text-xs">
                <thead className="text-slate-500 text-left">
                  <tr><th>#</th><th>Due Date</th><th>Total</th></tr>
                </thead>
                <tbody>
                  {loan.repayment_schedule.map((s) => (
                    <tr key={s.id}>
                      <td className="py-1">{s.installment_number}</td>
                      <td className="py-1">{s.due_date}</td>
                      <td className="py-1">KES {Number(s.expected_total).toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Notes sidebar */}
        <div className="bg-white border border-slate-200 rounded-xl p-6">
          <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">
            Loan Notes
          </h2>
          <ul className="space-y-3">
            {loan.notes.map((n) => (
              <li key={n.id} className="text-sm border-l-2 border-slate-200 pl-3">
                <p className="text-slate-700">{n.note_text}</p>
                <p className="text-xs text-slate-400 capitalize mt-1">{n.stage}</p>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </AppLayout>
  );
}
