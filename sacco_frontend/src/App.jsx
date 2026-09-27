import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';
import ClientsPage from './pages/ClientsPage';
import NewClientPage from './pages/NewClientPage';
import ClientDetailPage from './pages/ClientDetailPage';
import LoanApplicationPage from './pages/LoanApplicationPage';
import LoansPage from './pages/LoansPage';
import LoanDetailPage from './pages/LoanDetailPage';
import ApprovalsPage from './pages/ApprovalsPage';
import ReportsPage from './pages/ReportsPage';
import DividendsPage from './pages/DividendsPage';
import ExpensesPage from './pages/ExpensesPage';
import CommunicationPage from './pages/CommunicationPage';

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/dashboard" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
          <Route path="/clients" element={<ProtectedRoute><ClientsPage /></ProtectedRoute>} />
          <Route path="/clients/new" element={<ProtectedRoute><NewClientPage /></ProtectedRoute>} />
          <Route path="/clients/:id" element={<ProtectedRoute><ClientDetailPage /></ProtectedRoute>} />
          <Route path="/clients/:clientId/apply-loan" element={<ProtectedRoute><LoanApplicationPage /></ProtectedRoute>} />
          <Route path="/loans" element={<ProtectedRoute><LoansPage /></ProtectedRoute>} />
          <Route path="/loans/:id" element={<ProtectedRoute><LoanDetailPage /></ProtectedRoute>} />
          <Route path="/approvals" element={<ProtectedRoute><ApprovalsPage /></ProtectedRoute>} />
          <Route path="/reports" element={<ProtectedRoute><ReportsPage /></ProtectedRoute>} />
          <Route path="/dividends" element={<ProtectedRoute><DividendsPage /></ProtectedRoute>} />
          <Route path="/expenses" element={<ProtectedRoute><ExpensesPage /></ProtectedRoute>} />
          <Route path="/communication" element={<ProtectedRoute><CommunicationPage /></ProtectedRoute>} />
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
