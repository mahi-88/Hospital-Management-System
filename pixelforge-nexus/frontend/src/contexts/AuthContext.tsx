import React, { createContext, useContext, useReducer, useEffect } from 'react';
import { authService } from '../services/authService';
import { securityService } from '../services/securityService';
import toast from 'react-hot-toast';

export interface User {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  role: 'ADMIN' | 'PROJECT_LEAD' | 'DEVELOPER';
}

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
}

type AuthAction =
  | { type: 'AUTH_START' }
  | { type: 'AUTH_SUCCESS'; payload: User }
  | { type: 'AUTH_FAILURE'; payload: string }
  | { type: 'LOGOUT' }
  | { type: 'CLEAR_ERROR' };

const initialState: AuthState = {
  user: null,
  isAuthenticated: false,
  isLoading: true,
  error: null,
};

const authReducer = (state: AuthState, action: AuthAction): AuthState => {
  switch (action.type) {
    case 'AUTH_START':
      return {
        ...state,
        isLoading: true,
        error: null,
      };
    case 'AUTH_SUCCESS':
      return {
        ...state,
        user: action.payload,
        isAuthenticated: true,
        isLoading: false,
        error: null,
      };
    case 'AUTH_FAILURE':
      return {
        ...state,
        user: null,
        isAuthenticated: false,
        isLoading: false,
        error: action.payload,
      };
    case 'LOGOUT':
      return {
        ...state,
        user: null,
        isAuthenticated: false,
        isLoading: false,
        error: null,
      };
    case 'CLEAR_ERROR':
      return {
        ...state,
        error: null,
      };
    default:
      return state;
  }
};

interface AuthContextType extends AuthState {
  login: (email: string, password: string, mfaToken?: string) => Promise<void>;
  logout: () => Promise<void>;
  clearError: () => void;
  hasRole: (roles: string | string[]) => boolean;
  hasPermission: (permission: string) => boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: React.ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [state, dispatch] = useReducer(authReducer, initialState);

  // Check for existing session on mount
  useEffect(() => {
    const initializeAuth = async () => {
      try {
        dispatch({ type: 'AUTH_START' });
        
        const token = authService.getToken();
        if (!token) {
          dispatch({ type: 'AUTH_FAILURE', payload: 'No token found' });
          return;
        }

        // Validate token and get user info
        const user = await authService.getCurrentUser();
        dispatch({ type: 'AUTH_SUCCESS', payload: user });
        
        // Log security event
        securityService.logSecurityEvent({
          eventType: 'SESSION_RESTORED',
          severity: 'INFO',
          description: 'User session restored from token',
        });
      } catch (error) {
        console.error('Auth initialization failed:', error);
        authService.removeToken();
        dispatch({ type: 'AUTH_FAILURE', payload: 'Session expired' });
      }
    };

    initializeAuth();
  }, []);

  // Set up token refresh interval
  useEffect(() => {
    if (state.isAuthenticated) {
      const refreshInterval = setInterval(async () => {
        try {
          await authService.refreshToken();
        } catch (error) {
          console.error('Token refresh failed:', error);
          logout();
        }
      }, 14 * 60 * 1000); // Refresh every 14 minutes

      return () => clearInterval(refreshInterval);
    }
  }, [state.isAuthenticated]);

  const login = async (email: string, password: string, mfaToken?: string) => {
    try {
      dispatch({ type: 'AUTH_START' });
      
      const response = await authService.login(email, password, mfaToken);
      
      // Store tokens securely
      authService.setToken(response.accessToken);
      authService.setRefreshToken(response.refreshToken);
      
      dispatch({ type: 'AUTH_SUCCESS', payload: response.user });
      
      // Log successful login
      securityService.logSecurityEvent({
        eventType: 'LOGIN_SUCCESS',
        severity: 'INFO',
        description: 'User logged in successfully',
      });
      
      toast.success('Login successful!');
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Login failed';
      dispatch({ type: 'AUTH_FAILURE', payload: errorMessage });
      
      // Log failed login attempt
      securityService.logSecurityEvent({
        eventType: 'LOGIN_FAILED',
        severity: 'WARNING',
        description: `Login failed: ${errorMessage}`,
        metadata: { email },
      });
      
      toast.error(errorMessage);
      throw error;
    }
  };

  const logout = async () => {
    try {
      await authService.logout();
      
      // Log logout
      securityService.logSecurityEvent({
        eventType: 'LOGOUT',
        severity: 'INFO',
        description: 'User logged out',
      });
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Clear tokens and state regardless of API call success
      authService.removeToken();
      authService.removeRefreshToken();
      dispatch({ type: 'LOGOUT' });
      toast.success('Logged out successfully');
    }
  };

  const clearError = () => {
    dispatch({ type: 'CLEAR_ERROR' });
  };

  const hasRole = (roles: string | string[]): boolean => {
    if (!state.user) return false;
    
    const roleArray = Array.isArray(roles) ? roles : [roles];
    return roleArray.includes(state.user.role);
  };

  const hasPermission = (permission: string): boolean => {
    if (!state.user) return false;
    
    // Define role-based permissions
    const permissions: Record<string, string[]> = {
      ADMIN: [
        'users.create',
        'users.read',
        'users.update',
        'users.delete',
        'projects.create',
        'projects.read',
        'projects.update',
        'projects.delete',
        'documents.upload',
        'documents.read',
        'documents.delete',
        'audit.read',
        'system.configure',
      ],
      PROJECT_LEAD: [
        'projects.read',
        'projects.update',
        'projects.assign',
        'documents.upload',
        'documents.read',
        'team.manage',
      ],
      DEVELOPER: [
        'projects.read',
        'documents.read',
        'profile.update',
      ],
    };

    const userPermissions = permissions[state.user.role] || [];
    return userPermissions.includes(permission);
  };

  const value: AuthContextType = {
    ...state,
    login,
    logout,
    clearError,
    hasRole,
    hasPermission,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};
