
<div align="center">

# 🎮 PixelForge Nexus

⭐ If you find PixelForge Nexus useful, please star us on GitHub! ⭐

PixelForge Nexus is a secure game development management system for Creative SkillZ LLC.<br />
We combine project management, team collaboration, and asset management while making it easy for everyone on the team to use.<br />
Built with enterprise-grade security and role-based access control in mind. 🔒<br />

💪 As secure as enterprise systems but as easy to use as modern tools<br />
🔄 A perfect solution for game development teams and creative projects<br />
🌐 Secure • Scalable • User-Friendly<br />

[![Security Score](https://img.shields.io/badge/Security%20Score-92%2F100-brightgreen)](./security/security-testing-report.md)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8%2B-blue)](https://php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8-orange)](https://mysql.com/)
<br />

</div>
<br /><br />

## 🚀 Key Features ##

### 🔒 Security Features
- **Multi-Factor Authentication (MFA)** with TOTP support
- **Role-Based Access Control** (Admin/Project Lead/Developer)
- **bcrypt Password Hashing** for secure authentication
- **Session Management** with automatic timeout
- **CSRF Protection** and XSS prevention
- **Input Validation** and SQL injection protection

### 👥 User Roles & Permissions
- **Admin**: Full system access, user management, all projects
- **Project Lead**: Team assignment, project management, document upload
- **Developer**: Assigned project access, task updates, document viewing

### 📁 Asset Management
- **Secure File Upload** with type validation
- **Project-Based Organization** of assets and documents
- **Version Control** for project files
- **Access Control** based on team assignments

### 📊 Project Management
- **Team Assignment** to specific projects
- **Progress Tracking** and analytics
- **Dashboard Views** tailored by role
- **Audit Logging** for security compliance
<br /><br />
## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- MySQL 8.0+ or MariaDB 10.6+
- Apache or Nginx web server
- Composer

### Installation
```bash
# Clone the repository
git clone <your-repository-url> pixelforge-nexus
cd pixelforge-nexus

# Install dependencies
composer install

# Copy environment file
cp config/sample.env .env

# Configure your database settings in .env
# Set up your database and run migrations

# Set proper permissions
chmod -R 755 storage/
chmod -R 755 userfiles/
```

### Default Login Credentials
```
Admin:
Email: admin@pixelforge.com
Password: Admin123!@#

Project Lead:
Email: lead@pixelforge.com
Password: Lead123!@#

Developer:
Email: dev@pixelforge.com
Password: Dev123!@#
```


## 🔒 Security Implementation

### Authentication & Authorization
- **bcrypt Password Hashing** with 12 rounds for secure password storage
- **Multi-Factor Authentication (MFA)** using TOTP (Time-based One-Time Password)
- **JWT-based Session Management** with automatic timeout
- **Role-Based Access Control** with granular permissions

### Data Protection
- **Input Validation** and sanitization to prevent XSS attacks
- **SQL Injection Protection** using parameterized queries
- **CSRF Token Protection** on all forms
- **Secure File Upload** with type validation and virus scanning

### Security Headers
- **Content Security Policy (CSP)** implementation
- **HTTP Strict Transport Security (HSTS)** for HTTPS enforcement
- **X-Frame-Options** to prevent clickjacking
- **X-Content-Type-Options** to prevent MIME sniffing

## 🏗️ System Architecture

### Technology Stack
- **Backend**: PHP 8.2+ with modern security practices
- **Database**: MySQL 8.0+ with encrypted connections
- **Frontend**: Enhanced Leantime UI with security improvements
- **Authentication**: bcrypt + TOTP MFA integration
## 📋 Project Structure

```
pixelforge-nexus-php/
├── app/                     # Application core
│   ├── Core/               # Core framework files
│   ├── Domain/             # Business logic modules
│   ├── Views/              # Template files
│   └── Plugins/            # Plugin system
├── config/                 # Configuration files
├── database/               # Database migrations
├── public/                 # Web accessible files
├── storage/                # Application storage
└── userfiles/              # User uploaded files
```

## 🔧 Development Guidelines

### Secure Coding Practices
- Always validate and sanitize user inputs
- Use parameterized queries for database operations
- Implement proper error handling without information disclosure
- Regular security code reviews and testing

### Code Quality
- Follow PSR-12 coding standards
- Use type hints and return types
- Implement comprehensive logging
- Write unit tests for critical functionality

## 📚 Documentation

- [Security Implementation Guide](docs/security.md)
- [User Role Management](docs/roles.md)
- [Asset Management System](docs/assets.md)
- [MFA Setup Guide](docs/mfa.md)

## 🤝 Contributing

This project is developed for Creative SkillZ LLC. For internal development:
1. Follow the established coding standards
2. Ensure all security tests pass
3. Update documentation for new features
4. Test thoroughly before deployment

## 📄 License

This project is proprietary software developed for Creative SkillZ LLC.

## 🆘 Support

For support and questions:
- Internal Documentation: See docs/ folder
- Security Issues: Contact system administrator immediately
- Feature Requests: Submit through internal channels
