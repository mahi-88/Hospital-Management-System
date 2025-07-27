#!/bin/bash

# PixelForge Nexus Installation Script
# This script installs all dependencies and sets up the development environment

set -e  # Exit on any error

echo "🎮 PixelForge Nexus - Installation Script"
echo "=========================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Node.js is installed
check_nodejs() {
    print_status "Checking Node.js installation..."
    if command -v node &> /dev/null; then
        NODE_VERSION=$(node --version)
        print_success "Node.js is installed: $NODE_VERSION"
        
        # Check if version is >= 18
        MAJOR_VERSION=$(echo $NODE_VERSION | cut -d'.' -f1 | sed 's/v//')
        if [ "$MAJOR_VERSION" -lt 18 ]; then
            print_error "Node.js version 18 or higher is required. Current version: $NODE_VERSION"
            print_status "Please install Node.js 18+ from https://nodejs.org/"
            exit 1
        fi
    else
        print_error "Node.js is not installed."
        print_status "Please install Node.js 18+ from https://nodejs.org/"
        exit 1
    fi
}

# Check if npm is installed
check_npm() {
    print_status "Checking npm installation..."
    if command -v npm &> /dev/null; then
        NPM_VERSION=$(npm --version)
        print_success "npm is installed: $NPM_VERSION"
    else
        print_error "npm is not installed. Please install npm."
        exit 1
    fi
}

# Install backend dependencies
install_backend() {
    print_status "Installing backend dependencies..."
    cd backend
    
    if [ -f "package.json" ]; then
        npm install
        print_success "Backend dependencies installed successfully"
    else
        print_error "Backend package.json not found"
        exit 1
    fi
    
    cd ..
}

# Install frontend dependencies
install_frontend() {
    print_status "Installing frontend dependencies..."
    cd frontend
    
    if [ -f "package.json" ]; then
        npm install
        print_success "Frontend dependencies installed successfully"
    else
        print_error "Frontend package.json not found"
        exit 1
    fi
    
    cd ..
}

# Install root dependencies
install_root() {
    print_status "Installing root dependencies..."
    if [ -f "package.json" ]; then
        npm install
        print_success "Root dependencies installed successfully"
    else
        print_warning "Root package.json not found, skipping..."
    fi
}

# Setup environment files
setup_env() {
    print_status "Setting up environment files..."
    
    # Backend environment
    if [ ! -f "backend/.env" ]; then
        if [ -f "backend/.env.example" ]; then
            cp backend/.env.example backend/.env
            print_success "Backend .env file created from template"
            print_warning "Please update backend/.env with your actual configuration"
        else
            print_warning "Backend .env.example not found"
        fi
    else
        print_warning "Backend .env file already exists"
    fi
    
    # Frontend environment
    if [ ! -f "frontend/.env" ]; then
        if [ -f "frontend/.env.example" ]; then
            cp frontend/.env.example frontend/.env
            print_success "Frontend .env file created from template"
        else
            # Create basic frontend .env
            echo "REACT_APP_API_URL=http://localhost:3001/api" > frontend/.env
            print_success "Frontend .env file created"
        fi
    else
        print_warning "Frontend .env file already exists"
    fi
}

# Create necessary directories
create_directories() {
    print_status "Creating necessary directories..."
    
    # Backend directories
    mkdir -p backend/uploads
    mkdir -p backend/logs
    mkdir -p backend/dist
    
    # Frontend directories
    mkdir -p frontend/build
    
    # Root directories
    mkdir -p backups
    mkdir -p logs
    
    print_success "Directories created successfully"
}

# Install global dependencies (optional)
install_global_deps() {
    print_status "Checking global dependencies..."
    
    # Check if TypeScript is installed globally
    if ! command -v tsc &> /dev/null; then
        print_warning "TypeScript not found globally. Installing..."
        npm install -g typescript
        print_success "TypeScript installed globally"
    else
        print_success "TypeScript is already installed globally"
    fi
    
    # Check if Prisma CLI is installed globally
    if ! command -v prisma &> /dev/null; then
        print_warning "Prisma CLI not found globally. Installing..."
        npm install -g prisma
        print_success "Prisma CLI installed globally"
    else
        print_success "Prisma CLI is already installed globally"
    fi
}

# Verify installation
verify_installation() {
    print_status "Verifying installation..."
    
    # Check backend node_modules
    if [ -d "backend/node_modules" ]; then
        print_success "Backend dependencies verified"
    else
        print_error "Backend dependencies not found"
        return 1
    fi
    
    # Check frontend node_modules
    if [ -d "frontend/node_modules" ]; then
        print_success "Frontend dependencies verified"
    else
        print_error "Frontend dependencies not found"
        return 1
    fi
    
    # Check environment files
    if [ -f "backend/.env" ] && [ -f "frontend/.env" ]; then
        print_success "Environment files verified"
    else
        print_warning "Some environment files are missing"
    fi
    
    print_success "Installation verification completed"
}

# Main installation function
main() {
    echo ""
    print_status "Starting PixelForge Nexus installation..."
    echo ""
    
    # Check prerequisites
    check_nodejs
    check_npm
    
    echo ""
    print_status "Installing dependencies..."
    
    # Install dependencies
    install_root
    install_backend
    install_frontend
    
    # Setup environment
    setup_env
    create_directories
    
    # Optional global dependencies
    read -p "Install global dependencies (TypeScript, Prisma CLI)? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        install_global_deps
    fi
    
    # Verify installation
    verify_installation
    
    echo ""
    print_success "Installation completed successfully!"
    echo ""
    echo "🚀 Next steps:"
    echo "1. Update backend/.env with your database configuration"
    echo "2. Set up your PostgreSQL database"
    echo "3. Run database migrations: cd backend && npx prisma migrate dev"
    echo "4. Seed the database: cd backend && npm run db:seed"
    echo "5. Start the development servers: npm run dev"
    echo ""
    echo "📚 Documentation:"
    echo "   - Setup Guide: ./SETUP-GUIDE.md"
    echo "   - README: ./README.md"
    echo ""
    print_success "Happy coding! 🎮"
}

# Run main function
main "$@"
