import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import AppLayout from '../components/AppLayout';

const STATUS_LABELS = {
  0: { text: 'Pending', className: 'bg-yellow-100 text-yellow-800' },
  1: { text: 'Active', className: 'bg-green-100 text-green-800' },
  2: { text: 'Inactive', className: 'bg-slate-100 text-slate-600' },
  3: { text: 'Deceased', className: 'bg-slate-100 text-slate-600' },
  4: { text: 'Unspecified', className: 'bg-slate-100 text-slate-600' },
  5: { text: 'Closed', className: 'bg-red-100 text-red-700' },
};

function formatCurrency(value) {
  const number = Number(value ?? 0);
  return `KES ${number.toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
}

export default function ClientDetailPage() {
  const { id } = useParams();
  const { user } = useAuth();
  const [client, setClient] = useState(null);
  const [savingsAccounts, setSavingsAccounts] = useState([]);
  const [savingsProducts, setSavingsProducts] = useState([]);
  const [selectedProductId, setSelectedProductId] = useState('');
  const [openingAccount, setOpeningAccount] = useState(false);
  const [openError, setOpenError] = useState('');
  const [openSuccess, setOpenSuccess] = useState('');
  const [sharesAccounts, setSharesAccounts] = useState([]);
  const [sharesProducts, setSharesProducts] = useState([]);
  const [selectedShareProductId, setSelectedShareProductId] = useState('');
  const [openingShareAccount, setOpeningShareAccount] = useState(false);
  const [shareOpenError, setShareOpenError] = useState('');
  const [shareOpenSuccess, setShareOpenSuccess] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const ORG_ID = 1;

  async function loadSavingsAccounts() {
    const res = await api.get('/savings-accounts', { params: { client_id: id } });
    setSavingsAccounts(res.data);
  }

  async function loadSharesAccounts() {
    const res = await api.get('/shares-accounts', { params: { client_id: id } });
    setSharesAccounts(res.data);
  }

  useEffect(() => {
    let isMounted = true;

    async function loadClient() {
      try {
        const [clientRes, productsRes, shareProductsRes] = await Promise.all([
          api.get(`/clients/${id}`),
          api.get(`/savings-products?org_id=${ORG_ID}`),
          api.get(`/shares-products?org_id=${ORG_ID}`),
        ]);
        if (isMounted) {
          setClient(clientRes.data);
          setSavingsProducts(productsRes.data);
          setSharesProducts(shareProductsRes.data);
        }
        await loadSavingsAccounts();
        await loadSharesAccounts();
      } catch (err) {
        if (isMounted) setError('Could not load this client.');
      } finally {
        if (isMounted) setLoading(false);
      }
    }

    loadClient();
    return () => { isMounted = false; };
  }, [id]);

  async function handleOpenSavingsAccount(event) {
    event.preventDefault();
    setOpenError('');
    setOpenSuccess('');
    setOpeningAccount(true);
    try {
      await api.post('/savings-accounts', {
        org_id: ORG_ID,
        branch_id: client.branch_id,
        client_id: Number(id),
        savings_product_id: Number(selectedProductId),
        initiated_by: user.id,
      });
      setOpenSuccess('Submitted for approval — visit the Approvals page to complete it.');
    } catch (err) {
      setOpenError(err.response?.data?.message || 'Could not submit this request.');
    } finally {
      setOpeningAccount(false);
    }
  }

  async function handleOpenShareAccount(event) {
    event.preventDefault();
    setShareOpenError('');
    setShareOpenSuccess('');
    setOpeningShareAccount(true);
    try {
      await api.post('/shares-accounts', {
        org_id: ORG_ID,
        branch_id: client.branch_id,
        client_id: Number(id),
        shares_product_id: Number(selectedShareProductId),
        initiated_by: user.id,
      });
      setShareOpenSuccess('Submitted for approval — visit the Approvals page to complete it.');
    } catch (err) {
      setShareOpenError(err.response?.data?.message || 'Could not submit this request.');
    } finally {
      setOpeningShareAccount(false);
    }
  }

  if (loading) {
    return (
      <AppLayout title="Client Detail">
        <p className="text-slate-500">Loading client...</p>
      </AppLayout>
    );
  }

  if (error || !client) {
    return (
      <AppLayout title="Client Detail">
        <div className="bg-red-50 text-red-700 px-4 py-3 rounded-lg">
          {error || 'Client not found.'}
        </div>
      </AppLayout>
    );
  }

  const status = STATUS_LABELS[client.status] ?? STATUS_LABELS[4];

  return (
    <AppLayout title={`${client.first_name} ${client.last_name}`}>
      <div className="flex items-center justify-between">
        <Link to="/clients" className="text-sm text-blue-600 hover:underline">
          &larr; Back to Clients
        </Link>
        <Link
          to={`/clients/${client.id}/apply-loan`}
          className="text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg"
        >
          + Apply for Loan
        </Link>
      </div>

      <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Profile card */}
        <div className="md:col-span-2 bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between mb-4">
            <h1 className="text-xl font-bold text-slate-900">
              {client.first_name} {client.middle_name} {client.last_name}
            </h1>
            <span className={`px-2 py-1 rounded-full text-xs font-medium ${status.className}`}>
              {status.text}
            </span>
          </div>

          <dl className="grid grid-cols-2 gap-y-3 text-sm">
            <dt className="text-slate-500">Member Number</dt>
            <dd className="text-slate-900 font-mono">{client.member_number}</dd>

            <dt className="text-slate-500">Mobile Number</dt>
            <dd className="text-slate-900">{client.mobile_number}</dd>

            <dt className="text-slate-500">Email</dt>
            <dd className="text-slate-900">{client.email || '—'}</dd>

            <dt className="text-slate-500">Branch</dt>
            <dd className="text-slate-900">{client.branch?.name ?? '—'}</dd>

            <dt className="text-slate-500">Account Type</dt>
            <dd className="text-slate-900 capitalize">{client.account_type}</dd>
          </dl>
        </div>

        {/* Wallet card */}
        <div className="bg-white border border-slate-200 rounded-xl p-6">
          <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-2">
            Wallet Balance
          </h2>
          {client.wallet ? (
            <>
              <p className="text-3xl font-bold text-slate-900">
                {formatCurrency(client.wallet.balance)}
              </p>
              <p className="text-xs text-slate-400 mt-1 font-mono">
                {client.wallet.wallet_number}
              </p>
            </>
          ) : (
            <p className="text-slate-400 text-sm">No wallet on record.</p>
          )}
        </div>
      </div>

      {/* Savings accounts */}
      <div className="mt-6 bg-white border border-slate-200 rounded-xl p-6">
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">
          Savings Accounts
        </h2>

        {savingsAccounts.length > 0 && (
          <ul className="space-y-2 mb-4">
            {savingsAccounts.map((acc) => (
              <li key={acc.id} className="flex items-center justify-between bg-slate-50 rounded-lg px-4 py-3 text-sm">
                <div>
                  <span className="font-mono text-slate-700">{acc.account_number}</span>
                  <span className="text-slate-400 mx-2">·</span>
                  <span className="text-slate-600">{acc.product?.name}</span>
                </div>
                <span className="font-semibold text-slate-900">
                  {formatCurrency(acc.balance)}
                </span>
              </li>
            ))}
          </ul>
        )}

        {savingsAccounts.length === 0 && (
          <p className="text-sm text-slate-400 mb-4">No savings accounts yet.</p>
        )}

        {/* BRD: savings account creation is maker-checker, so this
            submits a request rather than creating the account directly.
            The account only appears above once someone approves it
            via the Approvals page. */}
        <form onSubmit={handleOpenSavingsAccount} className="flex items-end gap-3 border-t border-slate-100 pt-4">
          <div className="flex-1">
            <label className="block text-xs font-semibold text-slate-600 mb-1">
              Open a new savings account
            </label>
            <select
              value={selectedProductId}
              onChange={(e) => setSelectedProductId(e.target.value)}
              required
              className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
            >
              <option value="" disabled>Select a product...</option>
              {savingsProducts.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          </div>
          <button
            type="submit"
            disabled={openingAccount}
            className="bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-4 py-2 rounded-lg"
          >
            {openingAccount ? 'Submitting...' : 'Request'}
          </button>
        </form>

        {openError && (
          <div className="mt-3 bg-red-50 text-red-700 text-sm px-3 py-2 rounded-lg">{openError}</div>
        )}
        {openSuccess && (
          <div className="mt-3 bg-green-50 text-green-700 text-sm px-3 py-2 rounded-lg">{openSuccess}</div>
        )}
      </div>

      {/* Shares accounts -- same maker-checker pattern as Savings above */}
      <div className="mt-6 bg-white border border-slate-200 rounded-xl p-6">
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">
          Shares Accounts
        </h2>

        {sharesAccounts.length > 0 && (
          <ul className="space-y-2 mb-4">
            {sharesAccounts.map((acc) => (
              <li key={acc.id} className="flex items-center justify-between bg-slate-50 rounded-lg px-4 py-3 text-sm">
                <div>
                  <span className="font-mono text-slate-700">{acc.account_number}</span>
                  <span className="text-slate-400 mx-2">·</span>
                  <span className="text-slate-600">{acc.product?.name}</span>
                </div>
                <span className="font-semibold text-slate-900">
                  {formatCurrency(acc.balance)}
                </span>
              </li>
            ))}
          </ul>
        )}

        {sharesAccounts.length === 0 && (
          <p className="text-sm text-slate-400 mb-4">No shares accounts yet.</p>
        )}

        <form onSubmit={handleOpenShareAccount} className="flex items-end gap-3 border-t border-slate-100 pt-4">
          <div className="flex-1">
            <label className="block text-xs font-semibold text-slate-600 mb-1">
              Open a new shares account
            </label>
            <select
              value={selectedShareProductId}
              onChange={(e) => setSelectedShareProductId(e.target.value)}
              required
              className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
            >
              <option value="" disabled>Select a product...</option>
              {sharesProducts.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          </div>
          <button
            type="submit"
            disabled={openingShareAccount}
            className="bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-4 py-2 rounded-lg"
          >
            {openingShareAccount ? 'Submitting...' : 'Request'}
          </button>
        </form>

        {shareOpenError && (
          <div className="mt-3 bg-red-50 text-red-700 text-sm px-3 py-2 rounded-lg">{shareOpenError}</div>
        )}
        {shareOpenSuccess && (
          <div className="mt-3 bg-green-50 text-green-700 text-sm px-3 py-2 rounded-lg">{shareOpenSuccess}</div>
        )}
      </div>
    </AppLayout>
  );
}
