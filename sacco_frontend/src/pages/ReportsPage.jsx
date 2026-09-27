import { useEffect, useState } from 'react';
import api from '../services/api';
import AppLayout from '../components/AppLayout';

const REPORTS = [
  { key: 'loan-portfolio', label: 'Loan Portfolio' },
  { key: 'loan-arrears', label: 'Loan Arrears' },
  { key: 'disbursements', label: 'Disbursements' },
  { key: 'savings-balances', label: 'Savings Balances' },
];

function formatCurrency(value) {
  if (value === null || value === undefined) return '—';
  return `KES ${Number(value).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
}

// Each report returns a slightly different shape, so we define how to
// render each one's table rather than trying to force one generic table.
function ReportTable({ reportKey, rows }) {
  if (rows.length === 0) {
    return (
      <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-500">
        No data for this report.
      </div>
    );
  }

  if (reportKey === 'loan-portfolio') {
    return (
      <Table
        headers={['Reference', 'Client', 'Product', 'Amount Approved', 'Status']}
        rows={rows.map((r) => [
          r.loan_reference, r.client_name, r.product,
          formatCurrency(r.amount_approved), r.status,
        ])}
      />
    );
  }

  if (reportKey === 'loan-arrears') {
    return (
      <Table
        headers={['Reference', 'Client', 'Installment #', 'Due Date', 'Outstanding', 'Days Overdue']}
        rows={rows.map((r) => [
          r.loan_reference, r.client_name, r.installment_number,
          r.due_date, formatCurrency(r.outstanding), r.days_overdue,
        ])}
      />
    );
  }

  if (reportKey === 'disbursements') {
    return (
      <Table
        headers={['Reference', 'Client', 'Amount', 'External Reference', 'Processed At']}
        rows={rows.map((r) => [
          r.loan_reference, r.client_name, formatCurrency(r.amount),
          r.external_reference, r.processed_at,
        ])}
      />
    );
  }

  if (reportKey === 'savings-balances') {
    return (
      <Table
        headers={['Account Number', 'Client', 'Product', 'Balance', 'Status']}
        rows={rows.map((r) => [
          r.account_number, r.client_name, r.product,
          formatCurrency(r.balance), r.status,
        ])}
      />
    );
  }

  return null;
}

function Table({ headers, rows }) {
  return (
    <div className="bg-white border border-slate-200 rounded-xl overflow-hidden overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="bg-slate-50 text-slate-600 text-left">
          <tr>
            {headers.map((h) => (
              <th key={h} className="px-4 py-3 font-semibold whitespace-nowrap">{h}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {rows.map((row, i) => (
            <tr key={i} className="hover:bg-slate-50">
              {row.map((cell, j) => (
                <td key={j} className="px-4 py-3 text-slate-900 whitespace-nowrap">{cell ?? '—'}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function ReportsPage() {
  const [activeReport, setActiveReport] = useState('loan-portfolio');
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const ORG_ID = 1;

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    setError('');

    api.get(`/reports/${activeReport}?org_id=${ORG_ID}`)
      .then((res) => { if (isMounted) setRows(res.data); })
      .catch(() => { if (isMounted) setError('Could not load this report.'); })
      .finally(() => { if (isMounted) setLoading(false); });

    return () => { isMounted = false; };
  }, [activeReport]);

  return (
    <AppLayout title="Reports">
      <h1 className="text-2xl font-bold text-slate-900 mb-6">Reports</h1>

      <div className="flex gap-2 mb-6 border-b border-slate-200">
        {REPORTS.map((r) => (
          <button
            key={r.key}
            onClick={() => setActiveReport(r.key)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              activeReport === r.key
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            {r.label}
          </button>
        ))}
      </div>

      {loading && <p className="text-slate-500">Loading...</p>}

      {!loading && error && (
        <div className="bg-red-50 text-red-700 px-4 py-3 rounded-lg">{error}</div>
      )}

      {!loading && !error && (
        <ReportTable reportKey={activeReport} rows={rows} />
      )}
    </AppLayout>
  );
}
