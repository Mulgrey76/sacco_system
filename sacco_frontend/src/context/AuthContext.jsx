import { createContext, useContext, useState } from 'react';
import api from '../services/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const stored = localStorage.getItem('sacco_user');
    return stored ? JSON.parse(stored) : null;
  });

  async function login(orgCode, identifier, password) {
    const response = await api.post('/auth/login', {
      org_code: orgCode,
      identifier,
      password,
    });

    const { access_token, user: loggedInUser, must_change_password } = response.data;

    localStorage.setItem('sacco_token', access_token);
    localStorage.setItem('sacco_user', JSON.stringify(loggedInUser));
    setUser(loggedInUser);

    return { mustChangePassword: must_change_password };
  }

  /**
   * SECURITY: now also revokes the token server-side, so a copied
   * token can't keep working after logout. Clearing localStorage
   * alone (what we did before) only hid the token locally -- it
   * stayed valid on the server indefinitely.
   */
  async function logout() {
    try {
      await api.post('/auth/logout');
    } catch {
      // Even if the revoke call fails (expired token, server down),
      // still clear local state so the user is logged out client-side.
    } finally {
      localStorage.removeItem('sacco_token');
      localStorage.removeItem('sacco_user');
      setUser(null);
    }
  }

  const value = {
    user,
    login,
    logout,
    isAuthenticated: !!user,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
