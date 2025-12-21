# Google Login - Frontend Implementation Guide

## What the Backend Does

1. **Receives Google OAuth callback** at `/api/auth/google/callback`
2. **Gets user info from Google** (name, email, avatar)
3. **Creates or finds user** in database
4. **Generates Sanctum token** using `createToken('auth-token')->plainTextToken`
5. **Returns HTML page** that:
   - Stores token in `localStorage` and `sessionStorage`
   - Sets a flag `google_login_success`
   - Redirects to your frontend

## Frontend Implementation

### Option 1: Handle Token from HTML Page (Current Implementation)

The backend returns an HTML page that automatically stores the token. Your frontend just needs to:

#### 1. Check for Token on App Load

```javascript
// In your main app file (App.js, main.js, or index.html)

// Check if user is already logged in
function checkAuth() {
  const token = localStorage.getItem('auth_token');
  
  if (token) {
    // User is logged in
    // Set token in your API client/axios
    setAuthToken(token);
    
    // Fetch user data
    fetchUserData(token);
    
    return true;
  }
  
  return false;
}

// Check on page load
window.addEventListener('DOMContentLoaded', function() {
  checkAuth();
});
```

#### 2. Set Token in API Requests

```javascript
// In your API client (axios, fetch wrapper, etc.)

// Set default authorization header
const token = localStorage.getItem('auth_token');

if (token) {
  // For Axios
  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  
  // OR for Fetch
  // Add to all fetch requests:
  fetch(url, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
}
```

#### 3. Fetch User Data After Login

```javascript
async function fetchUserData(token) {
  try {
    const response = await fetch('http://localhost:8000/api/me', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Store user data
      localStorage.setItem('user', JSON.stringify(data.data));
      
      // Update your app state
      setUser(data.data);
      
      // Redirect to dashboard if needed
      // window.location.href = '/dashboard';
    }
  } catch (error) {
    console.error('Error fetching user data:', error);
    // Clear invalid token
    localStorage.removeItem('auth_token');
  }
}
```

### Option 2: Handle Token from URL (If Using Simple Redirect)

If you want to use the simple redirect method (token in URL), add this:

```javascript
// Check URL for token on page load
function handleGoogleLoginCallback() {
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('token');
  const googleLogin = urlParams.get('google_login');
  
  if (token && googleLogin === 'success') {
    // Store token
    localStorage.setItem('auth_token', token);
    sessionStorage.setItem('auth_token', token);
    
    // Clean up URL
    window.history.replaceState({}, document.title, window.location.pathname);
    
    // Fetch user data
    fetchUserData(token);
    
    // Redirect to dashboard
    // window.location.href = '/dashboard';
  }
}

// Call on page load
window.addEventListener('DOMContentLoaded', handleGoogleLoginCallback);
```

## Complete Frontend Auth Flow

### 1. Auth Service/Utility

```javascript
// auth.js or authService.js

class AuthService {
  // Get token from storage
  getToken() {
    return localStorage.getItem('auth_token');
  }
  
  // Check if user is authenticated
  isAuthenticated() {
    return !!this.getToken();
  }
  
  // Set token
  setToken(token) {
    localStorage.setItem('auth_token', token);
    sessionStorage.setItem('auth_token', token);
  }
  
  // Clear token (logout)
  clearToken() {
    localStorage.removeItem('auth_token');
    sessionStorage.removeItem('auth_token');
    localStorage.removeItem('user');
  }
  
  // Get user data
  getUser() {
    const userStr = localStorage.getItem('user');
    return userStr ? JSON.parse(userStr) : null;
  }
  
  // Set user data
  setUser(user) {
    localStorage.setItem('user', JSON.stringify(user));
  }
  
  // Fetch current user from API
  async fetchCurrentUser() {
    const token = this.getToken();
    if (!token) return null;
    
    try {
      const response = await fetch('http://localhost:8000/api/me', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      const data = await response.json();
      
      if (data.success) {
        this.setUser(data.data);
        return data.data;
      }
      
      return null;
    } catch (error) {
      console.error('Error fetching user:', error);
      this.clearToken();
      return null;
    }
  }
}

export default new AuthService();
```

### 2. API Client Setup

```javascript
// api.js or apiClient.js

import AuthService from './auth';

// Axios example
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json'
  }
});

// Add token to all requests
api.interceptors.request.use(
  (config) => {
    const token = AuthService.getToken();
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Handle 401 (unauthorized) - token expired
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid
      AuthService.clearToken();
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

### 3. React Example (if using React)

```javascript
// App.js or main component

import { useEffect, useState } from 'react';
import AuthService from './services/auth';
import api from './services/api';

function App() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    // Check if user is logged in
    const initAuth = async () => {
      const token = AuthService.getToken();
      
      if (token) {
        // Fetch user data
        const userData = await AuthService.fetchCurrentUser();
        if (userData) {
          setUser(userData);
        }
      }
      
      setLoading(false);
    };
    
    initAuth();
  }, []);
  
  if (loading) {
    return <div>Loading...</div>;
  }
  
  return (
    <div>
      {user ? (
        <Dashboard user={user} />
      ) : (
        <Login />
      )}
    </div>
  );
}
```

### 4. Login Button

```javascript
// Login component or button

function LoginButton() {
  const handleGoogleLogin = () => {
    // Redirect to backend Google OAuth
    window.location.href = 'http://localhost:8000/api/auth/google';
  };
  
  return (
    <button onClick={handleGoogleLogin}>
      Login with Google
    </button>
  );
}
```

### 5. Protected Route Example

```javascript
// ProtectedRoute.js

import { Navigate } from 'react-router-dom';
import AuthService from './services/auth';

function ProtectedRoute({ children }) {
  if (!AuthService.isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }
  
  return children;
}
```

## Summary

**What Backend Does:**
1. Handles Google OAuth callback
2. Creates/finds user
3. Generates token
4. Returns HTML page that stores token

**What Frontend Needs to Do:**
1. ✅ Check for token in localStorage on app load
2. ✅ Set token in API request headers
3. ✅ Fetch user data using `/api/me` endpoint
4. ✅ Handle authentication state
5. ✅ Redirect to login if token is invalid/expired

## Testing

1. Click "Login with Google" → redirects to backend
2. Complete Google login
3. Backend stores token in localStorage via HTML page
4. Frontend detects token and fetches user data
5. User stays logged in

## API Endpoints to Use

- `GET /api/me` - Get current user (requires token)
- All other endpoints require `Authorization: Bearer TOKEN` header

