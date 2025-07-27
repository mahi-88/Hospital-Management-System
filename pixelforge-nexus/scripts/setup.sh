#!/bin/bash

# PixelForge Nexus Setup Script
# This script sets up the development environment for PixelForge Nexus

set -e  # Exit on any error

echo "🎮 PixelForge Nexus - Setup Script"
echo "=================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
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
            exit 1
        fi
    else
        print_error "Node.js is not installed. Please install Node.js 18 or higher."
        exit 1
    fi
}

# Check if PostgreSQL is installed
check_postgresql() {
    print_status "Checking PostgreSQL installation..."
    if command -v psql &> /dev/null; then
        PG_VERSION=$(psql --version)
        print_success "PostgreSQL is installed: $PG_VERSION"
    else
        print_warning "PostgreSQL is not installed. You can use Docker instead."
    fi
}

# Check if Docker is installed
check_docker() {
    print_status "Checking Docker installation..."
    if command -v docker &> /dev/null; then
        DOCKER_VERSION=$(docker --version)
        print_success "Docker is installed: $DOCKER_VERSION"
    else
        print_warning "Docker is not installed. Manual database setup will be required."
    fi
}

# Install backend dependencies
install_backend_deps() {
    print_status "Installing backend dependencies..."
    cd backend
    npm install
    print_success "Backend dependencies installed"
    cd ..
}

# Install frontend dependencies
install_frontend_deps() {
    print_status "Installing frontend dependencies..."
    cd frontend
    npm install
    print_success "Frontend dependencies installed"
    cd ..
}

# Setup environment files
setup_env_files() {
    print_status "Setting up environment files..."
    
    # Backend environment
    if [ ! -f "backend/.env" ]; then
        cp backend/.env.example backend/.env
        print_success "Backend .env file created from template"
        print_warning "Please update backend/.env with your actual configuration"
    else
        print_warning "Backend .env file already exists"
    fi
    
    # Frontend environment
    if [ ! -f "frontend/.env" ]; then
        echo "REACT_APP_API_URL=http://localhost:3001/api" > frontend/.env
        print_success "Frontend .env file created"
    else
        print_warning "Frontend .env file already exists"
    fi
}

# Setup database with Docker
setup_database_docker() {
    print_status "Setting up database with Docker..."
    if command -v docker &> /dev/null; then
        docker-compose up -d postgres
        print_success "PostgreSQL container started"
        
        # Wait for database to be ready
        print_status "Waiting for database to be ready..."
        sleep 10
        
        # Run migrations
        cd backend
        npx prisma migrate dev --name init
        npx prisma generate
        npm run db:seed
        print_success "Database migrations and seeding completed"
        cd ..
    else
        print_error "Docker is not available. Please set up PostgreSQL manually."
    fi
}

# Setup database manually
setup_database_manual() {
    print_status "Setting up database manually..."
    print_warning "Please ensure PostgreSQL is running and accessible"
    
    cd backend
    npx prisma migrate dev --name init
    npx prisma generate
    npm run db:seed
    print_success "Database migrations and seeding completed"
    cd ..
}

# Create necessary directories
create_directories() {
    print_status "Creating necessary directories..."
    mkdir -p backend/uploads
    mkdir -p backend/logs
    mkdir -p backups
    print_success "Directories created"
}

# Main setup function
main() {
    echo ""
    print_status "Starting PixelForge Nexus setup..."
    echo ""
    
    # Check prerequisites
    check_nodejs
    check_postgresql
    check_docker
    
    echo ""
    print_status "Installing dependencies..."
    
    # Install dependencies
    install_backend_deps
    install_frontend_deps
    
    # Setup environment
    setup_env_files
    create_directories
    
    echo ""
    print_status "Setting up database..."
    
    # Database setup
    if command -v docker &> /dev/null; then
        read -p "Use Docker for database setup? (y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            setup_database_docker
        else
            setup_database_manual
        fi
    else
        setup_database_manual
    fi
    
    echo ""
    print_success "Setup completed successfully!"
    echo ""
    echo "🚀 Next steps:"
    echo "1. Update backend/.env with your configuration"
    echo "2. Start the development servers:"
    echo "   - Backend: cd backend && npm run dev"
    echo "   - Frontend: cd frontend && npm start"
    echo "   - Or use: npm run dev (from root directory)"
    echo ""
    echo "📋 Default login credentials:"
    echo "   - Admin: admin@pixelforge.com / Admin123!@#"
    echo "   - Project Lead: lead@pixelforge.com / Lead123!@#"
    echo "   - Developer: dev@pixelforge.com / Dev123!@#"
    echo ""
    echo "🌐 Application URLs:"
    echo "   - Frontend: http://localhost:3000"
    echo "   - Backend API: http://localhost:3001"
    echo "   - API Documentation: http://localhost:3001/api/docs"
    echo ""
    print_success "Happy coding! 🎮"
}

# Run main function
main "$@"
