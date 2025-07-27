#!/bin/bash

# PixelForge Nexus Database Setup Script
# This script sets up the PostgreSQL database and runs migrations

set -e  # Exit on any error

echo "🗄️ PixelForge Nexus - Database Setup Script"
echo "============================================"

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

# Check if PostgreSQL is running
check_postgres() {
    print_status "Checking PostgreSQL connection..."
    
    # Try to connect to PostgreSQL
    if command -v psql &> /dev/null; then
        if psql -h localhost -U postgres -d postgres -c "SELECT 1;" &> /dev/null; then
            print_success "PostgreSQL is running and accessible"
            return 0
        else
            print_warning "PostgreSQL is installed but not accessible"
            return 1
        fi
    else
        print_warning "PostgreSQL client (psql) not found"
        return 1
    fi
}

# Setup database with Docker
setup_with_docker() {
    print_status "Setting up database with Docker..."
    
    if command -v docker &> /dev/null; then
        # Check if docker-compose.yml exists
        if [ -f "docker-compose.yml" ]; then
            print_status "Starting PostgreSQL container..."
            docker-compose up -d postgres
            
            # Wait for database to be ready
            print_status "Waiting for database to be ready..."
            sleep 15
            
            # Check if container is running
            if docker-compose ps postgres | grep -q "Up"; then
                print_success "PostgreSQL container is running"
                return 0
            else
                print_error "Failed to start PostgreSQL container"
                return 1
            fi
        else
            print_error "docker-compose.yml not found"
            return 1
        fi
    else
        print_error "Docker is not installed"
        return 1
    fi
}

# Create database manually
create_database() {
    print_status "Creating database manually..."
    
    # Database configuration
    DB_NAME="pixelforge_nexus_dev"
    DB_USER="postgres"
    DB_HOST="localhost"
    DB_PORT="5432"
    
    # Create database if it doesn't exist
    print_status "Creating database: $DB_NAME"
    
    if psql -h $DB_HOST -U $DB_USER -d postgres -tc "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'" | grep -q 1; then
        print_warning "Database $DB_NAME already exists"
    else
        psql -h $DB_HOST -U $DB_USER -d postgres -c "CREATE DATABASE $DB_NAME;"
        print_success "Database $DB_NAME created successfully"
    fi
    
    # Create test database
    DB_TEST_NAME="pixelforge_nexus_test"
    print_status "Creating test database: $DB_TEST_NAME"
    
    if psql -h $DB_HOST -U $DB_USER -d postgres -tc "SELECT 1 FROM pg_database WHERE datname = '$DB_TEST_NAME'" | grep -q 1; then
        print_warning "Test database $DB_TEST_NAME already exists"
    else
        psql -h $DB_HOST -U $DB_USER -d postgres -c "CREATE DATABASE $DB_TEST_NAME;"
        print_success "Test database $DB_TEST_NAME created successfully"
    fi
}

# Run Prisma migrations
run_migrations() {
    print_status "Running Prisma migrations..."
    
    cd backend
    
    # Check if Prisma is installed
    if [ ! -d "node_modules/@prisma" ]; then
        print_error "Prisma not found. Please run npm install first."
        exit 1
    fi
    
    # Generate Prisma client
    print_status "Generating Prisma client..."
    npx prisma generate
    print_success "Prisma client generated"
    
    # Run migrations
    print_status "Running database migrations..."
    npx prisma migrate dev --name init
    print_success "Database migrations completed"
    
    cd ..
}

# Seed the database
seed_database() {
    print_status "Seeding database with initial data..."
    
    cd backend
    
    # Check if seed script exists
    if [ -f "prisma/seed.ts" ]; then
        npm run db:seed
        print_success "Database seeded successfully"
    else
        print_warning "Seed script not found, skipping..."
    fi
    
    cd ..
}

# Verify database setup
verify_setup() {
    print_status "Verifying database setup..."
    
    cd backend
    
    # Check database connection
    if npx prisma db push --accept-data-loss > /dev/null 2>&1; then
        print_success "Database connection verified"
    else
        print_error "Database connection failed"
        return 1
    fi
    
    # Check if tables exist
    if npx prisma db execute --stdin <<< "SELECT COUNT(*) FROM \"User\";" > /dev/null 2>&1; then
        print_success "Database tables verified"
    else
        print_warning "Some database tables may be missing"
    fi
    
    cd ..
}

# Reset database (optional)
reset_database() {
    print_warning "This will delete all data in the database!"
    read -p "Are you sure you want to reset the database? (y/N): " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_status "Resetting database..."
        cd backend
        npx prisma migrate reset --force
        print_success "Database reset completed"
        cd ..
    else
        print_status "Database reset cancelled"
    fi
}

# Main setup function
main() {
    echo ""
    print_status "Starting database setup..."
    echo ""
    
    # Check if reset is requested
    if [ "$1" = "--reset" ]; then
        reset_database
        return
    fi
    
    # Try to connect to existing PostgreSQL
    if check_postgres; then
        print_status "Using existing PostgreSQL installation"
        create_database
    else
        print_status "PostgreSQL not accessible, trying Docker..."
        if setup_with_docker; then
            print_status "Using Docker PostgreSQL"
        else
            print_error "Failed to setup database with Docker"
            print_status "Please install and start PostgreSQL manually"
            print_status "Or install Docker and try again"
            exit 1
        fi
    fi
    
    # Wait a moment for database to be ready
    sleep 5
    
    # Run migrations and seed
    run_migrations
    seed_database
    
    # Verify setup
    verify_setup
    
    echo ""
    print_success "Database setup completed successfully!"
    echo ""
    echo "📊 Database Information:"
    echo "   - Database: pixelforge_nexus_dev"
    echo "   - Test Database: pixelforge_nexus_test"
    echo "   - Host: localhost"
    echo "   - Port: 5432"
    echo "   - User: postgres"
    echo ""
    echo "🔑 Default User Accounts Created:"
    echo "   - Admin: admin@pixelforge.com / Admin123!@#"
    echo "   - Project Lead: lead@pixelforge.com / Lead123!@#"
    echo "   - Developer: dev@pixelforge.com / Dev123!@#"
    echo ""
    echo "🚀 Next steps:"
    echo "   - Start the backend server: cd backend && npm run dev"
    echo "   - Start the frontend server: cd frontend && npm start"
    echo "   - Or use: npm run dev (from root directory)"
    echo ""
    print_success "Database is ready! 🗄️"
}

# Run main function
main "$@"
