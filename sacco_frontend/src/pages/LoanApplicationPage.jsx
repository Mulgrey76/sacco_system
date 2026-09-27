import { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import AppLayout from '../components/AppLayout';

/**
 * BRD Section 8 "Loan creation": create a loan application with at
 * least one guarantor attached in the same request -- matching
 * LoanController::store() on the backend, which we already proved
 * works end-to-end via Postman.
 */
export default function LoanApplicationPage() {
  const { clientId } = useParams(); // the borrower, from /clients/:clientId/apply-loan
  const navigate = useNavigate();
  const { user } = useAuth();

  const [client, setClient] = useState(null);
  const [products, setProducts] = useState([]);
  const [otherClients, setOtherClients] = useState([]);

  const [selectedProductId, setSelectedProductId] = useState('');
  const [amount, setAmount] = useState('');
  const [guarantorClientId, setGuarantorClientId] = useState('');
  const [note, setNote] = useState('');

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(null);

  const ORG_ID = 1; // same placeholder as ClientsPage, until org context exists

  useEffect(() => {
    let isMounted = true;

    async function loadFormData() {
      try {
        const [clientRes, productsRes, clientsRes] = await Promise.all([
          api.get(`/clients/${clientId}`),
          api.get(`/loan-products?org_id=${ORG_ID}`),
          api.get(`/clients?org_id=${ORG_ID}`),
        ]);

        if (!isMounted) return;

        setClient(clientRes.data);
        setProducts(productsRes.data);
        // Exclude the borrower themself from the guarantor list --
        // BRD doesn't allow a client to guarantee their own loan.
        setOtherClients(clientsRes.data.data.filter((c) => c.id !== Number(clientId)));
      } catch (err) {
        if (isMounted) setError('Could not load the data needed for this form.');
      } finally {
        if (isMounted) setLoading(false);
      }
    }

    loadFormData();
    return () => { isMounted = false; };
  }, [clientId]);

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setSubmitting(true);

    try {
      const response = await api.post('/loans', {
        org_id: ORG_ID,
        branch_id: client.branch_id,
        client_id: Number(clientId),
        loan_product_id: Number(selectedProductId),
        loan_officer_id: user.id,
        amount_applied: Number(amount),
        guarantors: [
          {
            guarantor_type: 'internal',
            client_id: Number(guarantorClientId),
          },
        ],
        creation_note: note || undefined,
      });

      setSuccess(response.data);
    } catch (err) {
      // Surface the EXACT message our backend returns -- e.g. "Amount
      // must be between X and Y for this product," or "Client has no
      // active wallet" -- rather than a generic failure message.
      setError(err.response?.data?.message || 'Could not submit the loan application.');
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <AppLayout title="New Loan Application">
        <p className="text-slate-500">Loading...</p>
      </AppLayout>
    );
  }

  if (success) {
    return (
      <AppLayout title="New Loan Application">
        <div className="max-w-lg mx-auto bg-white border border-slate-200 rounded-xl p-6 text-center">
          <div className="text-green-600 text-4xl mb-3">✓</div>
          <h1 className="text-lg font-bold text-slate-900 mb-1">
            Loan application submitted
          </h1>
          <p className="text-slate-500 text-sm mb-4">
            Reference <span className="font-mono">{success.loan_reference}</span> is now
            pending guarantor approval.
          </p>
          <Link
            to={`/clients/${clientId}`}
            className="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg text-sm"
          >
            Back to Client
          </Link>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout title="New Loan Application">
      <Link to={`/clients/${clientId}`} className="text-sm text-blue-600 hover:underline">
        &larr; Back to {client?.first_name}'s profile
      </Link>

      <div className="max-w-lg mt-4 bg-white border border-slate-200 rounded-xl p-6">
        <h1 className="text-lg font-bold text-slate-900 mb-1">New Loan Application</h1>
        <p className="text-sm text-slate-500 mb-6">
          Borrower: <strong>{client?.first_name} {client?.last_name}</strong> ({client?.member_number})
        </p>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-1">
              Loan Product
            </label>
            <select
              value={selectedProductId}
              onChange={(e) => setSelectedProductId(e.target.value)}
              required
              className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="" disabled>Select a product...</option>
              {products.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} (KES {Number(p.min_amount).toLocaleString()} – {Number(p.max_amount).toLocaleString()})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-1">
              Amount Applied (KES)
            </label>
            <input
              type="number"
              min="1"
              step="0.01"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
              className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-1">
              Guarantor (internal / member)
            </label>
            <select
              value={guarantorClientId}
              onChange={(e) => setGuarantorClientId(e.target.value)}
              required
              className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="" disabled>Select a guarantor...</option>
              {otherClients.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.first_name} {c.last_name} ({c.member_number})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-1">
              Note (optional)
            </label>
            <textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              rows={2}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {error && (
            <div className="bg-red-50 text-red-700 text-sm px-3 py-2 rounded-lg">
              {error}
            </div>
          )}

          <button
            type="submit"
            disabled={submitting}
            className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 text-white font-semibold py-2.5 rounded-lg transition-colors"
          >
            {submitting ? 'Submitting...' : 'Submit Application'}
          </button>
        </form>
      </div>
    </AppLayout>
  );
}
