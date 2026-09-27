import axios from 'axios';

// Base URL for our Laravel backend. Change this if php artisan serve
// is running on a different port, or when deploying later.
const API_BASE_URL = 'http://127.0.0.1:8000/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    // Our Laravel routes need this to return JSON errors instead of
    // redirecting to a web page -- the exact issue we debugged
    // manually in Postman earlier. Baking it in here means every
    // request from the React app gets it automatically.
    'Accept': 'application/json',
  },
});

// Attach the saved token (if any) to every outgoing request.
// This runs before each request is sent.
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('sacco_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// If the backend ever responds with 401 (unauthenticated), clear
// whatever we have stored and send the user back to login.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('sacco_token');
      localStorage.removeItem('sacco_user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
