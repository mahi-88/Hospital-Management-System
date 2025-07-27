#!/bin/bash

# PixelForge Nexus Development Server Startup Script
# This script starts both backend and frontend development servers

set -e

echo "🎮 PixelForge Nexus - Development Server Startup"
echo "==============================================="

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

# Check if dependencies are installed
check_dependencies() {
    print_status "Checking dependencies..."
    
    if [ ! -d "backend/node_modules" ]; then
        print_error "Backend dependencies not installed. Run: cd backend && npm install"
        exit 1
    fi
    
    if [ ! -d "frontend/node_modules" ]; then
        print_error "Frontend dependencies not installed. Run: cd frontend && npm install"
        exit 1
    fi
    
    print_success "Dependencies check passed"
}

# Check if environment files exist
check_env_files() {
    print_status "Checking environment files..."
    
    if [ ! -f "backend/.env" ]; then
        print_warning "Backend .env file not found. Creating from template..."
        cp backend/.env.example backend/.env
        print_warning "Please update backend/.env with your configuration"
    fi
    
    if [ ! -f "frontend/.env" ]; then
        print_warning "Frontend .env file not found. Creating..."
        echo "REACT_APP_API_URL=http://localhost:3001/api" > frontend/.env
    fi
    
    print_success "Environment files check passed"
}

# Check database connection
check_database() {
    print_status "Checking database connection..."
    
    cd backend
    if npx prisma db push --accept-data-loss > /dev/null 2>&1; then
        print_success "Database connection successful"
    else
        print_error "Database connection failed. Please ensure PostgreSQL is running."
        print_status "You can start PostgreSQL with Docker: docker-compose up -d postgres"
        exit 1
    fi
    cd ..
}

# Start backend server
start_backend() {
    print_status "Starting backend server..."
    cd backend
    npm run dev &
    BACKEND_PID=$!
    cd ..
    print_success "Backend server started (PID: $BACKEND_PID)"
}

# Start frontend server
start_frontend() {
    print_status "Starting frontend server..."
    cd frontend
    npm start &
    FRONTEND_PID=$!
    cd ..
    print_success "Frontend server started (PID: $FRONTEND_PID)"
}

# Wait for servers to be ready
wait_for_servers() {
    print_status "Waiting for servers to be ready..."
    
    # Wait for backend
    for i in {1..30}; do
        if curl -s http://localhost:3001/health > /dev/null 2>&1; then
            print_success "Backend server is ready"
            break
        fi
        if [ $i -eq 30 ]; then
            print_error "Backend server failed to start"
            exit 1
        fi
        sleep 2
    done
    
    # Wait for frontend
    for i in {1..30}; do
        if curl -s http://localhost:3000 > /dev/null 2>&1; then
            print_success "Frontend server is ready"
            break
        fi
        if [ $i -eq 30 ]; then
            print_error "Frontend server failed to start"
            exit 1
        fi
        sleep 2
    done
}

# Cleanup function
cleanup() {
    print_status "Shutting down servers..."
    if [ ! -z "$BACKEND_PID" ]; then
        kill $BACKEND_PID 2>/dev/null || true
    fi
    if [ ! -z "$FRONTEND_PID" ]; then
        kill $FRONTEND_PID 2>/dev/null || true
    fi
    print_success "Servers stopped"
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

# Main function
main() {
    echo ""
    print_status "Starting development environment..."
    
    # Run checks
    check_dependencies
    check_env_files
    check_database
    
    echo ""
    print_status "Starting servers..."
    
    # Start servers
    start_backend
    sleep 5  # Give backend time to start
    start_frontend
    
    # Wait for servers to be ready
    wait_for_servers
    
    echo ""
    print_success "Development environment is ready!"
    echo ""
    echo "🌐 Application URLs:"
    echo "   - Frontend: http://localhost:3000"
    echo "   - Backend API: http://localhost:3001"
    echo "   - Health Check: http://localhost:3001/health"
    echo ""
    echo "📋 Default login credentials:"
    echo "   - Admin: admin@pixelforge.com / Admin123!@#"
    echo "   - Project Lead: lead@pixelforge.com / Lead123!@#"
    echo "   - Developer: dev@pixelforge.com / Dev123!@#"
    echo ""
    echo "Press Ctrl+C to stop the servers"
    echo ""
    
    # Keep script running
    while true; do
        sleep 1
    done
}

# Run main function
main "$@"
