import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import AppLayout from '../components/AppLayout';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';

const LOAN_STATUS = {
  0: { text: 'Pending', tone: 'warning' },
  1: { text: 'Approved', tone: 'info' },
  2: { text: 'Active', tone: 'success' },
  3: { text: 'Closed', tone: 'neutral' },
  4: { text: 'Overdue', tone: 'danger' },
  5: { text: 'Withdrawn', tone: 'neutral' },
  6: { text: 'Rejected', tone: 'danger' },
  7: { text: 'Rescheduled', tone: 'info' },
  8: { text: 'Written Off', tone: 'neutral' },
  9: { text: 'Overpaid', tone: 'info' },
};

export default function LoansPage() {
  const [loans, setLoans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const ORG_ID = 1;

  useEffect(() => {
    let isMounted = true;
    api.get(`/loans?org_id=${ORG_ID}`)
      .then((res) => { if (isMounted) setLoans(res.data.data); })
      .catch(() => { if (isMounted) setError('Could not load loans.'); })
      .finally(() => { if (isMounted) setLoading(false); });
    return () => { isMounted = false; };
  }, []);

  const columns = [
    { key: 'loan_reference', label: 'Reference' },
    {
      key: 'client_name', label: 'Client',
      render: (row) => `${row.client?.first_name ?? ''} ${row.client?.last_name ?? ''}`,
    },
    { key: 'product_name', label: 'Product', render: (row) => row.product?.name },
    {
      key: 'amount_applied', label: 'Amount Applied',
      render: (row) => `KES ${Number(row.amount_applied).toLocaleString()}`,
    },
    {
      key: 'status', label: 'Status',
      render: (row) => {
        const s = LOAN_STATUS[row.status] ?? { text: 'Unknown', tone: 'neutral' };
        return <StatusBadge tone={s.tone}>{s.text}</StatusBadge>;
      },
    },
  ];

  const rows = loans.map((l) => ({
    ...l,
    client_name: `${l.client?.first_name ?? ''} ${l.client?.last_name ?? ''}`,
    product_name: l.product?.name ?? '',
  }));

  return (
    <AppLayout title="Loans">
      <h1 className="text-2xl font-bold text-secondary mb-6">Loans</h1>

      {loading && <p className="text-slate-500">Loading...</p>}
      {!loading && error && <div className="bg-red-50 text-danger px-4 py-3 rounded-lg">{error}</div>}

      {!loading && !error && (
        <DataTable
          columns={columns}
          data={rows}
          searchKeys={['loan_reference', 'client_name', 'product_name']}
          exportFilename="loans"
          onRowClick={(row) => navigate(`/loans/${row.id}`)}
        />
      )}
    </AppLayout>
  );
}
