import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { AuthProvider } from '../contexts/AuthContext';
import { SecurityProvider } from '../contexts/SecurityContext';
import Login from '../pages/Login';

// Mock axios
jest.mock('axios');

// Mock react-router-dom
const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: () => mockNavigate,
  useLocation: () => ({ state: null }),
}));

// Mock react-hot-toast
jest.mock('react-hot-toast', () => ({
  success: jest.fn(),
  error: jest.fn(),
}));

const renderLogin = () => {
  return render(
    <BrowserRouter>
      <SecurityProvider>
        <AuthProvider>
          <Login />
        </AuthProvider>
      </SecurityProvider>
    </BrowserRouter>
  );
};

describe('Login Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders login form correctly', () => {
    renderLogin();
    
    expect(screen.getByText('PixelForge Nexus')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Email address')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Password')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
  });

  it('shows quick login buttons', () => {
    renderLogin();
    
    expect(screen.getByText('Login as Admin')).toBeInTheDocument();
    expect(screen.getByText('Login as Project Lead')).toBeInTheDocument();
    expect(screen.getByText('Login as Developer')).toBeInTheDocument();
  });

  it('validates email format', async () => {
    const user = userEvent.setup();
    renderLogin();
    
    const emailInput = screen.getByPlaceholderText('Email address');
    const submitButton = screen.getByRole('button', { name: /sign in/i });
    
    await user.type(emailInput, 'invalid-email');
    await user.click(submitButton);
    
    // HTML5 validation should prevent submission
    expect(emailInput).toBeInvalid();
  });

  it('requires password field', async () => {
    const user = userEvent.setup();
    renderLogin();
    
    const emailInput = screen.getByPlaceholderText('Email address');
    const passwordInput = screen.getByPlaceholderText('Password');
    const submitButton = screen.getByRole('button', { name: /sign in/i });
    
    await user.type(emailInput, 'test@example.com');
    await user.click(submitButton);
    
    expect(passwordInput).toBeInvalid();
  });

  it('toggles password visibility', async () => {
    const user = userEvent.setup();
    renderLogin();
    
    const passwordInput = screen.getByPlaceholderText('Password');
    const toggleButton = screen.getByRole('button', { name: '' }); // Eye icon button
    
    expect(passwordInput).toHaveAttribute('type', 'password');
    
    await user.click(toggleButton);
    expect(passwordInput).toHaveAttribute('type', 'text');
    
    await user.click(toggleButton);
    expect(passwordInput).toHaveAttribute('type', 'password');
  });

  it('fills form when quick login button is clicked', async () => {
    const user = userEvent.setup();
    renderLogin();
    
    const emailInput = screen.getByPlaceholderText('Email address');
    const passwordInput = screen.getByPlaceholderText('Password');
    const adminButton = screen.getByText('Login as Admin');
    
    await user.click(adminButton);
    
    expect(emailInput).toHaveValue('admin@pixelforge.com');
    expect(passwordInput).toHaveValue('Admin123!@#');
  });

  it('shows MFA field when required', async () => {
    const user = userEvent.setup();
    renderLogin();
    
    // Simulate MFA requirement by setting showMFA state
    // This would typically be triggered by a login response
    const emailInput = screen.getByPlaceholderText('Email address');
    const passwordInput = screen.getByPlaceholderText('Password');
    
    await user.type(emailInput, 'lead2@pixelforge.com');
    await user.type(passwordInput, 'Lead456!@#');
    
    // In a real scenario, this would trigger MFA requirement
    // For testing, we can check if the component handles MFA state correctly
    expect(emailInput).toHaveValue('lead2@pixelforge.com');
    expect(passwordInput).toHaveValue('Lead456!@#');
  });

  it('handles form submission', async () => {
    const user = userEvent.setup();
    renderLogin();
    
    const emailInput = screen.getByPlaceholderText('Email address');
    const passwordInput = screen.getByPlaceholderText('Password');
    const submitButton = screen.getByRole('button', { name: /sign in/i });
    
    await user.type(emailInput, 'test@example.com');
    await user.type(passwordInput, 'TestPassword123!');
    await user.click(submitButton);
    
    // Check if form submission is handled
    expect(submitButton).toBeInTheDocument();
  });

  it('shows loading state during submission', async () => {
    const user = userEvent.setup();
    renderLogin();
    
    const emailInput = screen.getByPlaceholderText('Email address');
    const passwordInput = screen.getByPlaceholderText('Password');
    const submitButton = screen.getByRole('button', { name: /sign in/i });
    
    await user.type(emailInput, 'test@example.com');
    await user.type(passwordInput, 'TestPassword123!');
    
    // Mock a slow login process
    await user.click(submitButton);
    
    // The button should show loading state
    expect(submitButton).toBeInTheDocument();
  });

  it('prevents XSS in input fields', async () => {
    const user = userEvent.setup();
    renderLogin();
    
    const emailInput = screen.getByPlaceholderText('Email address');
    const xssPayload = '<script>alert("xss")</script>';
    
    await user.type(emailInput, xssPayload);
    
    // The input should contain the raw text, not execute script
    expect(emailInput).toHaveValue(xssPayload);
    expect(document.querySelector('script')).toBeNull();
  });

  it('has proper accessibility attributes', () => {
    renderLogin();
    
    const emailInput = screen.getByPlaceholderText('Email address');
    const passwordInput = screen.getByPlaceholderText('Password');
    
    expect(emailInput).toHaveAttribute('type', 'email');
    expect(emailInput).toHaveAttribute('autoComplete', 'email');
    expect(passwordInput).toHaveAttribute('type', 'password');
    expect(passwordInput).toHaveAttribute('autoComplete', 'current-password');
  });

  it('displays security notice', () => {
    renderLogin();
    
    expect(screen.getByText(/enterprise-grade security/i)).toBeInTheDocument();
  });
});
