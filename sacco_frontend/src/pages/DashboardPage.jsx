import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FaUsers, FaDollarSign, FaChartLine, FaPiggyBank, FaFileInvoice,
} from 'react-icons/fa';
import {
  ResponsiveContainer, PieChart, Pie, Cell, Tooltip, Legend,
  BarChart, Bar, XAxis, YAxis, CartesianGrid,
  LineChart, Line, AreaChart, Area,
} from 'recharts';
import api from '../services/api';
import AppLayout from '../components/AppLayout';
import StatCard from '../components/StatCard';
import ChartCard from '../components/ChartCard';

const PIE_COLORS = ['#2563EB', '#22C55E', '#F59E0B', '#EF4444', '#0F172A'];

function formatCurrency(value) {
  return `KES ${Number(value ?? 0).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
}

function monthLabel(dateString) {
  return new Date(dateString).toLocaleDateString('en-KE', { month: 'short', year: '2-digit' });
}

export default function DashboardPage() {
  const [stats, setStats] = useState(null);
  const [loanCategoryData, setLoanCategoryData] = useState([]);
  const [disbursementByMonth, setDisbursementByMonth] = useState([]);
  const [membershipGrowth, setMembershipGrowth] = useState([]);
  const [revenueByMonth, setRevenueByMonth] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const ORG_ID = 1;

  useEffect(() => {
    let isMounted = true;

    async function loadDashboard() {
      try {
        const [clientsRes, loansRes, pendingRes, savingsRes, disbursementsRes, journalRes] =
          await Promise.all([
            api.get(`/clients?org_id=${ORG_ID}`),
            api.get(`/reports/loan-portfolio?org_id=${ORG_ID}`),
            api.get('/approvals', { params: { org_id: ORG_ID, status: 'pending' } }),
            api.get(`/reports/savings-balances?org_id=${ORG_ID}`),
            api.get(`/reports/disbursements?org_id=${ORG_ID}`),
            api.get(`/journal-entries?org_id=${ORG_ID}`).catch(() => ({ data: { data: [] } })),
          ]);

        if (!isMounted) return;

        const totalSavings = savingsRes.data.reduce((sum, acc) => sum + Number(acc.balance), 0);
        const totalActiveLoanValue = loansRes.data.reduce((sum, l) => sum + Number(l.amount_approved ?? 0), 0);

        setStats({
          totalClients: clientsRes.data.total,
          activeLoans: loansRes.data.length,
          totalActiveLoanValue,
          pendingApprovals: pendingRes.data.data.length,
          totalSavings,
        });

        // Loan Categories Pie -- real, grouped by product name
        const byProduct = {};
        loansRes.data.forEach((l) => {
          byProduct[l.product] = (byProduct[l.product] || 0) + 1;
        });
        setLoanCategoryData(Object.entries(byProduct).map(([name, value]) => ({ name, value })));

        // Monthly Loan Disbursement Bar -- real, grouped by processed_at month
        const byMonth = {};
        disbursementsRes.data.forEach((d) => {
          if (!d.processed_at) return;
          const key = monthLabel(d.processed_at);
          byMonth[key] = (byMonth[key] || 0) + Number(d.amount);
        });
        setDisbursementByMonth(Object.entries(byMonth).map(([month, amount]) => ({ month, amount })));

        // Membership Growth Line -- real, cumulative client signups by month
        const clientsByMonth = {};
        clientsRes.data.data.forEach((c) => {
          const key = monthLabel(c.created_at);
          clientsByMonth[key] = (clientsByMonth[key] || 0) + 1;
        });
        let running = 0;
        setMembershipGrowth(
          Object.entries(clientsByMonth).map(([month, count]) => {
            running += count;
            return { month, members: running };
          })
        );

        // Monthly Revenue Area -- real, sum of GL credit lines per month
        const journalEntries = journalRes.data.data ?? journalRes.data ?? [];
        const revenueByMonthMap = {};
        journalEntries.forEach((entry) => {
          const key = monthLabel(entry.entry_date);
          const creditTotal = (entry.lines ?? []).reduce(
            (sum, line) => sum + Number(line.credit_amount || 0), 0
          );
          revenueByMonthMap[key] = (revenueByMonthMap[key] || 0) + creditTotal;
        });
        setRevenueByMonth(Object.entries(revenueByMonthMap).map(([month, amount]) => ({ month, amount })));

      } catch (err) {
        if (isMounted) setError('Could not load dashboard data.');
      } finally {
        if (isMounted) setLoading(false);
      }
    }

    loadDashboard();
    return () => { isMounted = false; };
  }, []);

  if (loading) {
    return <AppLayout title="Dashboard"><p className="text-slate-500">Loading...</p></AppLayout>;
  }

  if (error) {
    return <AppLayout title="Dashboard"><div className="bg-red-50 text-danger px-4 py-3 rounded-lg">{error}</div></AppLayout>;
  }

  const hour = new Date().getHours();
  const greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';

  return (
    <AppLayout title="Dashboard">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-secondary">{greeting}, Admin 👋</h1>
        <p className="text-slate-500 text-sm mt-1">Welcome back to Imara SACCO.</p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard label="Total Members" value={stats.totalClients} icon={FaUsers} colorClass="bg-blue-100 text-primary" />
        <StatCard label="Active Loans" value={stats.activeLoans} icon={FaDollarSign} colorClass="bg-amber-100 text-warning" />
        <StatCard label="Active Loan Value" value={formatCurrency(stats.totalActiveLoanValue)} icon={FaChartLine} colorClass="bg-green-100 text-success" />
        <StatCard label="Total Savings" value={formatCurrency(stats.totalSavings)} icon={FaPiggyBank} colorClass="bg-purple-100 text-purple-600" />
      </div>

      {stats.pendingApprovals > 0 && (
        <Link
          to="/approvals"
          className="block bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 hover:bg-amber-100 transition-colors"
        >
          <span className="text-amber-800 font-medium flex items-center gap-2">
            <FaFileInvoice size={14} />
            {stats.pendingApprovals} request{stats.pendingApprovals > 1 ? 's' : ''} awaiting approval
          </span>
        </Link>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <ChartCard title="Loan Categories" empty={loanCategoryData.length === 0}>
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie data={loanCategoryData} dataKey="value" nameKey="name" innerRadius={50} outerRadius={80} paddingAngle={3}>
                {loanCategoryData.map((_, i) => (
                  <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                ))}
              </Pie>
              <Tooltip />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        </ChartCard>

        <ChartCard title="Monthly Loan Disbursements" empty={disbursementByMonth.length === 0}>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={disbursementByMonth}>
              <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
              <XAxis dataKey="month" fontSize={12} />
              <YAxis fontSize={12} />
              <Tooltip formatter={(v) => formatCurrency(v)} />
              <Bar dataKey="amount" fill="#2563EB" radius={[6, 6, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </ChartCard>

        <ChartCard title="Membership Growth" empty={membershipGrowth.length === 0}>
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={membershipGrowth}>
              <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
              <XAxis dataKey="month" fontSize={12} />
              <YAxis fontSize={12} allowDecimals={false} />
              <Tooltip />
              <Line type="monotone" dataKey="members" stroke="#22C55E" strokeWidth={2.5} dot={{ r: 4 }} />
            </LineChart>
          </ResponsiveContainer>
        </ChartCard>

        <ChartCard title="Monthly Revenue (GL Credits)" empty={revenueByMonth.length === 0}>
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={revenueByMonth}>
              <defs>
                <linearGradient id="revenueGradient" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#2563EB" stopOpacity={0.35} />
                  <stop offset="95%" stopColor="#2563EB" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
              <XAxis dataKey="month" fontSize={12} />
              <YAxis fontSize={12} />
              <Tooltip formatter={(v) => formatCurrency(v)} />
              <Area type="monotone" dataKey="amount" stroke="#2563EB" fill="url(#revenueGradient)" strokeWidth={2} />
            </AreaChart>
          </ResponsiveContainer>
        </ChartCard>
      </div>
    </AppLayout>
  );
}
