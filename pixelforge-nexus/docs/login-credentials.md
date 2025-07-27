# 🔐 PixelForge Nexus - Login Credentials & System Access

## 🎯 Default User Accounts

The PixelForge Nexus system comes pre-configured with test accounts for each user role. These accounts demonstrate the role-based access control system and allow comprehensive testing of all system features.

### 👑 Administrator Account
```
Email: admin@pixelforge.com
Password: Admin123!@#
Role: ADMIN
MFA: Disabled (for testing)
```

**Permissions:**
- ✅ Create and manage all projects
- ✅ Add/remove/edit user accounts
- ✅ Upload documents to any project
- ✅ View all projects and documents
- ✅ Access audit logs and security settings
- ✅ System configuration and management

### 🎯 Project Lead Account
```
Email: lead@pixelforge.com
Password: Lead123!@#
Role: PROJECT_LEAD
MFA: Disabled (for testing)
```

**Permissions:**
- ✅ View assigned projects
- ✅ Assign developers to their projects
- ✅ Upload documents to assigned projects
- ✅ Manage project timelines and details
- ✅ View team members and assignments
- ❌ Cannot create new projects
- ❌ Cannot manage user accounts
- ❌ Cannot access other projects

### 👨‍💻 Developer Account
```
Email: dev@pixelforge.com
Password: Dev123!@#
Role: DEVELOPER
MFA: Disabled (for testing)
```

**Permissions:**
- ✅ View assigned projects
- ✅ Access project documents
- ✅ View project details and timelines
- ✅ Update personal profile
- ❌ Cannot upload documents
- ❌ Cannot assign team members
- ❌ Cannot create projects
- ❌ Cannot access unassigned projects

## 🔒 Additional Test Accounts

### Project Lead 2
```
Email: lead2@pixelforge.com
Password: Lead456!@#
Role: PROJECT_LEAD
MFA: Enabled (for MFA testing)
MFA Secret: JBSWY3DPEHPK3PXP
```

### Developer 2
```
Email: dev2@pixelforge.com
Password: Dev456!@#
Role: DEVELOPER
MFA: Disabled
```

### Developer 3
```
Email: dev3@pixelforge.com
Password: Dev789!@#
Role: DEVELOPER
MFA: Disabled
```

## 🎮 Sample Projects

The system includes pre-configured sample projects to demonstrate functionality:

### Project 1: "Mystic Quest RPG"
- **ID**: project-001
- **Lead**: lead@pixelforge.com
- **Assigned Developers**: dev@pixelforge.com, dev2@pixelforge.com
- **Status**: Active
- **Deadline**: 2025-06-30
- **Documents**: 3 sample documents uploaded

### Project 2: "Space Shooter Arcade"
- **ID**: project-002
- **Lead**: lead2@pixelforge.com
- **Assigned Developers**: dev3@pixelforge.com
- **Status**: Active
- **Deadline**: 2025-04-15
- **Documents**: 2 sample documents uploaded

### Project 3: "Puzzle Adventure"
- **ID**: project-003
- **Lead**: admin@pixelforge.com
- **Assigned Developers**: dev@pixelforge.com, dev2@pixelforge.com, dev3@pixelforge.com
- **Status**: Completed
- **Deadline**: 2025-01-15
- **Documents**: 5 sample documents uploaded

## 🔐 Multi-Factor Authentication (MFA) Testing

### MFA Setup for Testing
For accounts with MFA enabled, use the following TOTP secret:
```
Secret: JBSWY3DPEHPK3PXP
```

**Google Authenticator Setup:**
1. Open Google Authenticator app
2. Scan QR code or manually enter secret
3. Use generated 6-digit code for login

**Alternative TOTP Apps:**
- Authy
- Microsoft Authenticator
- 1Password
- LastPass Authenticator

### MFA Backup Codes
```
Backup Codes for lead2@pixelforge.com:
- 123456
- 789012
- 345678
- 901234
- 567890
```

## 🌐 System URLs

### Development Environment
```
Frontend: http://localhost:3000
Backend API: http://localhost:3001
Database: localhost:5432
```

### Production Environment
```
Frontend: https://pixelforge-nexus.netlify.app
Backend API: https://pixelforge-nexus-api.herokuapp.com
Database: Hosted PostgreSQL instance
```

## 🔧 Database Connection

### Development Database
```
Host: localhost
Port: 5432
Database: pixelforge_nexus_dev
Username: postgres
Password: postgres123
```

### Test Database
```
Host: localhost
Port: 5432
Database: pixelforge_nexus_test
Username: postgres
Password: postgres123
```

## 🧪 API Testing

### Postman Collection
Import the provided Postman collection for API testing:
```
File: pixelforge-nexus-api.postman_collection.json
Environment: pixelforge-nexus.postman_environment.json
```

### Sample API Requests

#### Authentication
```bash
# Login
POST /api/auth/login
{
  "email": "admin@pixelforge.com",
  "password": "Admin123!@#"
}

# Login with MFA
POST /api/auth/login
{
  "email": "lead2@pixelforge.com",
  "password": "Lead456!@#",
  "mfaToken": "123456"
}
```

#### Project Management
```bash
# Get all projects (Admin)
GET /api/projects
Authorization: Bearer <token>

# Create project (Admin only)
POST /api/projects
Authorization: Bearer <token>
{
  "name": "New Game Project",
  "description": "Exciting new game",
  "deadline": "2025-12-31"
}
```

## 🔍 Security Testing Accounts

### Penetration Testing Account
```
Email: pentest@pixelforge.com
Password: PenTest123!@#
Role: DEVELOPER
Purpose: Security testing and vulnerability assessment
```

### Load Testing Account
```
Email: loadtest@pixelforge.com
Password: LoadTest123!@#
Role: DEVELOPER
Purpose: Performance and load testing
```

## 📊 Monitoring and Logging

### Admin Dashboard Access
- **URL**: /admin/dashboard
- **Required Role**: ADMIN
- **Features**: User management, system metrics, audit logs

### Audit Log Access
- **URL**: /audit
- **Required Role**: ADMIN
- **Features**: Security events, user actions, system changes

### Security Monitoring
- **URL**: /security
- **Required Role**: ADMIN
- **Features**: Failed login attempts, suspicious activities, security alerts

## 🚨 Security Considerations

### Password Policy
- Minimum 8 characters
- Must contain uppercase letter
- Must contain lowercase letter
- Must contain number
- Must contain special character
- Cannot be common passwords

### Session Management
- Access token expiry: 15 minutes
- Refresh token expiry: 7 days
- Maximum concurrent sessions: 3 per user
- Automatic logout on inactivity: 30 minutes

### Account Lockout Policy
- Failed attempts threshold: 5
- Lockout duration: 15 minutes
- Progressive lockout for repeated failures
- Admin can manually unlock accounts

## 🔄 Account Reset Procedures

### Password Reset
1. Navigate to login page
2. Click "Forgot Password"
3. Enter email address
4. Check email for reset link
5. Follow link to set new password

### Account Unlock (Admin)
1. Login as admin
2. Navigate to user management
3. Find locked account
4. Click "Unlock Account"
5. User can attempt login again

### MFA Reset (Admin)
1. Login as admin
2. Navigate to user management
3. Find user account
4. Click "Reset MFA"
5. User must set up MFA again

## 📞 Support Information

### Technical Support
- **Email**: support@pixelforge.com
- **Documentation**: /docs
- **API Reference**: /api/docs
- **Status Page**: /status

### Emergency Contacts
- **Security Issues**: security@pixelforge.com
- **System Outages**: ops@pixelforge.com
- **Data Concerns**: privacy@pixelforge.com

---

**Last Updated**: January 27, 2025  
**Version**: 1.0.0  
**Environment**: Development/Testing
