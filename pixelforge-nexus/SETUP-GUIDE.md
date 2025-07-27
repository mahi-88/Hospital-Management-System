# 🚀 PixelForge Nexus - Complete Setup Guide

This guide will help you set up and run the complete PixelForge Nexus system with all its security features.

## 📋 Prerequisites

### Required Software
- **Node.js 18+** and npm 8+
- **PostgreSQL 14+** (or Docker)
- **Git** for version control

### Optional (Recommended)
- **Docker & Docker Compose** for easy database setup
- **VS Code** with TypeScript extensions

## 🛠️ Quick Setup (Automated)

### 1. Clone and Setup
```bash
git clone <repository-url>
cd pixelforge-nexus
chmod +x scripts/setup.sh
./scripts/setup.sh
```

### 2. Start Development Servers
```bash
chmod +x scripts/start-dev.sh
./scripts/start-dev.sh
```

### 3. Access the Application
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:3001

## 🔧 Manual Setup (Step by Step)

### 1. Install Dependencies

**Backend:**
```bash
cd backend
npm install
cd ..
```

**Frontend:**
```bash
cd frontend
npm install
cd ..
```

### 2. Environment Configuration

**Backend Environment (.env):**
```bash
cd backend
cp .env.example .env
```

Update `backend/.env` with your configuration:
```env
DATABASE_URL=postgresql://postgres:postgres123@localhost:5432/pixelforge_nexus_dev
JWT_SECRET=your-super-secret-jwt-key-change-this-in-production
JWT_REFRESH_SECRET=your-super-secret-refresh-key-change-this-in-production
NODE_ENV=development
PORT=3001
FRONTEND_URL=http://localhost:3000
```

**Frontend Environment (.env):**
```bash
cd frontend
echo "REACT_APP_API_URL=http://localhost:3001/api" > .env
cd ..
```

### 3. Database Setup

**Option A: Using Docker (Recommended)**
```bash
docker-compose up -d postgres
cd backend
npx prisma migrate dev --name init
npx prisma generate
npm run db:seed
cd ..
```

**Option B: Manual PostgreSQL Setup**
```bash
# Ensure PostgreSQL is running
# Create database: pixelforge_nexus_dev
cd backend
npx prisma migrate dev --name init
npx prisma generate
npm run db:seed
cd ..
```

### 4. Start Development Servers

**Terminal 1 (Backend):**
```bash
cd backend
npm run dev
```

**Terminal 2 (Frontend):**
```bash
cd frontend
npm start
```

## 🔑 Default Login Credentials

| Role | Email | Password | MFA |
|------|-------|----------|-----|
| **Admin** | admin@pixelforge.com | Admin123!@# | Disabled |
| **Project Lead** | lead@pixelforge.com | Lead123!@# | Disabled |
| **Developer** | dev@pixelforge.com | Dev123!@# | Disabled |
| **Lead with MFA** | lead2@pixelforge.com | Lead456!@# | Enabled |

**MFA Test Secret:** `JBSWY3DPEHPK3PXP`

## 🧪 Testing the System

### 1. Security Features Test
```bash
# Backend security tests
cd backend
npm run test:security

# Frontend tests
cd frontend
npm test
```

### 2. Manual Security Testing

**Test Authentication:**
1. Try logging in with wrong credentials (should fail after 5 attempts)
2. Test MFA with lead2@pixelforge.com account
3. Verify session expiry (15 minutes)

**Test Authorization:**
1. Login as Developer and try to access admin features
2. Try to access projects you're not assigned to
3. Test document upload permissions

**Test Input Validation:**
1. Try XSS payloads in forms
2. Test SQL injection attempts
3. Upload invalid file types

## 📊 System Features Overview

### 🔐 Security Features
- **Multi-Factor Authentication** (TOTP)
- **Role-Based Access Control** (3 roles)
- **Session Management** (15-min expiry)
- **Account Lockout** (5 failed attempts)
- **Audit Logging** (all actions tracked)
- **Rate Limiting** (DDoS protection)
- **Input Validation** (XSS/SQL injection prevention)

### 👥 User Management
- **Admin**: Full system access, user management
- **Project Lead**: Project management, team assignment
- **Developer**: Read-only access to assigned projects

### 🎮 Project Management
- Create and manage game projects
- Assign team members to projects
- Set deadlines and track progress
- Upload and manage project documents

### 📄 Document Management
- Secure file upload (10MB limit)
- Access control based on project assignment
- File type validation and virus scanning
- Download tracking and audit logging

## 🐳 Docker Deployment

### Development Environment
```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

### Production Environment
```bash
# Start production services
docker-compose --profile production up -d
```

## 🔍 Monitoring & Health Checks

### Health Check Endpoints
- **Backend Health**: http://localhost:3001/health
- **Database Status**: Automatic monitoring
- **Security Events**: Real-time logging

### Monitoring Dashboard
- **Audit Logs**: http://localhost:3000/audit (Admin only)
- **Security Center**: http://localhost:3000/security
- **User Management**: http://localhost:3000/users (Admin only)

## 🛡️ Security Configuration

### Password Policy
- Minimum 8 characters
- Must contain: uppercase, lowercase, number, special character
- bcrypt hashing with 12 salt rounds

### Session Security
- JWT tokens with 15-minute expiry
- Refresh tokens with 7-day expiry
- Maximum 3 concurrent sessions per user
- Automatic logout on inactivity (30 minutes)

### Rate Limiting
- 100 requests per 15 minutes (general)
- 5 login attempts per 15 minutes
- Progressive lockout for repeated failures

## 🔧 Troubleshooting

### Common Issues

**Database Connection Error:**
```bash
# Check if PostgreSQL is running
docker-compose ps postgres

# Restart database
docker-compose restart postgres
```

**Port Already in Use:**
```bash
# Kill processes on ports 3000/3001
npx kill-port 3000
npx kill-port 3001
```

**Permission Errors:**
```bash
# Fix script permissions
chmod +x scripts/*.sh
```

**Node Modules Issues:**
```bash
# Clean and reinstall
rm -rf backend/node_modules frontend/node_modules
npm run install:all
```

### Debug Mode

**Enable Debug Logging:**
```bash
# Backend
cd backend
DEBUG=pixelforge:* npm run dev

# Frontend
cd frontend
REACT_APP_DEBUG=true npm start
```

## 📚 Additional Resources

### Documentation
- [Individual Report](docs/individual-report.md) - Complete system analysis
- [Security Testing Report](security/security-testing-report.md) - Security assessment
- [Login Credentials](docs/login-credentials.md) - Test account details
- [Video Script](docs/video-script.md) - Demo walkthrough

### API Documentation
- **Swagger UI**: http://localhost:3001/api/docs (when available)
- **Postman Collection**: Import from `docs/api-collection.json`

### Security Reports
- **Formal Verification**: [formal-methods/verification-report.md](formal-methods/verification-report.md)
- **Penetration Testing**: [security/security-testing-report.md](security/security-testing-report.md)

## 🎯 Next Steps

1. **Explore the System**: Login with different roles and test features
2. **Review Security**: Check audit logs and security settings
3. **Test Functionality**: Create projects, assign users, upload documents
4. **Read Documentation**: Review the comprehensive reports
5. **Watch Demo**: Follow the video script for guided tour

## 📞 Support

If you encounter issues:

1. Check this troubleshooting guide
2. Review the logs: `docker-compose logs -f`
3. Verify environment configuration
4. Check database connectivity
5. Ensure all dependencies are installed

---

**🎮 Happy coding with PixelForge Nexus!**

*Built with security, designed for developers, ready for production.*
