import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useSecurityContext } from '../contexts/SecurityContext';
import toast from 'react-hot-toast';

const Login: React.FC = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [mfaToken, setMfaToken] = useState('');
  const [showMFA, setShowMFA] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const { login, isAuthenticated, clearError } = useAuth();
  const { detectXSSAttempt } = useSecurityContext();
  const navigate = useNavigate();
  const location = useLocation();

  const from = location.state?.from?.pathname || '/dashboard';

  useEffect(() => {
    if (isAuthenticated) {
      navigate(from, { replace: true });
    }
  }, [isAuthenticated, navigate, from]);

  useEffect(() => {
    clearError();
  }, [clearError]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    // Security check for XSS attempts
    if (detectXSSAttempt(email) || detectXSSAttempt(password)) {
      toast.error('Invalid input detected');
      return;
    }

    setIsLoading(true);

    try {
      await login(email, password, mfaToken);
      toast.success('Login successful!');
      navigate(from, { replace: true });
    } catch (error: any) {
      const errorMessage = error.response?.data?.error || 'Login failed';
      
      // Check if MFA is required
      if (errorMessage.includes('MFA token required')) {
        setShowMFA(true);
        toast.error('Please enter your MFA token');
      } else {
        toast.error(errorMessage);
        setShowMFA(false);
        setMfaToken('');
      }
    } finally {
      setIsLoading(false);
    }
  };

  const quickLoginButtons = [
    { role: 'Admin', email: 'admin@pixelforge.com', password: 'Admin123!@#' },
    { role: 'Project Lead', email: 'lead@pixelforge.com', password: 'Lead123!@#' },
    { role: 'Developer', email: 'dev@pixelforge.com', password: 'Dev123!@#' },
  ];

  const handleQuickLogin = (credentials: { email: string; password: string }) => {
    setEmail(credentials.email);
    setPassword(credentials.password);
    setShowMFA(false);
    setMfaToken('');
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        {/* Header */}
        <div>
          <div className="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100">
            <span className="text-2xl">🎮</span>
          </div>
          <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
            PixelForge Nexus
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            Secure Game Development Management
          </p>
        </div>

        {/* Login Form */}
        <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
          <div className="rounded-md shadow-sm -space-y-px">
            <div>
              <label htmlFor="email" className="sr-only">
                Email address
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                placeholder="Email address"
              />
            </div>
            <div className="relative">
              <label htmlFor="password" className="sr-only">
                Password
              </label>
              <input
                id="password"
                name="password"
                type={showPassword ? 'text' : 'password'}
                autoComplete="current-password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className={`appearance-none rounded-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 ${
                  showMFA ? '' : 'rounded-b-md'
                } focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm`}
                placeholder="Password"
              />
              <button
                type="button"
                className="absolute inset-y-0 right-0 pr-3 flex items-center"
                onClick={() => setShowPassword(!showPassword)}
              >
                <span className="text-gray-400 hover:text-gray-600">
                  {showPassword ? '🙈' : '👁️'}
                </span>
              </button>
            </div>
            {showMFA && (
              <div>
                <label htmlFor="mfaToken" className="sr-only">
                  MFA Token
                </label>
                <input
                  id="mfaToken"
                  name="mfaToken"
                  type="text"
                  maxLength={6}
                  value={mfaToken}
                  onChange={(e) => setMfaToken(e.target.value.replace(/\D/g, ''))}
                  className="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                  placeholder="6-digit MFA code"
                />
              </div>
            )}
          </div>

          <div>
            <button
              type="submit"
              disabled={isLoading}
              className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? (
                <div className="flex items-center">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                  Signing in...
                </div>
              ) : (
                'Sign in'
              )}
            </button>
          </div>
        </form>

        {/* Quick Login Buttons */}
        <div className="mt-6">
          <div className="relative">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-gray-300" />
            </div>
            <div className="relative flex justify-center text-sm">
              <span className="px-2 bg-gray-50 text-gray-500">Quick Login (Demo)</span>
            </div>
          </div>

          <div className="mt-6 grid grid-cols-1 gap-3">
            {quickLoginButtons.map((button) => (
              <button
                key={button.role}
                type="button"
                onClick={() => handleQuickLogin(button)}
                className="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              >
                Login as {button.role}
              </button>
            ))}
          </div>
        </div>

        {/* Security Notice */}
        <div className="mt-6 text-center">
          <p className="text-xs text-gray-500">
            🔒 This system uses enterprise-grade security including MFA, audit logging, and encryption.
          </p>
        </div>
      </div>
    </div>
  );
};

export default Login;
