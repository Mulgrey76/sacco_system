import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  FaSignOutAlt, FaBell, FaMoon, FaSun, FaUserCircle, FaChevronDown,
} from 'react-icons/fa';
import Sidebar from './Sidebar';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';

export default function AppLayout({ title, children }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const [darkMode, setDarkMode] = useState(() => localStorage.getItem('sacco_dark_mode') === 'true');
  const [notifOpen, setNotifOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [pendingApprovals, setPendingApprovals] = useState([]);
  const notifRef = useRef(null);
  const profileRef = useRef(null);

  const ORG_ID = 1;

  // Apply/persist dark mode
  useEffect(() => {
    document.documentElement.classList.toggle('dark', darkMode);
    localStorage.setItem('sacco_dark_mode', String(darkMode));
  }, [darkMode]);

  // Real notification source: pending maker-checker requests, the same
  // data already proven working on the Dashboard and Approvals pages --
  // no fabricated notification feed.
  useEffect(() => {
    let isMounted = true;
    api.get('/approvals', { params: { org_id: ORG_ID, status: 'pending' } })
      .then((res) => { if (isMounted) setPendingApprovals(res.data.data); })
      .catch(() => {});
    return () => { isMounted = false; };
  }, []);

  // Close dropdowns on outside click
  useEffect(() => {
    function handleClickOutside(e) {
      if (notifRef.current && !notifRef.current.contains(e.target)) setNotifOpen(false);
      if (profileRef.current && !profileRef.current.contains(e.target)) setProfileOpen(false);
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  function handleLogout() {
    logout();
    navigate('/login');
  }

  function describeRequest(req) {
    if (req.entity_type === 'LOAN_APPROVAL') return `Loan #${req.entity_id} needs approval`;
    if (req.entity_type === 'SAVINGS_ACCOUNT_CREATION') return 'New savings account request';
    if (req.entity_type === 'SHARES_ACCOUNT_CREATION') return 'New shares account request';
    if (req.entity_type === 'EXPENSE_AUTHORIZATION') return `Expense #${req.entity_id} needs authorization`;
    return req.entity_type;
  }

  return (
    <div className="min-h-screen flex bg-app-bg dark:bg-[#0B1120]">
      <Sidebar />

      <div className="flex-1 flex flex-col min-w-0">
        <header className="h-16 bg-card dark:bg-[#0F172A] border-b border-light-border dark:border-white/10 px-6 flex items-center justify-between sticky top-0 z-20">
          <h1 className="text-lg font-semibold text-secondary dark:text-white">{title}</h1>

          <div className="flex items-center gap-3">
            {/* Dark mode toggle */}
            <button
              onClick={() => setDarkMode((d) => !d)}
              className="w-9 h-9 flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors"
              title="Toggle dark mode"
            >
              {darkMode ? <FaSun size={15} /> : <FaMoon size={15} />}
            </button>

            {/* Notification bell */}
            <div className="relative" ref={notifRef}>
              <button
                onClick={() => setNotifOpen((o) => !o)}
                className="relative w-9 h-9 flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors"
              >
                <FaBell size={15} />
                {pendingApprovals.length > 0 && (
                  <span className="absolute top-1 right-1 w-4 h-4 bg-danger text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                    {pendingApprovals.length}
                  </span>
                )}
              </button>
              {notifOpen && (
                <div className="absolute right-0 mt-2 w-72 bg-card dark:bg-[#0F172A] border border-light-border dark:border-white/10 rounded-xl shadow-lg py-2 z-30">
                  <p className="px-4 py-1.5 text-xs font-semibold text-slate-400 uppercase">Pending Approvals</p>
                  {pendingApprovals.length === 0 ? (
                    <p className="px-4 py-3 text-sm text-slate-400">Nothing pending.</p>
                  ) : (
                    pendingApprovals.slice(0, 6).map((req) => (
                      <button
                        key={req.id}
                        onClick={() => { setNotifOpen(false); navigate('/approvals'); }}
                        className="w-full text-left px-4 py-2 text-sm text-body-text dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/5"
                      >
                        {describeRequest(req)}
                      </button>
                    ))
                  )}
                </div>
              )}
            </div>

            {/* Profile menu */}
            <div className="relative" ref={profileRef}>
              <button
                onClick={() => setProfileOpen((o) => !o)}
                className="flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 transition-colors"
              >
                <div className="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center text-sm font-semibold">
                  {user?.username?.[0]?.toUpperCase()}
                </div>
                <span className="text-sm text-body-text dark:text-slate-200 font-medium hidden sm:block">
                  {user?.username}
                </span>
                <FaChevronDown size={10} className="text-slate-400" />
              </button>
              {profileOpen && (
                <div className="absolute right-0 mt-2 w-48 bg-card dark:bg-[#0F172A] border border-light-border dark:border-white/10 rounded-xl shadow-lg py-2 z-30">
                  <div className="px-4 py-2 border-b border-light-border dark:border-white/10">
                    <p className="text-sm font-semibold text-secondary dark:text-white">{user?.username}</p>
                    <p className="text-xs text-slate-400">Imara Sacco</p>
                  </div>
                  <button className="w-full flex items-center gap-2 text-left px-4 py-2 text-sm text-slate-400 cursor-not-allowed">
                    <FaUserCircle size={13} /> Profile <span className="text-[10px] ml-auto">Soon</span>
                  </button>
                  <button
                    onClick={handleLogout}
                    className="w-full flex items-center gap-2 text-left px-4 py-2 text-sm text-danger hover:bg-red-50 dark:hover:bg-white/5"
                  >
                    <FaSignOutAlt size={13} /> Log Out
                  </button>
                </div>
              )}
            </div>
          </div>
        </header>

        <main className="flex-1 max-w-7xl w-full mx-auto px-6 py-8">{children}</main>
      </div>
    </div>
  );
}
