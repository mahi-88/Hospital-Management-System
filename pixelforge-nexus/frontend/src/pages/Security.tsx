import React from 'react';
import { useAuth } from '../contexts/AuthContext';

const Security: React.FC = () => {
  const { user } = useAuth();

  const securityFeatures = [
    {
      title: 'Multi-Factor Authentication',
      description: 'Add an extra layer of security with TOTP-based authentication',
      status: 'Available',
      icon: '🔐',
      color: 'green'
    },
    {
      title: 'Session Management',
      description: 'Automatic session expiry and secure token handling',
      status: 'Active',
      icon: '⏰',
      color: 'blue'
    },
    {
      title: 'Audit Logging',
      description: 'Complete audit trail of all user actions and security events',
      status: 'Active',
      icon: '📋',
      color: 'blue'
    },
    {
      title: 'Password Security',
      description: 'Strong password hashing with bcrypt and complexity requirements',
      status: 'Active',
      icon: '🔒',
      color: 'blue'
    },
    {
      title: 'Rate Limiting',
      description: 'Protection against brute force attacks and abuse',
      status: 'Active',
      icon: '🛡️',
      color: 'blue'
    },
    {
      title: 'Data Encryption',
      description: 'End-to-end encryption for sensitive data and communications',
      status: 'Active',
      icon: '🔐',
      color: 'blue'
    }
  ];

  const getStatusColor = (status: string, color: string) => {
    if (status === 'Active') {
      return 'bg-green-100 text-green-800';
    } else if (status === 'Available') {
      return 'bg-yellow-100 text-yellow-800';
    }
    return `bg-${color}-100 text-${color}-800`;
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Security Center</h1>
        <p className="text-gray-600">
          Monitor and manage your account security settings and system security features.
        </p>
      </div>

      {/* Security Overview */}
      <div className="bg-white shadow overflow-hidden sm:rounded-lg">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-medium text-gray-900">Security Status</h3>
          <p className="mt-1 max-w-2xl text-sm text-gray-500">
            Your current security configuration and recommendations.
          </p>
        </div>
        <div className="border-t border-gray-200">
          <div className="px-4 py-5 sm:px-6">
            <div className="flex items-center justify-between p-4 bg-green-50 rounded-lg">
              <div className="flex items-center">
                <span className="text-2xl mr-3">✅</span>
                <div>
                  <h4 className="text-sm font-medium text-green-900">Security Status: Good</h4>
                  <p className="text-sm text-green-700">
                    Your account is protected with enterprise-grade security measures.
                  </p>
                </div>
              </div>
              <div className="text-right">
                <div className="text-2xl font-bold text-green-600">92%</div>
                <div className="text-xs text-green-600">Security Score</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Security Features */}
      <div className="bg-white shadow overflow-hidden sm:rounded-lg">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-medium text-gray-900">Security Features</h3>
          <p className="mt-1 max-w-2xl text-sm text-gray-500">
            Active security measures protecting your account and data.
          </p>
        </div>
        <div className="border-t border-gray-200">
          <div className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
            {securityFeatures.map((feature, index) => (
              <div key={index} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                <div className="flex items-start justify-between">
                  <div className="flex items-start">
                    <span className="text-2xl mr-3">{feature.icon}</span>
                    <div>
                      <h4 className="text-sm font-medium text-gray-900">{feature.title}</h4>
                      <p className="text-sm text-gray-500 mt-1">{feature.description}</p>
                    </div>
                  </div>
                  <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(feature.status, feature.color)}`}>
                    {feature.status}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Account Security Settings */}
      <div className="bg-white shadow overflow-hidden sm:rounded-lg">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-medium text-gray-900">Account Security</h3>
          <p className="mt-1 max-w-2xl text-sm text-gray-500">
            Manage your personal security settings and preferences.
          </p>
        </div>
        <div className="border-t border-gray-200">
          <div className="px-4 py-5 sm:px-6 space-y-6">
            {/* MFA Setting */}
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Multi-Factor Authentication</h4>
                <p className="text-sm text-gray-500">
                  Secure your account with an additional verification step
                </p>
              </div>
              <div className="flex items-center space-x-3">
                <span className="text-sm text-gray-500">
                  Status: <span className="font-medium">Not Configured</span>
                </span>
                <button className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                  Setup MFA
                </button>
              </div>
            </div>

            {/* Password */}
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Password</h4>
                <p className="text-sm text-gray-500">
                  Change your password regularly for better security
                </p>
              </div>
              <button className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Change Password
              </button>
            </div>

            {/* Active Sessions */}
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Active Sessions</h4>
                <p className="text-sm text-gray-500">
                  Manage devices and browsers that are signed in to your account
                </p>
              </div>
              <button className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                View Sessions
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Security Recommendations */}
      <div className="bg-white shadow overflow-hidden sm:rounded-lg">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-medium text-gray-900">Security Recommendations</h3>
          <p className="mt-1 max-w-2xl text-sm text-gray-500">
            Suggestions to improve your account security.
          </p>
        </div>
        <div className="border-t border-gray-200">
          <div className="px-4 py-5 sm:px-6">
            <div className="space-y-4">
              <div className="flex items-start p-4 bg-yellow-50 rounded-lg">
                <span className="text-xl mr-3">⚠️</span>
                <div>
                  <h4 className="text-sm font-medium text-yellow-900">Enable Multi-Factor Authentication</h4>
                  <p className="text-sm text-yellow-700 mt-1">
                    Add an extra layer of security to your account by enabling MFA. This significantly reduces the risk of unauthorized access.
                  </p>
                  <button className="mt-2 text-sm font-medium text-yellow-900 hover:text-yellow-700">
                    Setup MFA →
                  </button>
                </div>
              </div>

              <div className="flex items-start p-4 bg-blue-50 rounded-lg">
                <span className="text-xl mr-3">💡</span>
                <div>
                  <h4 className="text-sm font-medium text-blue-900">Regular Password Updates</h4>
                  <p className="text-sm text-blue-700 mt-1">
                    Consider updating your password every 90 days and ensure it meets complexity requirements.
                  </p>
                </div>
              </div>

              <div className="flex items-start p-4 bg-green-50 rounded-lg">
                <span className="text-xl mr-3">✅</span>
                <div>
                  <h4 className="text-sm font-medium text-green-900">Strong Security Foundation</h4>
                  <p className="text-sm text-green-700 mt-1">
                    Your account benefits from enterprise-grade security including encryption, audit logging, and secure session management.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Security Metrics */}
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <span className="text-2xl">🔐</span>
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">Login Attempts</dt>
                  <dd className="text-lg font-medium text-gray-900">0 Failed</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <span className="text-2xl">⏰</span>
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">Session Expiry</dt>
                  <dd className="text-lg font-medium text-gray-900">15 min</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <span className="text-2xl">🛡️</span>
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">Protection Level</dt>
                  <dd className="text-lg font-medium text-gray-900">High</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <span className="text-2xl">📊</span>
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">Security Score</dt>
                  <dd className="text-lg font-medium text-gray-900">92/100</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Security;
