#!/bin/bash
#
# SaaS School System - Deployment Script
# Usage: bash deploy.sh [branch] [environment]
#
# Example: bash deploy.sh main production
#

set -e

# Configuration
BRANCH=${1:-main}
ENV=${2:-production}
APP_DIR="/var/www/saas"
REPO="https://github.com/Morgan-Okoth/saas-system.git"
DATE=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root or www-data
if [[ $EUID -ne 0 ]] && [[ $(whoami) != "www-data" ]]; then
    log_error "This script must be run as root or www-data"
    exit 1
fi

log_info "Starting deployment for SaaS School System"
log_info "Branch: $BRANCH"
log_info "Environment: $ENV"
log_info "Date: $DATE"

# Step 1: Clone or update repository
if [ -d "$APP_DIR/.git" ]; then
    log_info "Repository exists, pulling latest changes..."
    cd $APP_DIR
    git fetch origin
    git checkout $BRANCH
    git pull origin $BRANCH
else
    log_info "Cloning repository..."
    cd /var/www
    git clone -b $BRANCH $REPO saas
    cd $APP_DIR
fi

# Step 2: Create backup
if [ -d "$APP_DIR/storage" ]; then
    log_info "Creating backup..."
    mkdir -p /var/backups/saas
    tar -czf /var/backups/saas/backup_$DATE.tar.gz \
        -C $APP_DIR \
        --exclude=node_modules \
        --exclude=.git \
        --exclude=vendor \
        .
    log_info "Backup created: /var/backups/saas/backup_$DATE.tar.gz"
fi

# Step 3: Install PHP dependencies
log_info "Installing PHP dependencies..."
cd $APP_DIR
composer install --optimize-autoloader --no-interaction --prefer-dist \
    ${COMPOSER_FLAGS:---no-dev}

# Step 4: Install Node dependencies and build assets
log_info "Building frontend assets..."
npm install
npm run build

# Step 5: Set permissions
log_info "Setting permissions..."
chown -R www-data:www-data $APP_DIR
chmod -R 775 $APP_DIR/storage
chmod -R 775 $APP_DIR/bootstrap/cache

# Step 6: Environment setup
if [ ! -f "$APP_DIR/.env" ]; then
    log_warn ".env file not found, copying from example..."
    cp $APP_DIR/.env.example $APP_DIR/.env
    log_warn "Please update .env with your configuration"
fi

# Step 7: Generate application key
log_info "Generating application key..."
php artisan key:generate --force

# Step 8: Database migrations
log_info "Running database migrations..."
php artisan migrate --force

# Step 9: Seed permissions
log_info "Seeding permissions and roles..."
php artisan db:seed --class=PermissionSeeder --force

# Step 10: Clear caches
log_info "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Step 11: Optimize
if [ "$ENV" == "production" ]; then
    log_info "Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Step 12: Restart queue workers
log_info "Restarting queue workers..."
if command -v supervisorctl &> /dev/null; then
    supervisorctl restart saas-worker:*
    log_info "Queue workers restarted"
else
    log_warn "Supervisor not found, skipping queue restart"
fi

# Step 13: Health check
log_info "Running health check..."
php artisan --version > /dev/null 2>&1
if [ $? -eq 0 ]; then
    log_info "Laravel: OK"
else
    log_error "Laravel: FAILED"
fi

php artisan migrate --pretend > /dev/null 2>&1
if [ $? -eq 0 ]; then
    log_info "Database: OK"
else
    log_error "Database: FAILED"
fi

redis-cli ping > /dev/null 2>&1
if [ $? -eq 0 ]; then
    log_info "Redis: OK"
else
    log_error "Redis: FAILED"
fi

# Step 14: Cleanup old backups
log_info "Cleaning up old backups..."
find /var/backups/saas -name "*.tar.gz" -mtime +30 -delete

log_info "${GREEN}Deployment completed successfully!${NC}"
log_info "Application URL: https://saas.yourdomain.com"
log_info ""
log_info "Next steps:"
log_info "  1. Verify application is running"
log_info "  2. Check queue workers: supervisorctl status"
log_info "  3. Monitor logs: tail -f storage/logs/laravel.log"
log_info "  4. Test registration: https://saas.yourdomain.com/register"
