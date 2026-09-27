import { motion } from 'framer-motion';
import { FaArrowUp } from 'react-icons/fa';

/**
 * label: small uppercase subtitle
 * value: the big number/currency string
 * icon: a react-icons component
 * colorClass: tailwind bg class for the icon circle, e.g. 'bg-blue-100 text-primary'
 */
export default function StatCard({ label, value, icon: Icon, colorClass = 'bg-blue-100 text-primary' }) {
  return (
    <motion.div
      whileHover={{ y: -3 }}
      transition={{ duration: 0.15 }}
      className="bg-card border border-light-border rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow"
    >
      <div className="flex items-start justify-between mb-3">
        <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${colorClass}`}>
          <Icon size={16} />
        </div>
        <span className="flex items-center gap-1 text-xs font-semibold text-success">
          <FaArrowUp size={10} />
        </span>
      </div>
      <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">{label}</p>
      <p className="text-2xl font-bold text-secondary">{value}</p>
    </motion.div>
  );
}
