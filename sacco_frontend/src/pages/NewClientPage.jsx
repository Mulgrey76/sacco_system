import { useEffect, useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import AppLayout from '../components/AppLayout';

/**
 * BRD Section 7 "Client Profile/Account Manager — Single profile
 * creation": accommodates 4 names (first required, middle optional,
 * last required, other optional), validates mobile number, and the
 * member_number is auto-generated server-side (AccountNumberSequence)
 * -- we never ask for it here.
 */
export default function NewClientPage() {
  const navigate = useNavigate();
  const { user } = useAuth();

  const [branches, setBranches] = useState([]);
  const [branchId, setBranchId] = useState('');
  const [accountCategory, setAccountCategory] = useState('100100'); // BRD example: category A
  const [accountType, setAccountType] = useState('individual');
  const [firstName, setFirstName] = useState('');
  const [middleName, setMiddleName] = useState('');
  const [lastName, setLastName] = useState('');
  const [otherNames, setOtherNames] = useState('');
  const [nationalId, setNationalId] = useState('');
  const [mobileNumber, setMobileNumber] = useState('');
  const [email, setEmail] = useState('');

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const ORG_ID = 1;

  useEffect(() => {
    let isMounted = true;
    api.get(`/branches?org_id=${ORG_ID}`)
      .then((res) => { if (isMounted) setBranches(res.data); })
      .catch(() => { if (isMounted) setError('Could not load branches.'); })
      .finally(() => { if (isMounted) setLoading(false); });
    return () => { isMounted = false; };
  }, []);

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setSubmitting(true);

    try {
      const response = await api.post('/clients', {
        org_id: ORG_ID,
        branch_id: Number(branchId),
        account_category: accountCategory,
        account_type: accountType,
        first_name: firstName,
        middle_name: middleName || undefined,
        last_name: lastName,
        other_names: otherNames || undefined,
        national_id: nationalId || undefined,
        mobile_number: mobileNumber,
        email: email || undefined,
        onboarded_by: user.id,
      });

      // Land directly on the new client's own page -- the most useful
      // next step after onboarding is usually "now open a savings
      // account" or "now apply for a loan," both reachable from there.
      navigate(`/clients/${response.data.id}`);
    } catch (err) {
      const backendMessage = err.response?.data?.message;
      const validationErrors = err.response?.data?.errors;
      if (validationErrors) {
        // Laravel validation errors come as { field: [messages] } --
        // flatten to one readable string.
        setError(Object.values(validationErrors).flat().join(' '));
      } else {
        setError(backendMessage || 'Could not create this client.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return <AppLayout title="New Client"><p className="text-slate-500">Loading...</p></AppLayout>;
  }

  return (
    <AppLayout title="New Client">
      <Link to="/clients" className="text-sm text-blue-600 hover:underline">&larr; Back to Clients</Link>

      <div className="max-w-2xl mt-4 bg-white border border-slate-200 rounded-xl p-6">
        <h1 className="text-lg font-bold text-slate-900 mb-1">Onboard New Client</h1>
        <p className="text-sm text-slate-500 mb-6">
          A member number and wallet are generated automatically once submitted.
        </p>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">First Name *</label>
              <input
                type="text" value={firstName} onChange={(e) => setFirstName(e.target.value)} required
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">Middle Name</label>
              <input
                type="text" value={middleName} onChange={(e) => setMiddleName(e.target.value)}
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">Last Name *</label>
              <input
                type="text" value={lastName} onChange={(e) => setLastName(e.target.value)} required
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">Other Names</label>
              <input
                type="text" value={otherNames} onChange={(e) => setOtherNames(e.target.value)}
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">Mobile Number *</label>
              <input
                type="text" value={mobileNumber} onChange={(e) => setMobileNumber(e.target.value)}
                placeholder="0712345678" required
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">National ID</label>
              <input
                type="text" value={nationalId} onChange={(e) => setNationalId(e.target.value)}
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-1">Email</label>
            <input
              type="email" value={email} onChange={(e) => setEmail(e.target.value)}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">Branch *</label>
              <select
                value={branchId} onChange={(e) => setBranchId(e.target.value)} required
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="" disabled>Select a branch...</option>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">Account Type *</label>
              <select
                value={accountType} onChange={(e) => setAccountType(e.target.value)} required
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="individual">Individual</option>
                <option value="joint">Joint</option>
                <option value="diaspora">Diaspora</option>
              </select>
            </div>
          </div>

          {error && (
            <div className="bg-red-50 text-red-700 text-sm px-3 py-2 rounded-lg">{error}</div>
          )}

          <button
            type="submit"
            disabled={submitting}
            className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 text-white font-semibold py-2.5 rounded-lg transition-colors"
          >
            {submitting ? 'Creating...' : 'Create Client'}
          </button>
        </form>
      </div>
    </AppLayout>
  );
}
