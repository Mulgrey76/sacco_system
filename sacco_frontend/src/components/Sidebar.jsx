import { useState } from 'react';
import { NavLink } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  FaHome, FaUsers, FaDollarSign, FaCheckCircle, FaChartLine,
  FaFileInvoice, FaChartBar, FaCommentDots, FaChevronLeft, FaChevronRight,
} from 'react-icons/fa';

const NAV_ITEMS = [
  { to: '/dashboard', label: 'Dashboard', icon: FaHome },
  { to: '/clients', label: 'Members', icon: FaUsers },
  { to: '/loans', label: 'Loans', icon: FaDollarSign },
  { to: '/approvals', label: 'Approvals', icon: FaCheckCircle },
  { to: '/dividends', label: 'Dividends', icon: FaChartLine },
  { to: '/expenses', label: 'Expenses', icon: FaFileInvoice },
  { to: '/reports', label: 'Reports', icon: FaChartBar },
  { to: '/communication', label: 'Communication', icon: FaCommentDots },
];

export default function Sidebar() {
  const [collapsed, setCollapsed] = useState(false);

  return (
    <motion.aside
      animate={{ width: collapsed ? 76 : 240 }}
      transition={{ duration: 0.2, ease: 'easeInOut' }}
      className="h-screen sticky top-0 bg-secondary text-slate-300 flex flex-col shrink-0"
    >
      <div className="flex items-center justify-between px-4 h-16 border-b border-white/10">
        <AnimatePresence mode="wait">
          {!collapsed && (
            <motion.span
              key="brand"
              initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
              className="font-bold text-white tracking-wide whitespace-nowrap overflow-hidden"
            >
              IMARA SACCO
            </motion.span>
          )}
        </AnimatePresence>
        <button
          onClick={() => setCollapsed((c) => !c)}
          className="text-slate-400 hover:text-white p-1 rounded-md hover:bg-white/10 transition-colors"
        >
          {collapsed ? <FaChevronRight size={14} /> : <FaChevronLeft size={14} />}
        </button>
      </div>

      <nav className="flex-1 py-4 px-2 space-y-1 overflow-y-auto">
        {NAV_ITEMS.map(({ to, label, icon: Icon }) => (
          <NavLink
            key={to}
            to={to}
            className={({ isActive }) =>
              `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-primary text-white'
                  : 'text-slate-300 hover:bg-white/10 hover:text-white'
              }`
            }
          >
            <Icon size={19} className="shrink-0" />
            <AnimatePresence mode="wait">
              {!collapsed && (
                <motion.span
                  initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                  className="whitespace-nowrap overflow-hidden"
                >
                  {label}
                </motion.span>
              )}
            </AnimatePresence>
          </NavLink>
        ))}
      </nav>
    </motion.aside>
  );
}
