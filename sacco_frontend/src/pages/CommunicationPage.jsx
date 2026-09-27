import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  FaUser, FaUsers, FaSms, FaEnvelope, FaWhatsapp, FaBell,
  FaPaperclip, FaClock, FaFlag, FaPaperPlane, FaCheckCircle, FaTimesCircle,
} from 'react-icons/fa';
import api from '../services/api';
import AppLayout from '../components/AppLayout';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';

const CHANNELS = [
  { value: 'sms', label: 'SMS', icon: FaSms, available: true },
  { value: 'email', label: 'Email', icon: FaEnvelope, available: true },
  { value: 'whatsapp', label: 'WhatsApp', icon: FaWhatsapp, available: false },
  { value: 'push', label: 'Push Notification', icon: FaBell, available: false },
];

const PRIORITIES = ['Normal', 'High', 'Urgent'];
const MAX_LENGTH = 500;

export default function CommunicationPage() {
  const [clients, setClients] = useState([]);
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);

  const [recipientType, setRecipientType] = useState('single');
  const [clientId, setClientId] = useState('');
  const [channel, setChannel] = useState('sms');
  const [messageBody, setMessageBody] = useState('');
  const [priority, setPriority] = useState('Normal');
  const [attachment, setAttachment] = useState(null);
  const [scheduleEnabled, setScheduleEnabled] = useState(false);
  const [scheduleAt, setScheduleAt] = useState('');

  const [sending, setSending] = useState(false);
  const [alert, setAlert] = useState(null);

  const ORG_ID = 1;

  async function loadLogs() {
    const res = await api.get(`/communication/logs?org_id=${ORG_ID}`);
    setLogs(res.data);
  }

  useEffect(() => {
    let isMounted = true;
    Promise.all([api.get(`/clients?org_id=${ORG_ID}`), loadLogs()])
      .then(([clientsRes]) => { if (isMounted) setClients(clientsRes.data.data); })
      .finally(() => { if (isMounted) setLoading(false); });
    return () => { isMounted = false; };
  }, []);

  const blockedReason = attachment
    ? 'Attachments are not yet supported by the backend — remove the file to send.'
    : scheduleEnabled
    ? 'Scheduled sending is not yet supported by the backend — disable it to send immediately.'
    : !CHANNELS.find((c) => c.value === channel)?.available
    ? 'This channel is not yet supported by the backend — choose SMS or Email.'
    : null;

  async function handleSend(event) {
    event.preventDefault();
    setAlert(null);
    setSending(true);

    const finalBody = priority === 'Normal' ? messageBody : `[${priority.toUpperCase()}] ${messageBody}`;
    const targets = recipientType === 'all' ? clients.map((c) => c.id) : [Number(clientId)];

    try {
      for (const id of targets) {
        await api.post('/communication/send', {
          org_id: ORG_ID,
          client_id: id,
          channel,
          message_body: finalBody,
        });
      }
      setAlert({ type: 'success', text: `Message sent to ${targets.length} recipient(s).` });
      setMessageBody('');
      await loadLogs();
    } catch (err) {
      setAlert({ type: 'error', text: err.response?.data?.message || 'Could not send this message.' });
      await loadLogs();
    } finally {
      setSending(false);
    }
  }

  const logColumns = [
    { key: 'recipient', label: 'Recipient' },
    {
      key: 'channel', label: 'Channel', sortable: false,
      render: (row) => <span className="uppercase text-xs text-slate-500">{row.channel}</span>,
    },
    {
      key: 'message_body', label: 'Message Preview', sortable: false,
      render: (row) => <span className="line-clamp-1 max-w-xs block">{row.message_body}</span>,
    },
    {
      key: 'status', label: 'Status',
      render: (row) => (
        <StatusBadge tone={row.status === 'sent' ? 'success' : 'danger'}>{row.status}</StatusBadge>
      ),
    },
    {
      key: 'sent_at', label: 'Time Sent',
      render: (row) => new Date(row.sent_at).toLocaleString('en-KE'),
    },
  ];

  return (
    <AppLayout title="Communication">
      <h1 className="text-2xl font-bold text-secondary mb-6">Communication Center</h1>

      {loading ? (
        <p className="text-slate-500">Loading...</p>
      ) : (
        <>
          <form onSubmit={handleSend} className="bg-card border border-light-border rounded-2xl p-6 mb-8 space-y-5 max-w-2xl shadow-sm">
            <div>
              <label className="block text-sm font-semibold text-secondary mb-2">Recipient</label>
              <div className="flex gap-2">
                <PillOption active={recipientType === 'single'} onClick={() => setRecipientType('single')} icon={FaUser} label="Single Member" />
                <PillOption active={recipientType === 'all'} onClick={() => setRecipientType('all')} icon={FaUsers} label="All Members" />
              </div>
              {recipientType === 'single' && (
                <select
                  value={clientId} onChange={(e) => setClientId(e.target.value)} required
                  className="mt-3 w-full px-3 py-2 border border-light-border rounded-lg text-sm"
                >
                  <option value="" disabled>Select a member...</option>
                  {clients.map((c) => (
                    <option key={c.id} value={c.id}>{c.first_name} {c.last_name}</option>
                  ))}
                </select>
              )}
              {recipientType === 'all' && (
                <p className="text-xs text-slate-400 mt-2">Will send individually to all {clients.length} members.</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-semibold text-secondary mb-2">Channel</label>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                {CHANNELS.map((c) => (
                  <button
                    key={c.value}
                    type="button"
                    disabled={!c.available}
                    onClick={() => setChannel(c.value)}
                    className={`relative flex flex-col items-center gap-1.5 py-3 rounded-xl border text-xs font-medium transition-colors ${
                      channel === c.value
                        ? 'border-primary bg-blue-50 text-primary'
                        : c.available
                        ? 'border-light-border text-slate-600 hover:bg-slate-50'
                        : 'border-light-border text-slate-300 cursor-not-allowed'
                    }`}
                  >
                    <c.icon size={16} />
                    {c.label}
                    {!c.available && (
                      <span className="absolute -top-2 -right-1 bg-slate-200 text-slate-500 text-[9px] px-1.5 py-0.5 rounded-full">
                        Soon
                      </span>
                    )}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-sm font-semibold text-secondary mb-2 flex items-center gap-1.5">
                <FaFlag size={11} /> Priority
              </label>
              <div className="flex gap-2">
                {PRIORITIES.map((p) => (
                  <PillOption key={p} active={priority === p} onClick={() => setPriority(p)} label={p} />
                ))}
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between mb-2">
                <label className="text-sm font-semibold text-secondary">Message</label>
                <span className={`text-xs ${messageBody.length > MAX_LENGTH ? 'text-danger' : 'text-slate-400'}`}>
                  {messageBody.length}/{MAX_LENGTH}
                </span>
              </div>
              <textarea
                value={messageBody}
                onChange={(e) => setMessageBody(e.target.value.slice(0, MAX_LENGTH))}
                rows={4} required
                className="w-full px-3 py-2 border border-light-border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="flex items-center gap-1.5 text-sm font-semibold text-secondary mb-2">
                  <FaPaperclip size={12} /> Attachment
                </label>
                <input
                  type="file"
                  onChange={(e) => setAttachment(e.target.files?.[0] ?? null)}
                  className="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-xs file:font-medium hover:file:bg-slate-200"
                />
              </div>
              <div>
                <label className="flex items-center gap-1.5 text-sm font-semibold text-secondary mb-2">
                  <FaClock size={12} /> Schedule Send
                </label>
                <div className="flex items-center gap-2">
                  <input
                    type="checkbox" checked={scheduleEnabled}
                    onChange={(e) => setScheduleEnabled(e.target.checked)}
                    className="w-4 h-4"
                  />
                  <input
                    type="datetime-local" value={scheduleAt}
                    onChange={(e) => setScheduleAt(e.target.value)}
                    disabled={!scheduleEnabled}
                    className="flex-1 px-2 py-1.5 border border-light-border rounded-lg text-xs disabled:bg-slate-50"
                  />
                </div>
              </div>
            </div>

            {blockedReason && (
              <p className="text-xs text-warning bg-amber-50 px-3 py-2 rounded-lg">{blockedReason}</p>
            )}

            <AnimatePresence>
              {alert && (
                <motion.div
                  initial={{ opacity: 0, y: -6 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }}
                  className={`flex items-center gap-2 text-sm px-3 py-2 rounded-lg ${
                    alert.type === 'success' ? 'bg-green-50 text-success' : 'bg-red-50 text-danger'
                  }`}
                >
                  {alert.type === 'success' ? <FaCheckCircle size={14} /> : <FaTimesCircle size={14} />}
                  {alert.text}
                </motion.div>
              )}
            </AnimatePresence>

            <motion.button
              whileTap={{ scale: 0.98 }}
              type="submit"
              disabled={sending || !!blockedReason}
              className="flex items-center justify-center gap-2 bg-primary hover:bg-blue-700 disabled:bg-slate-300 text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors"
            >
              {sending ? (
                <span className="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
              ) : (
                <FaPaperPlane size={13} />
              )}
              {sending ? 'Sending...' : 'Send Message'}
            </motion.button>
          </form>

          <h2 className="text-sm font-semibold text-secondary uppercase tracking-wide mb-3">
            Recent Communication Log
          </h2>
          <DataTable
            columns={logColumns}
            data={logs}
            searchKeys={['recipient', 'message_body']}
            exportFilename="communication_log"
          />
        </>
      )}
    </AppLayout>
  );
}

function PillOption({ active, onClick, icon: Icon, label }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${
        active ? 'bg-primary text-white border-primary' : 'border-light-border text-slate-600 hover:bg-slate-50'
      }`}
    >
      {Icon && <Icon size={11} />}
      {label}
    </button>
  );
}
