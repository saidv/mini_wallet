#!/bin/bash

# Pimono - MiniWallet
# Version: 1.1.0
# Features: Validation, error handling, health checks, queue verification, Pusher check

set -e  # Exit on error
set -u  # Exit on undefined variable

# Script version
SCRIPT_VERSION="1.0.0"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
BACKEND_DIR="backend"
ENV_FILE="${BACKEND_DIR}/.env"
ENV_EXAMPLE="${BACKEND_DIR}/.env.example"
REQUIRED_COMMANDS=("docker" "docker-compose")
MAX_RETRY=30
RETRY_INTERVAL=2

# Logging functions
log_info() {
    echo -e "${BLUE}$1${NC}"
}

log_success() {
    echo -e "${GREEN}$1${NC}"
}

log_warning() {
    echo -e "${YELLOW}$1${NC}"
}

log_error() {
    echo -e "${RED}$1${NC}"
}

log_step() {
    echo -e "${PURPLE}$1${NC}"
}

# Check if required commands exist
check_dependencies() {
    log_step "Checking dependencies..."
    for cmd in "${REQUIRED_COMMANDS[@]}"; do
        if ! command -v "$cmd" &> /dev/null; then
            log_error "$cmd is not installed. Please install it first."
            exit 1
        fi
        log_success "$cmd found"
    done
}

# Check if Docker daemon is running
check_docker_running() {
    log_step "Checking Docker daemon..."
    if ! docker info &> /dev/null; then
        log_error "Docker daemon is not running"
        echo ""
        echo "Please start Docker"
        exit 1
    fi
    log_success "Docker daemon is running"
}

# Check port availability
check_port_availability() {
    log_step "Checking port availability..."
    
    local ports_in_use=()
    
    if lsof -Pi :80 -sTCP:LISTEN -t &> /dev/null; then
        ports_in_use+=("80 (Frontend HTTP)")
    fi
    
    if lsof -Pi :443 -sTCP:LISTEN -t &> /dev/null; then
        ports_in_use+=("443 (Frontend HTTPS)")
    fi
    
    if lsof -Pi :8443 -sTCP:LISTEN -t &> /dev/null; then
        ports_in_use+=("8443 (Backend HTTPS)")
    fi
    
    if lsof -Pi :3306 -sTCP:LISTEN -t &> /dev/null; then
        ports_in_use+=("3306 (Database)")
    fi
    
    if [ ${#ports_in_use[@]} -gt 0 ]; then
        log_warning "Ports already in use: ${ports_in_use[*]}"
        read -p "Stop existing processes and continue? (Y/n): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Nn]$ ]]; then
            log_info "Stopping existing containers..."
            docker-compose down 2>/dev/null || true
        else
            log_error "Cannot proceed with ports in use"
            exit 1
        fi
    else
        log_success "All ports available"
    fi
}

# Validate .env.example exists
validate_env_example() {
    if [ ! -f "$ENV_EXAMPLE" ]; then
        log_error "$ENV_EXAMPLE not found!"
        exit 1
    fi
}

# Copy .env with optional interactive configuration
setup_env_file() {
    if [ -f "$ENV_FILE" ]; then
        log_warning "$ENV_FILE already exists"
        read -p "Do you want to overwrite it? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Keeping existing $ENV_FILE"
            return
        fi
    fi

    log_info "Creating $ENV_FILE from $ENV_EXAMPLE..."
    cp "$ENV_EXAMPLE" "$ENV_FILE"

    # Generate Laravel application key if PHP is available
    if command -v php &> /dev/null && [ -f "${BACKEND_DIR}/artisan" ]; then
        log_info "Generating Laravel application key..."
        cd "$BACKEND_DIR"
        php artisan key:generate --force --ansi || log_warning "Could not generate key (will run in container)"
        cd ..
    fi

    log_success ".env file created"
}

# Validate Pusher configuration
validate_pusher_config() {
    log_step "Validating Pusher configuration..."
    
    local has_pusher_key=$(grep "^PUSHER_APP_KEY=" "$ENV_FILE" | grep -v "your-pusher-app-key" || true)
    local has_pusher_secret=$(grep "^PUSHER_APP_SECRET=" "$ENV_FILE" | grep -v "your-pusher-secret" || true)
    
    if [ -z "$has_pusher_key" ] || [ -z "$has_pusher_secret" ]; then
        log_warning "Pusher credentials not configured"
        echo ""
        echo "Real-time updates require Pusher configuration:"
        echo "  1. Sign up at https://pusher.com"
        echo "  2. Create a new app"
        echo "  3. Update PUSHER_* variables in backend/.env"
        echo ""
        log_warning "Application will work, but real-time updates will be disabled"
        echo ""
        read -p "Continue without Pusher? (Y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Nn]$ ]]; then
            exit 1
        fi
    else
        log_success "Pusher configuration found"
    fi
}

# Stop existing containers
stop_existing_containers() {
    log_step "Stopping existing containers..."
    docker-compose down --remove-orphans 2>/dev/null || true
    log_success "Existing containers stopped"
}

# Start Docker containers (production mode only)
start_containers() {
    log_step "Starting Docker containers (production mode)..."
    
    if [ "${1:-}" == "--build" ]; then
        log_info "Building images from scratch..."
        docker-compose build --no-cache
        docker-compose up -d
    else
        docker-compose up -d --build
    fi
    
    log_success "Containers started successfully"
}

# Wait for service to be healthy
wait_for_service() {
    local service=$1
    local max_attempts=$MAX_RETRY
    local attempt=0

    log_step "Waiting for $service to be ready..."

    while [ $attempt -lt $max_attempts ]; do
        if docker-compose ps | grep -q "$service.*Up"; then
            log_success "$service is ready"
            return 0
        fi
        
        attempt=$((attempt + 1))
        echo -n "."
        sleep $RETRY_INTERVAL
    done

    echo ""
    log_error "$service failed to start within expected time"
    return 1
}

# Wait for database connection
wait_for_database() {
    local max_attempts=30
    local attempt=0

    log_step "Waiting for database connection..."
    
    # Give database container time to initialize
    sleep 5

    while [ $attempt -lt $max_attempts ]; do
        # Ping database directly from db container (most reliable method)
        if docker-compose exec -T db mysqladmin ping -h localhost -u root -prootpassword --silent &> /dev/null; then
            log_success "Database connection established"
            return 0
        fi
        
        attempt=$((attempt + 1))
        
        # Show progress less frequently
        if [ $((attempt % 10)) -eq 0 ]; then
            echo ""
            log_info "Still waiting... (attempt $attempt/$max_attempts)"
        else
            echo -n "."
        fi
        
        sleep 2
    done

    echo ""
    log_error "Database connection failed after $max_attempts attempts"
    echo ""
    log_error "Backend logs:"
    docker-compose logs --tail=20 backend
    echo ""
    log_error "Database logs:"
    docker-compose logs --tail=20 db
    return 1
}

# Run database migrations
run_migrations() {
    log_step "Running database migrations..."
    
    # Wait for database to be fully ready
    sleep 3
    
    if docker-compose exec -T backend php artisan migrate --force; then
        log_success "Migrations completed"
    else
        log_warning "Migrations failed. You may need to run them manually."
        return 1
    fi
}

# Seed database (optional)
seed_database() {
    read -p "Do you want to seed the database with sample data? (Y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        log_step "Seeding database..."
        if docker-compose exec -T backend php artisan db:seed --force; then
            log_success "Database seeded"
        else
            log_warning "Seeding failed"
        fi
    else
        log_info "Skipping database seeding"
    fi
}

# Check queue worker
check_queue_worker() {
    log_step "Checking queue worker status..."
    
    if docker-compose exec -T backend php artisan queue:work --once --stop-when-empty &> /dev/null; then
        log_success "Queue worker is operational"
        return 0
    else
        log_warning "Queue worker test failed"
        log_info "This may be normal if no jobs are queued yet"
        return 0
    fi
}

# Configure development-specific settings (after containers start)
configure_development_mode() {
    log_step "Configuring development environment..."
    
    # Enable debug mode in Laravel
    docker-compose exec -T backend sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env 2>/dev/null || true
    docker-compose exec -T backend sed -i 's/APP_ENV=production/APP_ENV=local/' .env 2>/dev/null || true
    
    # Clear and rebuild cache
    docker-compose exec -T backend php artisan config:clear 2>/dev/null || true
    docker-compose exec -T backend php artisan cache:clear 2>/dev/null || true
    
    log_success "Development environment configured"
    log_info "Hot-reload enabled, debug mode active"
}

# Health check
health_check() {
    log_step "Running health checks..."
    
    local checks_passed=0
    local total_checks=3
    
    # Check backend (HTTPS on 8443) - accept any response including 404
    if curl -k -s -o /dev/null -w "%{http_code}" https://localhost:8443/api | grep -qE '^(200|404)'; then
        log_success "Backend is accessible"
        checks_passed=$((checks_passed + 1))
    else
        log_warning "Backend not responding at https://localhost:8443/api"
    fi
    
    # Check frontend (HTTPS on 443)
    if curl -f -k https://localhost &> /dev/null; then
        log_success "Frontend is accessible"
        checks_passed=$((checks_passed + 1))
    else
        log_warning "Frontend not responding at https://localhost"
    fi
    
    # Check database
    if docker-compose exec -T db mysqladmin ping -h localhost -u root -prootpassword &> /dev/null; then
        log_success "Database is accessible"
        checks_passed=$((checks_passed + 1))
    else
        log_warning "Database not responding"
    fi
    
    echo ""
    log_info "Health check: $checks_passed/$total_checks services healthy"
}

# Verify installation
verify_installation() {
    log_step "Running installation verification tests..."
    
    local tests_passed=0
    local total_tests=3
    
    # Test 1: Backend health (HTTPS on 8443) - accept any response including 404
    if curl -k -s -o /dev/null -w "%{http_code}" https://localhost:8443/api | grep -qE '^(200|404)'; then
        log_success "Backend health check passed"
        tests_passed=$((tests_passed + 1))
    else
        log_warning "Backend health check failed"
    fi
    
    # Test 2: Frontend loads (HTTPS on 443)
    if curl -f -k https://localhost &> /dev/null; then
        log_success "Frontend accessibility passed"
        tests_passed=$((tests_passed + 1))
    else
        log_warning "Frontend accessibility failed"
    fi
    
    # Test 3: Database connection
    if docker-compose exec -T db mysqladmin ping -h localhost -u root -prootpassword --silent &> /dev/null; then
        log_success "Database connectivity passed"
        tests_passed=$((tests_passed + 1))
    else
        log_warning "Database connectivity failed"
    fi
    
    echo ""
    if [ $tests_passed -eq $total_tests ]; then
        log_success "All verification tests passed! ($tests_passed/$total_tests)"
        echo ""
    else
        log_warning "Some tests failed ($tests_passed/$total_tests)"
    fi
}

# Display setup summary
display_setup_summary() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log_success "📊 Setup Summary"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # Count users
    local user_count=$(docker-compose exec -T backend php artisan tinker --execute="echo App\\Models\\User::count();" 2>/dev/null | tail -n1 | tr -d '\r')
    echo "👥 Users created: ${user_count:-0}"
    
    # Count transactions (if seeded)
    local tx_count=$(docker-compose exec -T backend php artisan tinker --execute="echo App\\Models\\Transaction::count() ?? 0;" 2>/dev/null | tail -n1 | tr -d '\r')
    echo "💸 Sample transactions: ${tx_count:-0}"
    
    echo ""
}

# Display service URLs and next steps
display_info() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log_success "MiniWallet setup complete!"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    log_success "Application URLs:"
    echo "   Frontend:  https://localhost"
    echo "   Backend:   https://localhost:8443/api"
    echo "   Database:  localhost:3306"
    echo ""
    log_success "Test user:"
    echo "   Email:        test@pimono.ae"
    echo "   Password:     password"
    echo ""
    log_success "Useful commands:"
    echo "   Queue worker:     docker-compose exec backend php artisan queue:work"
    echo "   Fresh start:      docker-compose down -v && ./setup.sh --build"
    echo ""
}

# Cleanup on error
cleanup_on_error() {
    log_error "Setup failed. Cleaning up..."
    docker-compose down 2>/dev/null || true
    exit 1
}

# Main setup flow
main() {
    trap cleanup_on_error ERR
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "   🏦 Mini Wallet Setup v${SCRIPT_VERSION}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # Pre-flight checks
    check_dependencies
    check_docker_running
    check_port_availability
    validate_env_example
    
    # Environment setup
    setup_env_file
    validate_pusher_config
    
    # Stop any existing containers
    stop_existing_containers
    
    # Start services in production mode
    start_containers "$@"
    
    # Wait for services
    wait_for_service "backend"
    wait_for_service "frontend"
    wait_for_service "db"
    
    # Wait for database connection
    wait_for_database
    
    # Post-setup tasks
    run_migrations || log_warning "Continue without migrations"
    seed_database
    
    # Additional checks
    check_queue_worker
    
    # Health checks
    health_check
    
    # Verification
    verify_installation
    
    # Display summary
    display_setup_summary
    display_info
}

# Run main function
main "$@"
