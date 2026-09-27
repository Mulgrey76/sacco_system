import { useEffect, useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { FaPlus } from 'react-icons/fa';
import api from '../services/api';
import AppLayout from '../components/AppLayout';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';

const STATUS_MAP = {
  0: { text: 'Pending', tone: 'warning' },
  1: { text: 'Active', tone: 'success' },
  2: { text: 'Inactive', tone: 'neutral' },
  3: { text: 'Deceased', tone: 'neutral' },
  4: { text: 'Unspecified', tone: 'neutral' },
  5: { text: 'Closed', tone: 'danger' },
};

export default function ClientsPage() {
  const [clients, setClients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const ORG_ID = 1;

  useEffect(() => {
    let isMounted = true;
    api.get(`/clients?org_id=${ORG_ID}`)
      .then((res) => { if (isMounted) setClients(res.data.data); })
      .catch(() => { if (isMounted) setError('Could not load clients.'); })
      .finally(() => { if (isMounted) setLoading(false); });
    return () => { isMounted = false; };
  }, []);

  const columns = [
    { key: 'member_number', label: 'Member Number' },
    {
      key: 'full_name', label: 'Name',
      render: (row) => `${row.first_name} ${row.last_name}`,
    },
    { key: 'mobile_number', label: 'Mobile' },
    {
      key: 'branch_name', label: 'Branch', sortable: false,
      render: (row) => row.branch?.name ?? '—',
    },
    {
      key: 'status', label: 'Status',
      render: (row) => {
        const s = STATUS_MAP[row.status] ?? STATUS_MAP[4];
        return <StatusBadge tone={s.tone}>{s.text}</StatusBadge>;
      },
    },
  ];

  // pre-compute derived fields the table needs for search/sort/export
  const rows = clients.map((c) => ({
    ...c,
    full_name: `${c.first_name} ${c.last_name}`,
    branch_name: c.branch?.name ?? '',
  }));

  return (
    <AppLayout title="Members">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-secondary">Members</h1>
        <Link
          to="/clients/new"
          className="flex items-center gap-2 bg-primary hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors"
        >
          <FaPlus size={12} /> New Member
        </Link>
      </div>

      {loading && <p className="text-slate-500">Loading...</p>}
      {!loading && error && <div className="bg-red-50 text-danger px-4 py-3 rounded-lg">{error}</div>}

      {!loading && !error && (
        <DataTable
          columns={columns}
          data={rows}
          searchKeys={['member_number', 'full_name', 'mobile_number']}
          exportFilename="members"
          onRowClick={(row) => navigate(`/clients/${row.id}`)}
        />
      )}
    </AppLayout>
  );
}
