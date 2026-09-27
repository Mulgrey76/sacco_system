import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { FaHome, FaLock, FaUser, FaBriefcase } from 'react-icons/fa';
import { useAuth } from '../context/AuthContext';

export default function LoginPage() {
  const [orgCode, setOrgCode] = useState('');
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const { login } = useAuth();
  const navigate = useNavigate();

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setLoading(true);

    try {
      await login(orgCode, identifier, password);
      navigate('/dashboard');
    } catch (err) {
      const backendMessage = err.response?.data?.message;
      setError(backendMessage || 'Could not reach the server. Is php artisan serve running?');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen bg-secondary flex items-center justify-center px-4">
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: 'easeOut' }}
        className="bg-card w-full max-w-sm rounded-2xl shadow-2xl p-8"
      >
        <div className="flex items-center gap-2 mb-1">
          <div className="w-9 h-9 rounded-lg bg-primary flex items-center justify-center text-white">
            <FaHome size={16} />
          </div>
          <h1 className="text-xl font-bold text-secondary tracking-tight">IMARA SACCO</h1>
        </div>
        <p className="text-sm text-slate-500 mb-6">Sign in to the back-office portal</p>

        <form onSubmit={handleSubmit} className="space-y-4">
          <FloatingInput
            label="Organization Code" icon={FaBriefcase}
            value={orgCode} onChange={setOrgCode} placeholder="e.g. IMARA"
          />
          <FloatingInput
            label="Username or Email" icon={FaUser}
            value={identifier} onChange={setIdentifier} placeholder="e.g. admin1"
          />
          <FloatingInput
            label="Password" icon={FaLock} type="password"
            value={password} onChange={setPassword} placeholder="••••••••"
          />

          {error && (
            <motion.div
              initial={{ opacity: 0 }} animate={{ opacity: 1 }}
              className="bg-red-50 text-danger text-sm px-3 py-2 rounded-lg"
            >
              {error}
            </motion.div>
          )}

          <motion.button
            whileTap={{ scale: 0.98 }}
            type="submit"
            disabled={loading}
            className="w-full bg-primary hover:bg-blue-700 disabled:bg-slate-400 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            {loading && (
              <span className="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
            )}
            {loading ? 'Signing in...' : 'Log In'}
          </motion.button>
        </form>
      </motion.div>
    </div>
  );
}

function FloatingInput({ label, icon: Icon, value, onChange, placeholder, type = 'text' }) {
  return (
    <div>
      <label className="block text-sm font-semibold text-slate-700 mb-1">{label}</label>
      <div className="relative">
        <Icon size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          type={type}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          required
          className="w-full pl-9 pr-3 py-2 border border-light-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary text-sm"
        />
      </div>
    </div>
  );
}
